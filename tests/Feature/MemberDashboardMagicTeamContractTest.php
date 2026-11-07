<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class MemberDashboardMagicTeamContractTest extends TestCase
{
    use RefreshDatabase;

    private function dashboard(): TestResponse
    {
        $member = User::factory()->create(['role' => 'member', 'status' => 'active', 'security_pin' => Hash::make('123456')]);

        return $this->actingAs($member)->get(route('member.dashboard'))->assertOk();
    }

    public function test_controller_supplies_member_magic_team_and_renders_matrix_heading(): void
    {
        $response = $this->dashboard();
        $team = $response->viewData('magicTeam');
        $this->assertIsArray($team);
        $this->assertSame(0, $team['total']);
        $this->assertFalse($team['has_position']);
        $response->assertSee('Magic Team');
    }

    public function test_blade_handles_missing_team_without_warnings(): void
    {
        $data = $this->dashboard()->original->getData();
        $data['magicTeam'] = null;
        $html = view('member.dashboard', $data)->render();
        $this->assertStringNotContainsString('Magic Team — 2×2 Matrix', $html);
    }
}
