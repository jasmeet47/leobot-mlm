<?php

namespace Tests\Feature;

use App\Contracts\UsdtChainVerifier;
use App\Models\User;
use App\Services\WithdrawalPaymentConfirmationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class WithdrawalConfirmationIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    private function safeDatabase(): void
    {
        $c = DB::connection();
        $cfg = $c->getConfig();

        $this->assertSame('testing', app()->environment());
        $this->assertSame('pgsql', $c->getDriverName());
        $this->assertSame('127.0.0.1', $cfg['host']);
        $this->assertSame('5433', (string) $cfg['port']);
        $this->assertSame('leobot_financial_disposable', $c->getDatabaseName());
        $this->assertEmpty($cfg['url'] ?? null);

        $identity = DB::selectOne(
            'SELECT current_database() AS db, inet_server_port() AS port'
        );
        $this->assertSame('leobot_financial_disposable', $identity->db);
        $this->assertSame(5433, (int) $identity->port);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->safeDatabase();

        config()->set('leobot_withdrawal.external_payment_verification_enabled', true);
        config()->set('leobot.withdrawals_enabled', true);
        DB::table('withdrawal_settings')->where('id', 1)
            ->update(['admin_review_enabled' => true]);
    }

    private function person(string $role): User
    {
        $id = Str::random(14);

        $key = DB::table('users')->insertGetId([
            'username' => 'PC' . $id,
            'name' => 'Confirmation Test',
            'email' => $id . '@example.test',
            'phone' => (string) random_int(1000000000, 9999999999),
            'password' => bcrypt(Str::random(32)),
            'status' => 'active',
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::findOrFail($key);
    }

    private function withdrawal(User $member): int
    {
        $address = '0x1111111111111111111111111111111111111111';

        $wallet = DB::table('usdt_member_wallets')->insertGetId([
            'user_id' => $member->id,
            'network' => 'BEP20',
            'address' => $address,
            'verified_at' => now(),
            'verification_method' => 'integration_test_fixture',
            'proof_digest' => str_repeat('a', 64),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('withdrawal_requests')->insertGetId([
            'user_id' => $member->id,
            'request_key' => (string) Str::uuid(),
            'wallet_type' => 'earnings',
            'gross_amount' => '10.00000000',
            'fee_amount' => '0.00000000',
            'net_amount' => '10.00000000',
            'status' => 'approved_awaiting_payment',
            'usdt_member_wallet_id' => $wallet,
            'payment_network' => 'BEP20',
            'locked_payment_address' => $address,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function service(bool $verified): WithdrawalPaymentConfirmationService
    {
        $chain = new class($verified) implements UsdtChainVerifier {
            public function __construct(private bool $result) {}

            public function verified(
                string $network,
                string $txHash,
                string $recipient,
                string $amount
            ): bool {
                return $this->result;
            }
        };

        return new WithdrawalPaymentConfirmationService($chain);
    }

    public function test_verified_payment_becomes_paid(): void
    {
        $admin = $this->person('admin');
        $id = $this->withdrawal($this->person('member'));
        $hash = '0x' . str_repeat('a', 64);

        $this->service(true)->confirm($admin, $id, 'BEP20', $hash);

        $this->assertSame(
            'paid',
            DB::table('withdrawal_requests')->find($id)->status
        );
        $this->assertSame(
            1,
            DB::table('withdrawal_payment_evidence')
                ->where('withdrawal_request_id', $id)->count()
        );
        $this->assertSame(
            1,
            DB::table('withdrawal_events')
                ->where('withdrawal_request_id', $id)
                ->where('event_type', 'verified_on_chain_paid')->count()
        );
    }

    public function test_failed_chain_verification_leaves_payment_unpaid(): void
    {
        $admin = $this->person('admin');
        $id = $this->withdrawal($this->person('member'));

        try {
            $this->service(false)->confirm(
                $admin, $id, 'BEP20', '0x' . str_repeat('b', 64)
            );
            $this->fail('Unverified payment was accepted.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('verification failed', $e->getMessage());
        }

        $this->assertSame(
            'approved_awaiting_payment',
            DB::table('withdrawal_requests')->find($id)->status
        );
        $this->assertSame(
            0,
            DB::table('withdrawal_payment_evidence')
                ->where('withdrawal_request_id', $id)->count()
        );
    }

    public function test_same_withdrawal_cannot_be_confirmed_twice(): void
    {
        $admin = $this->person('admin');
        $id = $this->withdrawal($this->person('member'));
        $service = $this->service(true);

        $service->confirm($admin, $id, 'BEP20', '0x' . str_repeat('c', 64));

        try {
            $service->confirm($admin, $id, 'BEP20', '0x' . str_repeat('d', 64));
            $this->fail('Duplicate confirmation was accepted.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('approved unpaid', $e->getMessage());
        }

        $this->assertSame(
            1,
            DB::table('withdrawal_payment_evidence')
                ->where('withdrawal_request_id', $id)->count()
        );
    }

    public function test_member_cannot_confirm_payment(): void
    {
        $member = $this->person('member');
        $id = $this->withdrawal($this->person('member'));

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        $this->service(true)->confirm(
            $member, $id, 'BEP20', '0x' . str_repeat('e', 64)
        );
    }
}
