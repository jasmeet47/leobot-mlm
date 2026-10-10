<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\IncomeSettingsController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

class IncomeSettingsSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $username = 'ADMIN'): User
    {
        $id = DB::table('users')->insertGetId([
            'username' => $username,
            'name' => 'Test Admin',
            'email' => str_replace('-', '', (string) Str::uuid()).'@example.test',
            'phone' => '9000000000',
            'password' => Hash::make('TestPassword123!'),
            'security_pin' => null,
            'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return User::findOrFail($id);
    }

    private function payload(): array
    {
        return [
            'roi_enabled' => '0',
            'distribution_mode' => 'manual',
            'default_roi_percentage' => '0.20',
            'magic_enabled' => '0',
            'minimum_activation_usdt' => '50',
            'trading_profit_pool_percent' => '3',
            'generation_enabled' => '0',
            'generation_pool_percent' => '30',
            'self_profit_percent' => '67',
            'investment_distribution_percent' => '5',
            'investment_distribution_enabled' => '0',
        ];
    }

    public function test_postgresql_boolean_values_are_decoded_correctly(): void
    {
        $method = new ReflectionMethod(IncomeSettingsController::class, 'dbEnabled');
        foreach (['f', 'false', '0', 0, false, null, 'off'] as $off) {
            $this->assertFalse($method->invoke(null, $off));
        }
        foreach (['t', 'true', '1', 1, true, 'on'] as $on) {
            $this->assertTrue($method->invoke(null, $on));
        }
    }

    public function test_admin_page_does_not_offer_payout_enable_controls(): void
    {
        $this->actingAs($this->user());
        $response = $this->get(route('admin.income.index'));
        $response->assertOk();
        foreach (['roi_enabled', 'magic_enabled', 'generation_enabled'] as $field) {
            $response->assertSee('type="hidden" name="'.$field.'" value="0"', false);
        }
        $response->assertSee('LOCKED OFF');
    }

    public function test_attempts_to_enable_payout_switches_are_rejected(): void
    {
        $this->actingAs($this->user());
        foreach (['roi_enabled','magic_enabled','generation_enabled', 'investment_distribution_enabled'] as $field) {
            $payload = $this->payload();
            $payload[$field] = '1';
            $this->from(route('admin.income.index'))
                ->post(route('admin.income.update'), $payload)
                ->assertSessionHasErrors([$field]);
        }
        $this->assertSame(0, DB::table('income_setting_audits')->count());
    }

    public function test_saving_changes_fails_closed_and_audits_previous_values(): void
    {
        $this->actingAs($this->user());
        DB::table('roi_settings')->where('id',1)->update(['roi_enabled' => true]);
        DB::table('magic_income_settings')->where('id',1)->update(['magic_enabled' => true]);
        DB::table('generation_pool_settings')->where('id',1)->update(['generation_enabled' => true]);
        DB::table('investment_distribution_settings')->where('id',1)->update(['is_enabled' => true]);

        $this->from(route('admin.income.index'))
            ->post(route('admin.income.update'), $this->payload())
            ->assertRedirect(route('admin.income.index'))
            ->assertSessionHasNoErrors();

        $this->assertEquals(0, DB::table('roi_settings')->where('id',1)->value('roi_enabled'));
        $this->assertEquals(0, DB::table('magic_income_settings')->where('id',1)->value('magic_enabled'));
        $this->assertEquals(0, DB::table('generation_pool_settings')->where('id',1)->value('generation_enabled'));
        $this->assertEquals(0, DB::table('investment_distribution_settings')->where('id',1)->value('is_enabled'));
        $history = DB::table('income_setting_audits')->first();
        $this->assertNotNull($history);
        $old = json_decode($history->old_settings, true, 512, JSON_THROW_ON_ERROR);
        $new = json_decode($history->new_settings, true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($old['roi']['roi_enabled']);
        $this->assertTrue($old['magic']['magic_enabled']);
        $this->assertTrue($old['generation']['generation_enabled']);
        $this->assertFalse($new['roi']['roi_enabled']);
        $this->assertFalse($new['magic']['magic_enabled']);
        $this->assertFalse($new['generation']['generation_enabled']);
    }

    public function test_percentages_must_equal_one_hundred_and_dont_save_on_error(): void
    {
        $this->actingAs($this->user());
        $payload = $this->payload();
        $payload['self_profit_percent'] = '68';
        $this->from(route('admin.income.index'))
            ->post(route('admin.income.update'), $payload)
            ->assertSessionHasErrors(['self_profit_percent']);
        $this->assertSame(0, DB::table('income_setting_audits')->count());
    }
}
