<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LevelConfigController extends Controller
{
    public function index()
    {
        $levels = DB::table('level_settings')
            ->orderBy('level_number', 'asc')
            ->get();

        return view('admin.settings.level-config', compact('levels'));
    }

    public function update(Request $request)
    {
        $commission = $request->input('commission', []);
        $activeLevels = $request->input('active_levels', []);

        if (!is_array($commission) || !is_array($activeLevels)) {
            return redirect()->back()
                ->withErrors([
                    'commission' => 'Invalid commission settings submitted.',
                ])
                ->withInput();
        }

        $totalScaled = 0;

        for ($i = 1; $i <= 51; $i++) {
            $isActive = isset($activeLevels[$i]);

            $rawPercentage = $isActive
                ? ($commission[$i] ?? null)
                : '0';

            if (
                $isActive &&
                (
                    !is_string($rawPercentage) ||
                    !preg_match('/^\d{1,3}(\.\d{1,9})?$/', $rawPercentage)
                )
            ) {
                return redirect()->back()
                    ->withErrors([
                        'commission' =>
                            "Level {$i} commission must be a valid number with up to 9 decimal places.",
                    ])
                    ->withInput();
            }

            if ($rawPercentage === null || $rawPercentage === '') {
                $rawPercentage = '0';
            }

            $parts = explode('.', (string) $rawPercentage, 2);
            $whole = (int) $parts[0];
            $fraction = isset($parts[1])
                ? str_pad(substr($parts[1], 0, 9), 9, '0')
                : '000000000';

            $scaled = ($whole * 1_000_000_000) + (int) $fraction;

            if ($scaled < 0 || $scaled > 100_000_000_000) {
                return redirect()->back()
                    ->withErrors([
                        'commission' =>
                            "Level {$i} commission must be between 0 and 100.",
                    ])
                    ->withInput();
            }

            $totalScaled += $scaled;
        }

        // Allow up to 0.000001% rounding difference.
        $targetScaled = 100_000_000_000;
        $toleranceScaled = 1_000;

        if (abs($totalScaled - $targetScaled) > $toleranceScaled) {
            $whole = intdiv($totalScaled, 1_000_000_000);
            $fraction = str_pad(
                (string) ($totalScaled % 1_000_000_000),
                9,
                '0',
                STR_PAD_LEFT
            );

            return redirect()->back()
                ->withErrors([
                    'commission' =>
                        "Total commission must be 100%. Current total: {$whole}.{$fraction}%",
                ])
                ->withInput();
        }

        DB::transaction(function () use ($activeLevels, $commission) {
            for ($i = 1; $i <= 51; $i++) {
                $isActive = isset($activeLevels[$i]);

                $rawPercentage = $isActive
                    ? ($commission[$i] ?? '0')
                    : '0';

                $parts = explode('.', (string) $rawPercentage, 2);
                $whole = (int) $parts[0];
                $fraction = isset($parts[1])
                    ? str_pad(substr($parts[1], 0, 9), 9, '0')
                    : '000000000';

                $scaled = ($whole * 1_000_000_000) + (int) $fraction;

                $wholePart = intdiv($scaled, 1_000_000_000);
                $fractionPart = str_pad(
                    (string) ($scaled % 1_000_000_000),
                    9,
                    '0',
                    STR_PAD_LEFT
                );

                $percentage = $wholePart . '.' . $fractionPart;

                DB::table('level_settings')
                    ->where('level_number', $i)
                    ->update([
                        'commission_percentage' => $percentage,
                        'is_active' => $isActive,
                        'updated_at' => now(),
                    ]);
            }
        });

        return redirect()->back()->with(
            'success',
            '51-level commission settings updated successfully!'
        );
    }
}
