<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class TreeService
{
    /**
     * 1. पुराना फंक्शन: टीम काउंट अपडेट करने के लिए
     */
    public static function updateUplineTree($userId, $type = 'register', $amount = 0)
    {
        $user = User::find($userId);
        if (!$user || !$user->sponsor_id) { return; }

        $currentSponsorId = $user->sponsor_id;
        $currentLevel = 1;

        while ($currentSponsorId && $currentLevel <= 41) {
            $upline = User::where('username', $currentSponsorId)->first();
            if (!$upline) { break; }

            DB::table('member_teams')->updateOrInsert(
                ['user_id' => $upline->id, 'level' => $currentLevel],
                ['updated_at' => now()]
            );

            if ($type === 'register') {
                DB::table('member_teams')->where(['user_id' => $upline->id, 'level' => $currentLevel])->increment('inactive_count');
            }

            if ($type === 'activation' && $amount > 0) {
                DB::table('member_teams')->where(['user_id' => $upline->id, 'level' => $currentLevel])->increment('active_count');
                DB::table('member_teams')->where(['user_id' => $upline->id, 'level' => $currentLevel])->increment('total_business', $amount);
            }

            $currentSponsorId = $upline->sponsor_id;
            $currentLevel++;
        }
    }

    /**
     * 2. नया मास्टर फंक्शन: 41 लेवल्स में कमीशन बांटना + 3X कैपिंग रूल लगाना
     */
    public static function distributeGenerationIncome($fromUserId, $totalAmount, $incomeType = 'level_income')
    {
        $childUser = User::find($fromUserId);
        if (!$childUser || !$childUser->sponsor_id) { return; }

        $currentSponsorId = $childUser->sponsor_id;
        $currentLevel = 1;

        // 41 लेवल्स का तय प्रतिशत स्लैब (उदाहरण के लिए: L1=10%, L2=5%, L3=3%, L4-L10=1%, L11-L41=0.5%)
        // आप अपने प्लान के मुताबिक इन नंबर्स को बदल भी सकते हैं
        $distributionRates = [
            1 => 10.00, 2 => 5.00, 3 => 3.00,
            4 => 1.00,  5 => 1.00, 6 => 1.00, 7 => 1.00, 8 => 1.00, 9 => 1.00, 10 => 1.00
        ];
        // लेवल 11 से 41 के लिए ऑटोमैटिक 0.5% सेट करना
        for ($i = 11; $i <= 41; $i++) {
            $distributionRates[$i] = 0.50;
        }

        // लूप चलाकर ऊपर 41 अपलाइनों को पैसा बांटना
        while ($currentSponsorId && $currentLevel <= 41) {
            $upline = User::where('username', $currentSponsorId)->first();
            if (!$upline) { break; }

            // केवल एक्टिव अपलाइन को ही इनकम मिलेगी
            if ($upline->status === 'active') {
                
                // इस लेवल का कमीशन कैलकुलेट करें
                $rate = $distributionRates[$currentLevel] ?? 0.50;
                $commission = ($totalAmount * $rate) / 100;

                // 3X कैपिंग सुरक्षा जांच (Total Investment का 3 गुना अधिकतम कमा सकते हैं)
                $maxEarningLimit = $upline->total_investment * 3;
                
                if ($upline->lifetime_income < $maxEarningLimit) {
                    
                    // अगर लिमिट पार होने वाली है, तो केवल बचा हुआ अमाउंट ही दें (Capping Cut)
                    if (($upline->lifetime_income + $commission) > $maxEarningLimit) {
                        $commission = $maxEarningLimit - $upline->lifetime_income;
                    }

                    if ($commission > 0) {
                        // डेटाबेस में बैलेंस प्लस करें
                        DB::transaction(function () use ($upline, $childUser, $currentLevel, $commission, $incomeType) {
                            $upline->increment('available_balance', $commission);
                            $upline->increment('lifetime_income', $commission);

                            // कमीशन का लाइव लॉग रिकॉर्ड करें
                            DB::table('income_logs')->insert([
                                'user_id'       => $upline->id,
                                'from_user_id'  => $childUser->id,
                                'level'         => $currentLevel,
                                'amount'        => $commission,
                                'income_type'   => $incomeType,
                                'created_at'    => now(),
                                'updated_at'    => now()
                            ]);
                        });
                    }
                }
            }

            // चैन में अगले ऊपर के अपलाइन पर जाने के लिए
            $currentSponsorId = $upline->sponsor_id;
            $currentLevel++;
        }
    }
}
