<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class MemberReleasePolishTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_message_is_member_owned_admin_only_and_escaped(): void
    {
        $member = User::factory()->create(['role' => 'member', 'security_pin' => Hash::make('123456')]);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($member)->post(route('member.support.store'), ['message' => '<script>test</script>', 'user_id' => $admin->id])->assertSessionHas('support_status');
        $this->assertDatabaseHas('support_messages', ['user_id' => $member->id, 'message' => '<script>test</script>']);
        $this->get(route('admin.support.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.support.index'))->assertOk()->assertSee('&lt;script&gt;test&lt;/script&gt;', false)->assertDontSee('<script>test</script>', false);
        $this->actingAs($admin)->post(route('member.support.store'), ['message' => 'not member'])->assertForbidden();
    }

    public function test_dashboard_sums_only_verified_deposits_and_own_completed_level_income(): void
    {
        $member = User::factory()->create(['role' => 'member', 'security_pin' => Hash::make('123456')]);
        foreach (['10', '60', '99'] as $index => $amount) {
            $id = DB::table('member_deposit_requests')->insertGetId(['user_id' => $member->id, 'request_key' => 'polish-deposit-'.$index, 'amount' => $amount, 'network' => 'BEP20', 'tx_hash' => 'hash-'.$index, 'status' => $index === 2 ? 'pending' : 'approved', 'created_at' => now(), 'updated_at' => now()]);
            if ($index !== 2) {
                WalletService::credit($member->id, 'activation_balance', $amount, 'verified_deposit', 'deposit:credit:'.$id, ['reference_type' => 'member_deposit_request', 'reference_id' => $id]);
            }
        }
        WalletService::debit($member->id, 'activation_balance', '10', 'test_activation', 'polish-used');
        WalletService::credit($member->id, 'available_balance', '1.25', 'generation_income_settlement', 'polish-level-income', ['level' => 2]);
        $other = User::factory()->create();
        WalletService::credit($other->id, 'available_balance', '99', 'generation_income_settlement', 'polish-other-income', ['level' => 2]);
        $this->actingAs($member)->get(route('member.dashboard'))->assertOk()->assertSee('70.00000000 USDT')->assertViewHas('verifiedDepositTotal', '70.00000000')->assertViewHas('levelIncome', fn ($rows) => $rows->get(2) === '1.25000000');
    }
}
