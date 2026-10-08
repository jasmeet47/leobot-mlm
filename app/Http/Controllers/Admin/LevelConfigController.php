<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class LevelConfigController extends Controller
{
    public function index()
    {
        $levels = DB::table('level_settings')
            ->orderBy('level_number', 'asc')
            ->get();

        return view(
            'admin.settings.level-config',
            compact('levels')
        );
    }

    public function update(Request $request)
    {
        // Only an authenticated admin may update settings.
        $admin = $request->user();

        abort_unless(
            $admin &&
            strtoupper((string) $admin->username) === 'ADMIN' &&
            $admin->status === 'active',
            403,
            'Unauthorized.'
        );

        $commission = $request->input('commission', []);
        $activeLevels = $request->input('active_levels', []);

        if (
            !is_array($commission) ||
            !is_array($activeLevels)
        ) {
            throw ValidationException::withMessages([
                'commission' =>
                    'Invalid commission settings submitted.',
            ]);
        }

        // Reject unknown or invalid level numbers.
        foreach (array_keys($commission) as $level) {
            if (
                !ctype_digit((string) $level) ||
                (int) $level < 1 ||
                (int) $level > 51
            ) {
                throw ValidationException::withMessages([
                    'commission' =>
                        'Invalid commission level submitted.',
                ]);
            }
        }

        foreach ($activeLevels as $level => $value) {
            if (
                !ctype_digit((string) $level) ||
                (int) $level < 1 ||
                (int) $level > 51 ||
                !in_array($value, ['1', 1], true)
            ) {
                throw ValidationException::withMessages([
                    'active_levels' =>
                        'Invalid active level submitted.',
                ]);
            }
        }

        $preparedSettings = [];
        $totalScaled = 0;

        // Validate all 51 levels before saving anything.
        for ($i = 1; $i <= 51; $i++) {
            $isActive = isset($activeLevels[$i]);

            $rawPercentage = $isActive
                ? ($commission[$i] ?? null)
                : '0';

            if (
                $isActive &&
                (
                    !is_string($rawPercentage) ||
                    !preg_match(
                        '/^\d{1,3}(\.\d{1,9})?$/',
                        $rawPercentage
                    )
                )
            ) {
                throw ValidationException::withMessages([
                    'commission' =>
                        "Level {$i} commission must be a valid number with up to 9 decimal places.",
                ]);
            }

            if (
                $rawPercentage === null ||
                $rawPercentage === ''
            ) {
                $rawPercentage = '0';
            }

            $parts = explode(
                '.',
                (string) $rawPercentage,
                2
            );

            $whole = (int) $parts[0];

            $fraction = isset($parts[1])
                ? str_pad(
                    $parts[1],
                    9,
                    '0',
                    STR_PAD_RIGHT
                )
                : '000000000';

            $scaled =
                ($whole * 1_000_000_000) +
                (int) $fraction;

            if (
                $scaled < 0 ||
                $scaled > 100_000_000_000
            ) {
                throw ValidationException::withMessages([
                    'commission' =>
                        "Level {$i} commission must be between 0 and 100.",
                ]);
            }

            $totalScaled += $scaled;

            $wholePart = intdiv(
                $scaled,
                1_000_000_000
            );

            $fractionPart = str_pad(
                (string) ($scaled % 1_000_000_000),
                9,
                '0',
                STR_PAD_LEFT
            );

            $preparedSettings[$i] = [
                'level_number' => $i,
                'commission_percentage' =>
                    $wholePart . '.' . $fractionPart,
                'is_active' => $isActive,
            ];
        }

        // Total must be 100%, allowing tiny rounding differences.
        $targetScaled = 100_000_000_000;
        $toleranceScaled = 1_000;

        if (
            abs($totalScaled - $targetScaled) >
            $toleranceScaled
        ) {
            $whole = intdiv(
                $totalScaled,
                1_000_000_000
            );

            $fraction = str_pad(
                (string) (
                    $totalScaled % 1_000_000_000
                ),
                9,
                '0',
                STR_PAD_LEFT
            );

            throw ValidationException::withMessages([
                'commission' =>
                    "Total commission must be 100%. Current total: {$whole}.{$fraction}%",
            ]);
        }

        /*
         * SECURITY:
         * Lock existing settings before reading and updating.
         *
         * Update and audit record must succeed together.
         */
        DB::transaction(function () use (
            $request,
            $admin,
            $preparedSettings
        ) {
            $existingLevels = DB::table('level_settings')
                ->orderBy('level_number', 'asc')
                ->lockForUpdate()
                ->get();

            if (
                $existingLevels->count() !== 51 ||
                $existingLevels
                    ->pluck('level_number')
                    ->map(fn ($level) => (int) $level)
                    ->sort()
                    ->values()
                    ->all() !== range(1, 51)
            ) {
                throw new RuntimeException(
                    'Expected exactly 51 configured levels.'
                );
            }

            $oldSettings = [];

            foreach ($existingLevels as $level) {
                $oldSettings[] = [
                    'level_number' =>
                        (int) $level->level_number,

                    'commission_percentage' =>
                        (string) $level->commission_percentage,

                    'is_active' =>
                        (bool) $level->is_active,
                ];
            }

            $newSettings = [];

            foreach ($preparedSettings as $level => $setting) {
                DB::table('level_settings')
                    ->where('level_number', $level)
                    ->update([
                        'commission_percentage' =>
                            $setting['commission_percentage'],

                        'is_active' =>
                            $setting['is_active'],

                        'updated_at' => now(),
                    ]);

                $newSettings[] = $setting;
            }

            // Save audit history in the same transaction.
            DB::table('level_setting_audits')->insert([
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

                'user_agent' =>
                    $request->userAgent(),

                'created_at' => now(),

                'updated_at' => now(),
            ]);
        }, 3);

        return redirect()
            ->back()
            ->with(
                'success',
                '51-level commission settings updated and audit recorded successfully!'
            );
    }
}
