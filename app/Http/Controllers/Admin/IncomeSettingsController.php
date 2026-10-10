<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class IncomeSettingsController extends Controller
{
    /**
     * Only the active ADMIN account can manage income settings.
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

    /**
     * Convert PostgreSQL boolean values (including string 't'/'f') safely.
     * A PHP cast of the string 'f' would incorrectly return true.
     */
    private static function dbEnabled(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['1', 't', 'true', 'yes', 'on'], true);
    }

    /**
     * Show ROI, Magic, Generation and Investment Distribution settings.
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

        $generationSettings = DB::table('generation_pool_settings')
            ->where('id', 1)
            ->first();

        $investmentSettings = DB::table('investment_distribution_settings')
            ->where('id', 1)
            ->first();

        $profitSharingSettings = DB::table('profit_sharing_settings')
            ->where('id', 1)
            ->first();

        if (
            !$roiSettings ||
            !$magicSettings ||
            !$generationSettings ||
            !$investmentSettings ||
            !$profitSharingSettings
        ) {
            throw new RuntimeException(
                'Income settings are missing.'
            );
        }

        // These flags are display-only. A stored ON state can be turned OFF
        // on save, but cannot be turned ON through this screen.
        $safetySwitchStates = [
            'roi' => self::dbEnabled($roiSettings->roi_enabled),
            'magic' => self::dbEnabled($magicSettings->magic_enabled),
            'generation' => self::dbEnabled($generationSettings->generation_enabled),
            'investment' => self::dbEnabled($investmentSettings->is_enabled),
            'combined' => self::dbEnabled($profitSharingSettings->combined_funding_enabled),
        ];

        return view(
            'admin.settings.income-settings',
            compact(
                'roiSettings',
                'magicSettings',
                'generationSettings',
                'investmentSettings',
                'profitSharingSettings',
                'safetySwitchStates'
            )
        );
    }

    /**
     * Update all income settings with an audit record.
     *
     * This method changes settings only.
     * It never distributes commissions or income.
     */
    public function update(Request $request)
    {
        $this->checkAdmin($request);

        $validated = $request->validate([
            'roi_enabled' => [
                'required',
                'in:0',
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
                'in:0',
            ],

            'minimum_activation_usdt' => [
                'required',
                'regex:/^\d{1,12}(\.\d{1,8})?$/',
            ],

            'trading_profit_pool_percent' => [
                'required',
                'regex:/^\d{1,3}(\.\d{1,9})?$/',
            ],

            'generation_enabled' => [
                'required',
                'in:0',
            ],

            'generation_pool_percent' => [
                'required',
                'regex:/^\d{1,3}(\.\d{1,9})?$/',
            ],

            'self_profit_percent' => [
                'required',
                'regex:/^\d{1,3}(\.\d{1,9})?$/',
            ],

            // Investment Distribution has a separate pool and rate.
            'investment_distribution_percent' => [
                'required',
                'regex:/^\d{1,3}(\.\d{1,9})?$/',
            ],

            // Until the payout engine is audited, only OFF is allowed.
            'investment_distribution_enabled' => [
                'required',
                'in:0',
            ],
        ]);

        // Validate percentage ranges without floating-point math.
        foreach ([
            'default_roi_percentage',
            'trading_profit_pool_percent',
            'generation_pool_percent',
            'self_profit_percent',
            'investment_distribution_percent',
        ] as $field) {
            if (
                bccomp(
                    $validated[$field],
                    '100',
                    9
                ) > 0
            ) {
                throw ValidationException::withMessages([
                    $field => 'Percentage cannot exceed 100%.',
                ]);
            }
        }

        // SELF + Generation + Magic must exactly reconcile the SAME profit.
        $totalProfitPercent = bcadd(
            bcadd(
                $validated['self_profit_percent'],
                $validated['generation_pool_percent'], 9
            ),
            $validated['trading_profit_pool_percent'], 9
        );
        if (bccomp($totalProfitPercent, '100.000000000', 9) !== 0) {
            throw ValidationException::withMessages([
                'self_profit_percent' =>
                    'SELF + Generation + Magic percentages must equal exactly 100%.',
            ]);
        }

        $admin = $request->user();

        DB::transaction(function () use (
            $request,
            $validated,
            $admin
        ) {
            // Lock settings to prevent conflicting updates.
            $roi = DB::table('roi_settings')
                ->where('id', 1)
                ->lockForUpdate()
                ->first();

            $magic = DB::table('magic_income_settings')
                ->where('id', 1)
                ->lockForUpdate()
                ->first();

            $generation = DB::table('generation_pool_settings')
                ->where('id', 1)
                ->lockForUpdate()
                ->first();

            $investment = DB::table('investment_distribution_settings')
                ->where('id', 1)
                ->lockForUpdate()
                ->first();

            $sharing = DB::table('profit_sharing_settings')
                ->where('id', 1)
                ->lockForUpdate()
                ->first();

            if (!$roi || !$magic || !$generation || !$investment || !$sharing) {
                throw new RuntimeException(
                    'Income settings not found.'
                );
            }

            // Record old values before changing anything.
            $oldSettings = [
                'roi' => [
                    'roi_enabled' => self::dbEnabled($roi->roi_enabled),
                    'distribution_mode' => $roi->distribution_mode,
                    'default_roi_percentage' =>
                        (string) $roi->default_roi_percentage,
                ],

                'magic' => [
                    'magic_enabled' => self::dbEnabled($magic->magic_enabled),
                    'minimum_activation_usdt' =>
                        (string) $magic->minimum_activation_usdt,
                    'trading_profit_pool_percent' =>
                        (string) $magic->trading_profit_pool_percent,
                ],

                'generation' => [
                    'generation_enabled' =>
                        self::dbEnabled($generation->generation_enabled),
                    'trading_profit_pool_percent' =>
                        (string) $generation->trading_profit_pool_percent,
                ],
                'profit_sharing' => [
                    'self_profit_percent' => (string) $sharing->self_profit_percent,
                    'combined_funding_enabled' => self::dbEnabled($sharing->combined_funding_enabled),
                ],
                'investment_distribution' => [
                    'is_enabled' => self::dbEnabled($investment->is_enabled),
                    'distribution_percent' =>
                        (string) $investment->distribution_percent,
                    'max_distribution_level' =>
                        (int) $investment->max_distribution_level,
                ],
            ];

            // Update ROI settings.
            DB::table('roi_settings')
                ->where('id', 1)
                ->update([
                    'roi_enabled' => false,

                    'distribution_mode' =>
                        $validated['distribution_mode'],

                    'default_roi_percentage' =>
                        $validated['default_roi_percentage'],

                    'updated_by' => $admin->id,
                    'updated_at' => now(),
                ]);

            // Update Magic Income settings.
            DB::table('magic_income_settings')
                ->where('id', 1)
                ->update([
                    'magic_enabled' => false,

                    'minimum_activation_usdt' =>
                        $validated['minimum_activation_usdt'],

                    'trading_profit_pool_percent' =>
                        $validated['trading_profit_pool_percent'],

                    'updated_by' => $admin->id,
                    'updated_at' => now(),
                ]);

            // Update 51-Level Generation Pool settings.
            DB::table('generation_pool_settings')
                ->where('id', 1)
                ->update([
                    'generation_enabled' => false,

                    'trading_profit_pool_percent' =>
                        $validated['generation_pool_percent'],

                    'updated_by' => $admin->id,
                    'updated_at' => now(),
                ]);

            // Only change Investment settings; never distribute funds.
            // Keep investment payouts OFF until the engine passes audit.
            DB::table('investment_distribution_settings')
                ->where('id', 1)
                ->update([
                    'is_enabled' => false,
                    'distribution_percent' =>
                        $validated['investment_distribution_percent'],
                    'updated_by' => $admin->id,
                    'updated_at' => now(),
                ]);

            // Unified funding switch is NOT editable on this screen.
            // This changes settings only and does not fund or pay anybody.
            DB::table('profit_sharing_settings')->where('id', 1)->update([
                'self_profit_percent' => $validated['self_profit_percent'],
                'updated_by' => $admin->id,
                'updated_at' => now(),
            ]);

            // Store new values in the audit history.
            $newSettings = [
                'roi' => [
                    'roi_enabled' => false,

                    'distribution_mode' =>
                        $validated['distribution_mode'],

                    'default_roi_percentage' =>
                        $validated['default_roi_percentage'],
                ],

                'magic' => [
                    'magic_enabled' => false,

                    'minimum_activation_usdt' =>
                        $validated['minimum_activation_usdt'],

                    'trading_profit_pool_percent' =>
                        $validated['trading_profit_pool_percent'],
                ],

                'generation' => [
                    'generation_enabled' => false,

                    'trading_profit_pool_percent' =>
                        $validated['generation_pool_percent'],
                ],

                'profit_sharing' => [
                    'self_profit_percent' => $validated['self_profit_percent'],
                    'combined_funding_enabled' => self::dbEnabled($sharing->combined_funding_enabled),
                ],
                'investment_distribution' => [
                    'is_enabled' => false,
                    'distribution_percent' =>
                        $validated['investment_distribution_percent'],
                    'max_distribution_level' =>
                        (int) $investment->max_distribution_level,
                ],
            ];

            DB::table('income_setting_audits')->insert([
                'admin_user_id' => $admin->id,
                'admin_username' => $admin->username,

                'old_settings' => json_encode(
                    $oldSettings,
                    JSON_THROW_ON_ERROR
                ),

                'new_settings' => json_encode(
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
                'ROI, Magic, SELF, Generation and Investment Distribution settings saved successfully!'
            );
    }

    /**
     * Read-only Admin Audit History.
     */
    public function history(Request $request)
    {
        $this->checkAdmin($request);

        $audits = DB::table('income_setting_audits')
            ->select(
                'id',
                'admin_user_id',
                'admin_username',
                'old_settings',
                'new_settings',
                'ip_address',
                'user_agent',
                'created_at'
            )
            ->orderByDesc('id')
            ->paginate(20);

        return view(
            'admin.settings.income-history',
            compact('audits')
        );
    }
}
