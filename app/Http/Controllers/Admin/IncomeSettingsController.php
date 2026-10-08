<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class IncomeSettingsController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Check Admin Permission
    |--------------------------------------------------------------------------
    */

    private function checkAdmin(Request $request): void
    {
        $admin = $request->user();

        abort_unless(
            $admin &&
            strtoupper((string) $admin->username) === 'ADMIN' &&
            $admin->status === 'active',
            403,
            'Unauthorized.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Show ROI and Magic Income Settings
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $this->checkAdmin($request);

        $roiSettings = DB::table('roi_settings')
            ->where('id', 1)
            ->first();

        $magicSettings = DB::table('magic_income_settings')
            ->where('id', 1)
            ->first();

        if (!$roiSettings || !$magicSettings) {
            throw new RuntimeException(
                'Income settings are missing.'
            );
        }

        return view(
            'admin.settings.income-settings',
            compact('roiSettings', 'magicSettings')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update ROI and Magic Income Settings
    |--------------------------------------------------------------------------
    */

    public function update(Request $request)
    {
        $this->checkAdmin($request);

        $validated = $request->validate([
            'roi_enabled' => [
                'required',
                'in:0,1',
            ],

            'distribution_mode' => [
                'required',
                'in:daily,weekly,monthly,manual',
            ],

            'default_roi_percentage' => [
                'required',
                'regex:/^\d{1,3}(\.\d{1,9})?$/',
            ],

            'magic_enabled' => [
                'required',
                'in:0,1',
            ],

            'minimum_activation_usdt' => [
                'required',
                'regex:/^\d{1,12}(\.\d{1,8})?$/',
            ],

            'trading_profit_pool_percent' => [
                'required',
                'regex:/^\d{1,3}(\.\d{1,9})?$/',
            ],
        ]);

        /*
         * Validate percentage ranges precisely.
         */
        if (
            bccomp(
                $validated['default_roi_percentage'],
                '100',
                9
            ) > 0
        ) {
            throw ValidationException::withMessages([
                'default_roi_percentage' =>
                    'ROI percentage cannot exceed 100%.',
            ]);
        }

        if (
            bccomp(
                $validated['trading_profit_pool_percent'],
                '100',
                9
            ) > 0
        ) {
            throw ValidationException::withMessages([
                'trading_profit_pool_percent' =>
                    'Magic Pool percentage cannot exceed 100%.',
            ]);
        }

        $admin = $request->user();

        DB::transaction(function () use (
            $request,
            $validated,
            $admin
        ) {
            $roi = DB::table('roi_settings')
                ->where('id', 1)
                ->lockForUpdate()
                ->first();

            $magic = DB::table('magic_income_settings')
                ->where('id', 1)
                ->lockForUpdate()
                ->first();

            if (!$roi || !$magic) {
                throw new RuntimeException(
                    'Income settings not found.'
                );
            }

            /*
             * Capture previous settings for audit.
             */
            $oldSettings = [
                'roi' => [
                    'roi_enabled' =>
                        (bool) $roi->roi_enabled,

                    'distribution_mode' =>
                        $roi->distribution_mode,

                    'default_roi_percentage' =>
                        (string) $roi->default_roi_percentage,
                ],

                'magic' => [
                    'magic_enabled' =>
                        (bool) $magic->magic_enabled,

                    'minimum_activation_usdt' =>
                        (string) $magic->minimum_activation_usdt,

                    'trading_profit_pool_percent' =>
                        (string) $magic->trading_profit_pool_percent,
                ],
            ];

            /*
             * Update Global ROI Settings.
             *
             * This does not distribute money.
             */
            DB::table('roi_settings')
                ->where('id', 1)
                ->update([
                    'roi_enabled' =>
                        $validated['roi_enabled'] === '1',

                    'distribution_mode' =>
                        $validated['distribution_mode'],

                    'default_roi_percentage' =>
                        $validated['default_roi_percentage'],

                    'updated_by' => $admin->id,
                    'updated_at' => now(),
                ]);

            /*
             * Update Magic Income Settings.
             *
             * Trading Profit Pool defaults to 3%
             * but Admin can change the percentage.
             */
            DB::table('magic_income_settings')
                ->where('id', 1)
                ->update([
                    'magic_enabled' =>
                        $validated['magic_enabled'] === '1',

                    'minimum_activation_usdt' =>
                        $validated['minimum_activation_usdt'],

                    'trading_profit_pool_percent' =>
                        $validated['trading_profit_pool_percent'],

                    'updated_by' => $admin->id,
                    'updated_at' => now(),
                ]);

            /*
             * Capture new settings for audit.
             */
            $newSettings = [
                'roi' => [
                    'roi_enabled' =>
                        $validated['roi_enabled'] === '1',

                    'distribution_mode' =>
                        $validated['distribution_mode'],

                    'default_roi_percentage' =>
                        $validated['default_roi_percentage'],
                ],

                'magic' => [
                    'magic_enabled' =>
                        $validated['magic_enabled'] === '1',

                    'minimum_activation_usdt' =>
                        $validated['minimum_activation_usdt'],

                    'trading_profit_pool_percent' =>
                        $validated['trading_profit_pool_percent'],
                ],
            ];

            /*
             * Permanent Admin Audit History.
             *
             * The audit table will be created
             * in the next migration step.
             */
            DB::table('income_setting_audits')->insert([
                'admin_user_id' => $admin->id,

                'admin_username' =>
                    $admin->username,

                'old_settings' =>
                    json_encode(
                        $oldSettings,
                        JSON_THROW_ON_ERROR
                    ),

                'new_settings' =>
                    json_encode(
                        $newSettings,
                        JSON_THROW_ON_ERROR
                    ),

                'ip_address' => $request->ip(),

                'user_agent' => $request->userAgent(),

                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, 3);

        return redirect()
            ->back()
            ->with(
                'success',
                'ROI and Magic Income settings updated successfully!'
            );
    }
}
