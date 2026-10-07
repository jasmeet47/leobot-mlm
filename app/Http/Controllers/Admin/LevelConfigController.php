public function update(Request $request)
{
    $totalPercentage = 0.0;

    for ($i = 1; $i <= 51; $i++) {
        $isActive = $request->has("active_levels.$i");

        $percentage = $isActive
            ? (float) ($request->input("commission.$i") ?? 0)
            : 0.0;

        if ($percentage < 0 || $percentage > 100) {
            return redirect()->back()
                ->withErrors([
                    'commission' => "Level {$i} commission must be between 0 and 100."
                ])
                ->withInput();
        }

        $totalPercentage += $percentage;
    }

    // Allow a very small rounding difference.
    if (abs($totalPercentage - 100.0) > 0.000001) {
        return redirect()->back()
            ->withErrors([
                'commission' => sprintf(
                    'Total commission must be 100%%. Current total: %.9f%%',
                    $totalPercentage
                )
            ])
            ->withInput();
    }

    DB::transaction(function () use ($request) {
        for ($i = 1; $i <= 51; $i++) {
            $isActive = $request->has("active_levels.$i");

            $percentage = $isActive
                ? (float) ($request->input("commission.$i") ?? 0)
                : 0.0;

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