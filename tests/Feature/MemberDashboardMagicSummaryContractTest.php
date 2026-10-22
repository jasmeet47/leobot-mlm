<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class MemberDashboardMagicSummaryContractTest extends TestCase
{
    public function test_member_dashboard_controller_provides_magic_summary_contract(): void
    {
        $controller = file_get_contents(
            dirname(__DIR__, 2) .
            '/app/Http/Controllers/Member/MemberDashboardController.php'
        );

        $this->assertMatchesRegularExpression(
            "/'magicIncomeSummary'\s*=>/",
            $controller
        );
    }

    public function test_member_dashboard_has_safe_magic_summary_guards(): void
    {
        $view = file_get_contents(
            dirname(__DIR__, 2) .
            '/resources/views/member/dashboard.blade.php'
        );

        $this->assertSame(
            3,
            substr_count(
                $view,
                '@if(isset($magicIncomeSummary) && is_array($magicIncomeSummary))'
            )
        );

        $this->assertStringNotContainsString(
            '@if($magicIncomeSummary !== null)',
            $view
        );
    }
}