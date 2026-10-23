<?php
namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class LumiFlowOnReleaseSafetyContractTest extends TestCase
{
    public function test_registration_otp_migration_defaults_disabled(): void
    {
        $s = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_10_20_210000_create_registration_email_otp_infrastructure.php');
        $this->assertStringContainsString("'is_enabled' => false", $s);
        $this->assertStringContainsString('registration_email_otp_challenges', $s);
        $this->assertStringContainsString('registration_email_otp_setting_audits', $s);
    }
    public function test_retirement_retains_audit_and_identity(): void
    {
        $s = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_10_20_000000_add_member_retirement_controls.php');
        $this->assertStringContainsString('member_retirement_audits', $s);
        $this->assertStringContainsString('reserved_member_identities', $s);
    }
    public function test_physical_deletion_is_disabled_by_default(): void
    {
        $s = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Admin/MemberSafeDeleteController.php');
        $this->assertStringContainsString("config('leobot_safe_delete.enabled', false) === true", $s);
    }
    public function test_physical_deletion_scans_dependents(): void
    {
        $s = file_get_contents(dirname(__DIR__, 2).'/app/Services/MemberSafeDeletionService.php');
        $this->assertStringContainsString('information_schema.columns', $s);
        $this->assertStringContainsString('reserved_member_identities', $s);
    }
}
