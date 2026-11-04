<?php

namespace Tests\Feature;

use App\Contracts\UsdtChainVerifier;
use App\Models\User;
use App\Services\DepositDestinationService;
use App\Services\StakingService;
use App\Services\VerifiedDepositService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class DepositControlTest extends TestCase
{
    use RefreshDatabase;

    private const BEP = '0x0302CDBF8563623549a4d137207be7572845cdDc';

    private const TRON = 'TXaLGPeyoEYensGJVtAT3XxAimamcyQAK4';

    protected function setUp(): void
    {
        parent::setUp();
        config(['leobot.deposits_enabled' => true, 'leobot.production_financial_approval' => true, 'leobot_usdt_rpc.enabled' => true,
            'leobot_deposits.receiving_addresses.BEP20' => self::BEP, 'leobot_deposits.receiving_addresses.TRC20' => self::TRON]);
    }

    private function account(string $role, string $status = 'active'): User
    {
        return User::factory()->create(['role' => $role, 'status' => $status, 'is_blocked' => false, 'member_login_version' => 0,
            'security_pin' => Hash::make('123456'), 'activation_balance' => '0']);
    }

    private function qr(string $network): UploadedFile
    {
        return new UploadedFile(base_path('tests/Fixtures/'.$network.'-deposit-qr.png'), 'qr.png', 'image/png', null, true);
    }

    public function test_admin_can_save_both_network_addresses_and_real_matching_qr_images(): void
    {
        $this->actingAs($this->account('admin'));
        foreach (['BEP20' => self::BEP, 'TRC20' => self::TRON] as $network => $address) {
            $this->post('/admin/deposit-control/'.$network, ['address' => $address, 'expected_revision' => 0, 'qr' => $this->qr(strtolower($network))])->assertSessionHasNoErrors();
            $current = DepositDestinationService::current($network);
            $this->assertSame($address, $current['address']);
            $this->assertTrue($current['has_qr']);
            $this->get('/admin/deposit-control/qr/'.$current['id'])->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        }
        $this->assertDatabaseCount('deposit_destinations', 2);
        $this->assertDatabaseCount('financial_ledger', 0);
    }

    public function test_member_and_blocked_admin_cannot_change_destinations(): void
    {
        $this->actingAs($this->account('member'))->get('/admin/deposit-control')->assertForbidden();
        $this->post('/admin/deposit-control/BEP20', ['address' => self::BEP, 'expected_revision' => 0])->assertForbidden();
        $admin = $this->account('admin');
        $admin->forceFill(['is_blocked' => true])->save();
        $this->actingAs($admin)->post('/admin/deposit-control/BEP20', ['address' => self::BEP, 'expected_revision' => 0])->assertForbidden();
        $this->assertDatabaseCount('deposit_destinations', 0);
    }

    public function test_mismatched_qr_checksum_and_stale_admin_revision_are_rejected(): void
    {
        $this->actingAs($this->account('admin'));
        $other = '0x'.str_repeat('b', 40);
        $this->post('/admin/deposit-control/BEP20', ['address' => $other, 'expected_revision' => 0, 'qr' => $this->qr('bep20')])->assertSessionHasErrors('qr');
        $this->post('/admin/deposit-control/TRC20', ['address' => substr(self::TRON, 0, -1).'5', 'expected_revision' => 0])->assertSessionHasErrors('address');
        $this->post('/admin/deposit-control/BEP20', ['address' => self::BEP, 'expected_revision' => 0, 'qr' => $this->qr('bep20')])->assertSessionHasNoErrors();
        $this->post('/admin/deposit-control/BEP20', ['address' => $other, 'expected_revision' => 0])->assertSessionHasErrors('address');
        $this->assertDatabaseCount('deposit_destinations', 1);
    }

    public function test_changing_address_clears_old_qr_and_preserves_old_deposit_snapshot_for_approval(): void
    {
        $admin = $this->account('admin');
        $member = $this->account('member', 'inactive');
        $this->actingAs($admin)->post('/admin/deposit-control/BEP20', ['address' => self::BEP, 'expected_revision' => 0, 'qr' => $this->qr('bep20')]);
        $revision = DepositDestinationService::current('BEP20')['id'];
        $id = VerifiedDepositService::submit($member->id, '10', 'BEP20', '0x'.str_repeat('a', 64), 'old-deposit-key', self::BEP);
        $other = '0x'.str_repeat('b', 40);
        $this->post('/admin/deposit-control/BEP20', ['address' => $other, 'expected_revision' => $revision])->assertSessionHasNoErrors();
        $this->assertFalse(DepositDestinationService::current('BEP20')['has_qr']);
        $this->assertSame($id, VerifiedDepositService::submit($member->id, '10', 'BEP20', '0x'.str_repeat('a', 64), 'old-deposit-key', self::BEP));
        $this->app->instance(UsdtChainVerifier::class, new class implements UsdtChainVerifier
        {
            public function verified(string $network, string $txHash, string $recipient, string $amount): bool
            {
                return $recipient === '0x0302CDBF8563623549a4d137207be7572845cdDc';
            }
        });
        VerifiedDepositService::approve($admin->id, $id, 'verified-old-destination');
        $this->assertSame('10.00000000', $member->fresh()->activation_balance);
        $this->assertSame('inactive', $member->fresh()->status);
        config(['leobot.staking_enabled' => true]);
        DB::table('staking_controls')->where('id', 1)->update(['enabled' => true]);
        try {
            app(StakingService::class)->create($member->id, '10', 'inactive-staking-blocked');
            $this->fail('An inactive deposit account was allowed to stake.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Member not eligible', $error->getMessage());
        }
        $this->assertSame('10.00000000', $member->fresh()->activation_balance);
        $this->assertDatabaseHas('member_deposit_requests', ['id' => $id, 'receiving_address' => self::BEP, 'status' => 'approved']);
        $this->actingAs($member)->get('/member/deposit-qr/'.$revision)->assertNotFound();
    }

    public function test_inactive_unblocked_member_can_submit_deposit_but_stale_address_quote_and_blocked_account_cannot(): void
    {
        $member = $this->account('member', 'inactive');
        $this->actingAs($member)->get('/member/deposit-transfer')->assertOk()->assertSee('Copy BEP20 Address')->assertSee('Address copied successfully.');
        $payload = ['amount' => '10', 'network' => 'BEP20', 'tx_hash' => '0x'.str_repeat('a', 64), 'request_key' => 'inactive-deposit-1', 'receiving_address' => self::BEP];
        $this->post('/member/deposit-request', $payload)->assertSessionHasNoErrors();
        $payload['receiving_address'] = '0x'.str_repeat('b', 40);
        $payload['request_key'] = 'inactive-deposit-2';
        $this->post('/member/deposit-request', $payload)->assertSessionHasErrors('deposit');
        $member->forceFill(['is_blocked' => true])->save();
        $this->actingAs($member)->post('/member/deposit-request', $payload)->assertForbidden();
        $this->assertDatabaseCount('member_deposit_requests', 1);
        $this->assertDatabaseCount('financial_ledger', 0);
    }
}
