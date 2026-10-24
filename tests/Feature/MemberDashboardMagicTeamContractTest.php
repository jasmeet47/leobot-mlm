<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class MemberDashboardMagicTeamContractTest extends TestCase
{
    public function test_controller_supplies_magic_team_contract(): void
    {
        $controller = file_get_contents(
            dirname(__DIR__, 2) .
            '/app/Http/Controllers/Member/MemberDashboardController.php'
        );

        $this->assertMatchesRegularExpression(
            "/'magicTeam'\s*=>/",
            $controller
        );
    }

    public function test_blade_checks_magic_team_safely(): void
    {
        $blade = file_get_contents(
            dirname(__DIR__, 2) .
            '/resources/views/member/dashboard.blade.php'
        );

        $this->assertStringContainsString(
            '@if(isset($magicTeam) && is_array($magicTeam) && ($magicTeam[\'visible_levels\'] ?? 0) > 0)',
            $blade
        );

        $this->assertStringNotContainsString(
            '@if($magicTeam !== null)',
            $blade
        );
    }
}