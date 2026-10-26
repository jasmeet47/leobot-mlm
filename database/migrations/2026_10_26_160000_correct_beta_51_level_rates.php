<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void {
        $rates = [
            1 => '30',
            2 => '5',
            3 => '4',
            4 => '3',
            5 => '2',
            6 => '1',
            7 => '5',
            8 => '4',
            9 => '3',
            10 => '2',
            11 => '1',
            12 => '5',
            13 => '4',
            14 => '3',
            15 => '2',
            16 => '1',
            17 => '4.5',
            18 => '3.5',
            19 => '2.5',
            20 => '1.5',
            21 => '0.5',
            22 => '0.5',
            23 => '0.5',
            24 => '0.5',
            25 => '0.5',
            26 => '0.5',
            27 => '0.5',
            28 => '0.5',
            29 => '0.5',
            30 => '0.5',
            31 => '0.5',
            32 => '0.5',
            33 => '0.5',
            34 => '0.5',
            35 => '0.5',
            36 => '0.5',
            37 => '0.5',
            38 => '0.5',
            39 => '0.5',
            40 => '0.5',
            41 => '0.5',
            42 => '0.25',
            43 => '0.25',
            44 => '0.25',
            45 => '0.25',
            46 => '0.25',
            47 => '0.25',
            48 => '0.25',
            49 => '0.25',
            50 => '0.25',
            51 => '0.25',
        ];
        if (count($rates) !== 51 || array_keys($rates) !== range(1, 51)) { throw new RuntimeException('Invalid level count'); }
        $total = '0.000000000';
        foreach ($rates as $rate) { $total = bcadd($total, $rate, 9); }
        if (bccomp($total, '100.000000000', 9) !== 0) { throw new RuntimeException('Rates must total 100%'); }
        DB::transaction(function () use ($rates) {
            $investment = DB::table('investment_distribution_settings')->where('id', 1)->lockForUpdate()->first();
            $generation = DB::table('generation_pool_settings')->where('id', 1)->lockForUpdate()->first();
            if (!$investment || !$generation || $investment->is_enabled || $generation->generation_enabled) { throw new RuntimeException('Financial switches must be OFF'); }
            foreach (['investment_distribution_level_rates','level_settings'] as $table) {
                $levels = DB::table($table)->orderBy('level_number')->lockForUpdate()->pluck('level_number')->map(fn ($n) => (int)$n)->all();
                if ($levels !== range(1,51)) { throw new RuntimeException('Missing levels in ' . $table); }
                foreach ($rates as $level => $rate) {
                    DB::table($table)->where('level_number', $level)->update(['commission_percentage' => $rate, 'updated_at' => now()]);
                }
            }
        });
    }
    public function down(): void { throw new RuntimeException('Financial rate rollback disabled'); }
};