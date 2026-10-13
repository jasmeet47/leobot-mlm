<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class LevelCommissionAuditTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $id = DB::table('users')->insertGetId([
            'username' => 'LEVEL_MANAGER',
            'name' => 'Level Manager',
            'email' => Str::uuid().'@example.test',
            'phone' => '9000000000',
            'password' => Hash::make('TestPassword123!'),
            'security_pin' => null,
            'status' => 'active',
            'role' => 'admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::findOrFail($id);
    }

    private function validPayload(): array
    {
        $commission = [];
        $activeLevels = [];

        for ($level = 1; $level <= 51; $level++) {
            $commission[$level] = '0.000000000';
            $activeLevels[$level] = '1';
        }

        $commission[1] = '30.000000001';
        $commission[2] = '69.999999999';

        return [
            'commission' => $commission,
            'active_levels' => $activeLevels,
        ];
    }

    public function test_database_contains_exactly_51_levels(): void
    {
        $levels = DB::table('level_settings')
            ->orderBy('level_number')
            ->pluck('level_number')
            ->map(fn ($level) => (int) $level)
            ->all();

        $this->assertSame(range(1, 51), $levels);
    }

    public function test_valid_100_percent_update_creates_audit(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin);

        $this->from(route('admin.levels.index'))
            ->post(
                route('admin.levels.update'),
                $this->validPayload()
            )
            ->assertRedirect(route('admin.levels.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            '30.000000001',
            (string) DB::table('level_settings')
                ->where('level_number', 1)
                ->value('commission_percentage')
        );

        $this->assertSame(
            '69.999999999',
            (string) DB::table('level_settings')
                ->where('level_number', 2)
                ->value('commission_percentage')
        );

        $this->assertSame(
            51,
            DB::table('level_settings')
                ->where('is_active', true)
                ->count()
        );

        $this->assertSame(
            1,
            DB::table('level_setting_audits')->count()
        );

        $audit = DB::table('level_setting_audits')->first();

        $this->assertSame(
            (int) $admin->id,
            (int) $audit->admin_user_id
        );

        $this->assertSame(
            'LEVEL_MANAGER',
            $audit->admin_username
        );

        $old = json_decode(
            $audit->old_settings,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $new = json_decode(
            $audit->new_settings,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertCount(51, $old);
        $this->assertCount(51, $new);

        $this->assertSame(
            '30.000000001',
            $new[0]['commission_percentage']
        );

        $this->assertSame(
            '69.999999999',
            $new[1]['commission_percentage']
        );
    }

    public function test_invalid_total_does_not_change_settings(): void
    {
        $this->actingAs($this->admin());

        $before = DB::table('level_settings')
            ->orderBy('level_number')
            ->pluck('commission_percentage')
            ->map(fn ($value) => (string) $value)
            ->all();

        $payload = $this->validPayload();

        $payload['commission'][2] = '68.999999999';

        $this->from(route('admin.levels.index'))
            ->post(route('admin.levels.update'), $payload)
            ->assertSessionHasErrors(['commission']);

        $after = DB::table('level_settings')
            ->orderBy('level_number')
            ->pluck('commission_percentage')
            ->map(fn ($value) => (string) $value)
            ->all();

        $this->assertSame($before, $after);

        $this->assertSame(
            0,
            DB::table('level_setting_audits')->count()
        );
    }

    public function test_more_than_nine_decimal_places_are_rejected(): void
    {
        $this->actingAs($this->admin());

        $payload = $this->validPayload();

        $payload['commission'][1] = '30.1234567891';

        $this->from(route('admin.levels.index'))
            ->post(route('admin.levels.update'), $payload)
            ->assertSessionHasErrors(['commission']);

        $this->assertSame(
            0,
            DB::table('level_setting_audits')->count()
        );
    }

    public function test_level_52_is_rejected(): void
    {
        $this->actingAs($this->admin());

        $payload = $this->validPayload();

        $payload['commission'][52] = '1';

        $this->from(route('admin.levels.index'))
            ->post(route('admin.levels.update'), $payload)
            ->assertSessionHasErrors(['commission']);

        $this->assertSame(
            0,
            DB::table('level_setting_audits')->count()
        );
    }

    public function test_invalid_active_level_value_is_rejected(): void
    {
        $this->actingAs($this->admin());

        $payload = $this->validPayload();

        $payload['active_levels'][1] = 'invalid';

        $this->from(route('admin.levels.index'))
            ->post(route('admin.levels.update'), $payload)
            ->assertSessionHasErrors(['active_levels']);

        $this->assertSame(
            0,
            DB::table('level_setting_audits')->count()
        );
    }

    public function test_missing_database_level_rolls_back_update(): void
    {
        $this->actingAs($this->admin());

        DB::table('level_settings')
            ->where('level_number', 51)
            ->delete();

        $before = DB::table('level_settings')
            ->orderBy('level_number')
            ->pluck('commission_percentage')
            ->map(fn ($value) => (string) $value)
            ->all();

        $this->withoutExceptionHandling();

        try {
            $this->post(
                route('admin.levels.update'),
                $this->validPayload()
            );

            $this->fail(
                'Expected RuntimeException for missing level.'
            );
        } catch (\RuntimeException $exception) {
            $this->assertSame(
                'Expected exactly 51 configured levels.',
                $exception->getMessage()
            );
        }

        $after = DB::table('level_settings')
            ->orderBy('level_number')
            ->pluck('commission_percentage')
            ->map(fn ($value) => (string) $value)
            ->all();

        $this->assertSame($before, $after);

        $this->assertSame(
            0,
            DB::table('level_setting_audits')->count()
        );
    }
}
