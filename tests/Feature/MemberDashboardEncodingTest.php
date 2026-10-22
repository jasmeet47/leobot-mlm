<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class MemberDashboardEncodingTest extends TestCase
{
    public function test_magic_matrix_heading_has_correct_symbol(): void
    {
        $blade = file_get_contents(
            dirname(__DIR__, 2) .
            '/resources/views/member/dashboard.blade.php'
        );

        $this->assertStringContainsString(
            '2×2 Matrix',
            $blade
        );

        $this->assertStringNotContainsString(
            '2├ù2',
            $blade
        );
    }
}