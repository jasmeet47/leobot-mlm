<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AdminSaveFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    public function test_twenty_rates_persist_on_refresh_and_success_toast_appears_once(): void
    {
        $admin = $this->admin();
        $rates = array_fill_keys(range(1, 20), '5.000000000');
        $rates[1] = '06.000000000';
        $rates[20] = '4.000000000';
        $this->actingAs($admin)->from('/admin/magic-matrix')->post(route('admin.magic.rates'), ['rates' => $rates])->assertRedirect('/admin/magic-matrix')->assertSessionHasNoErrors()->assertSessionHas('status');
        $this->assertDatabaseHas('magic_matrix_level_rates', ['level' => 1, 'pool_percent' => '6.000000000']);
        $this->assertDatabaseHas('magic_matrix_level_rates', ['level' => 20, 'pool_percent' => '4.000000000']);
        $this->assertDatabaseHas('magic_matrix_settings_audits', ['action' => 'level_rates_update']);
        $html = $this->get('/admin/magic-matrix')->assertOk()->assertSee('6.000000000')->getContent();
        $this->assertSame(1, substr_count($html, 'data-lf-toast="success"'));
        $this->assertStringContainsString('सफल!', $html);
        $this->get('/admin/magic-matrix')->assertSee('6.000000000')->assertDontSee('data-lf-toast="success"', false);
    }

    public function test_invalid_total_leaves_database_unchanged_and_red_error_without_success(): void
    {
        $before = DB::table('magic_matrix_level_rates')->orderBy('level')->pluck('pool_percent')->all();
        $this->actingAs($this->admin())->from('/admin/magic-matrix')->post(route('admin.magic.rates'), ['rates' => array_fill_keys(range(1, 20), '6')])->assertSessionHasErrors('rates');
        $this->assertSame($before, DB::table('magic_matrix_level_rates')->orderBy('level')->pluck('pool_percent')->all());
        $this->withCookie(config('session.cookie'), session()->getId())->get('/admin/magic-matrix')->assertSee('data-lf-toast="error"', false)->assertDontSee('data-lf-toast="success"', false);
        $this->assertDatabaseCount('magic_matrix_settings_audits', 0);
    }

    public function test_missing_negative_excess_precision_and_member_rate_writes_fail(): void
    {
        foreach ([array_fill_keys(range(1, 19), '5'), array_replace(array_fill_keys(range(1, 20), '5'), [1 => '-5']), array_replace(array_fill_keys(range(1, 20), '5'), [1 => '5.0000000001'])] as $rates) {
            $this->actingAs($this->admin())->post(route('admin.magic.rates'), ['rates' => $rates])->assertSessionHasErrors();
        }
        $this->actingAs(User::factory()->create(['role' => 'member', 'status' => 'active']))->post(route('admin.magic.rates'), ['rates' => array_fill_keys(range(1, 20), '5')])->assertForbidden();
    }

    public function test_database_failure_never_shows_success_and_rolls_back_all_rates(): void
    {
        $this->actingAs($this->admin());
        $before = DB::table('magic_matrix_level_rates')->orderBy('level')->pluck('pool_percent')->all();
        DB::statement("CREATE FUNCTION reject_magic_audit_test() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'test rollback'; END; $$");
        DB::statement('CREATE TRIGGER reject_magic_audit_test BEFORE INSERT ON magic_matrix_settings_audits FOR EACH ROW EXECUTE FUNCTION reject_magic_audit_test()');
        try {
            $this->from('/admin/magic-matrix')->post(route('admin.magic.rates'), ['rates' => array_fill_keys(range(1, 20), '5')])->assertSessionHasErrors();
            $this->assertSame($before, DB::table('magic_matrix_level_rates')->orderBy('level')->pluck('pool_percent')->all());
            $this->withCookie(config('session.cookie'), session()->getId())->get('/admin/magic-matrix')->assertSee('data-lf-toast="error"', false)->assertDontSee('data-lf-toast="success"', false);
        } finally {
            DB::statement('DROP TRIGGER reject_magic_audit_test ON magic_matrix_settings_audits');
            DB::statement('DROP FUNCTION reject_magic_audit_test()');
        }
    }

    public function test_user_delete_menu_exposes_safe_retirement_and_preserves_member(): void
    {
        $member = User::factory()->create(['role' => 'member', 'status' => 'active']);
        $this->actingAs($this->admin())->get(route('admin.members.deletion'))->assertOk()->assertSee('Delete Member ID');
        $this->get(route('admin.members.show', $member->id))->assertSee('Delete access / Retire member');
        $this->post(route('admin.members.retire', $member->id), ['confirm_username' => $member->username, 'reason' => 'Requested account retirement for testing'])->assertSessionHas('success');
        $this->assertDatabaseHas('users', ['id' => $member->id, 'is_blocked' => true]);
        $this->assertNotNull(DB::table('users')->where('id', $member->id)->value('deleted_at'));
    }
}
