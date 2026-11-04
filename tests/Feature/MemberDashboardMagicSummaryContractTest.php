<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class MemberDashboardMagicSummaryContractTest extends TestCase
{
    use RefreshDatabase;

    private function dashboard(): TestResponse
    {
        $member = User::factory()->create(['role' => 'member', 'status' => 'active', 'security_pin' => Hash::make('123456')]);

        return $this->actingAs($member)->get(route('member.dashboard'))->assertOk();
    }

    public function test_member_dashboard_provides_numeric_settled_magic_summary(): void
    {
        $response = $this->dashboard();
        $summary = $response->viewData('magicIncomeSummary');
        $this->assertIsArray($summary);
        foreach (['weekly_usdt', 'monthly_usdt', 'lifetime_usdt'] as $period) {
            $this->assertSame(0, bccomp('0', $summary[$period], 8));
        }
        $response->assertSee('Magic Income (Settled)')->assertSee('No pending or held amounts.');
    }

    public function test_member_dashboard_handles_absent_or_partial_summary_without_blade_errors(): void
    {
        $data = $this->dashboard()->original->getData();
        $data['magicIncomeSummary'] = null;
        $html = view('member.dashboard', $data)->render();
        $this->assertStringNotContainsString('Magic Income (Settled)', $html);
        $data['magicIncomeSummary'] = ['weekly_usdt' => '12.00000001'];
        $html = view('member.dashboard', $data)->render();
        $this->assertStringContainsString('12.00000001 USDT', $html);
        $this->assertStringContainsString('0.00000000 USDT', $html);
    }
}
