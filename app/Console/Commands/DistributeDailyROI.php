<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DistributeDailyROI extends Command
{
    protected $signature = 'roi:distribute';
    protected $description = 'सोमवार से शुक्रवार के बीच 0.2% डेली ROI ऑटोमैटिक बांटना';

    public function handle()
    {
        $today = Carbon::now();

        if ($today->isWeekend()) {
            $this->info('आज वीकends है, ट्रेडिंग मार्केट बंद होने के कारण ROI नहीं बंटेगी।');
            return 0;
        }

        $todayDate = $today->toDateString();

        $users = User::where('status', 'active')
                     ->where('total_investment', '>', 0)
                     ->get();

        if ($users->isEmpty()) {
            $this->info('कोई भी एक्टिव इन्वेस्टेड यूजर नहीं मिला।');
            return 0;
        }

        $count = 0;

        foreach ($users as $user) {
            $alreadyPaid = DB::table('roi_logs')
                             ->where('user_id', $user->id)
                             ->where('date', $todayDate)
                             ->exists();

            if ($alreadyPaid) {
                continue;
            }

            $roiAmount = ($user->total_investment * 0.2) / 100;

            DB::transaction(function () use ($user, $roiAmount, $todayDate) {
                // ROI क्रेडिट होते ही ऊपर के 41 अपलाइनों को जेनरेशन इनकम बांटना
\App\Services\TreeService::distributeGenerationIncome($user->id, $roiAmount, 'roi_level_income');

                $user->increment('available_balance', $roiAmount);
                $user->increment('lifetime_income', $roiAmount);
                $user->increment('daily_income_total', $roiAmount);

                DB::table('roi_logs')->insert([
                    'user_id' => $user->id,
                    'investment_amount' => $user->total_investment,
                    'roi_amount' => $roiAmount,
                    'percentage' => 0.20,
                    'date' => $todayDate,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            });

            $count++;
        }

        $this->info("सफलतापूर्वक {$count} यूज़र्स के वॉलेट में 0.2% Daily ROI क्रेडिट कर दी गई है।");
        return 0;
    }
}
