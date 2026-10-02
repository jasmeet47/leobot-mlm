<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Services\TreeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ActivationController extends Controller
{
    public function activate(Request $request)
    {
        // 1. इनपुट डेटा को वैलिडेट करें
        $request->validate([
            'username'     => 'required|string|exists:users,username',
            'package_amount'=> 'required|numeric|min:1', // न्यूनतम $1 का पैकेज
            'security_pin' => 'required|numeric|digits:6',
        ]);

        // 2. अभी हम बिना टोकन के टेस्टिंग के लिए डेटाबेस के पहले यूज़र (सेंडर) को मान लेते हैं
        $sender = User::first(); 

        // सुरक्षा जांच: सीधा पिन मैचिंग (सुरक्षित और एरर-फ्री)
if ($sender->security_pin !== $request->security_pin) {
    return response()->json(['success' => false, 'message' => 'गलत सिक्योरिटी पिन!'], 401, [], JSON_UNESCAPED_UNICODE);
}

        // 4. सुरक्षा जांच: एक्टिवेशन वॉलेट में पर्याप्त बैलेंस है या नहीं
        if ($sender->activation_balance < $request->package_amount) {
            return response()->json(['success' => false, 'message' => 'एक्टिवेशन वॉलेट में पर्याप्त बैलेंस नहीं है!'], 400, [], JSON_UNESCAPED_UNICODE);
        }

        // 5. जिस यूज़र की आईडी एक्टिवेट करनी है (टारगेट यूज़र)
        $targetUser = User::where('username', $request->username)->first();

        // 6. सुरक्षा जांच: वह पहले से एक्टिव तो नहीं है?
        if ($targetUser->status === 'active') {
            return response()->json(['success' => false, 'message' => 'यह यूजर आईडी पहले से ही एक्टिव है!'], 400, [], JSON_UNESCAPED_UNICODE);
        }

        // 7. डेटाबेस ट्रांजेक्शन (सुरक्षित फंड डिडक्शन और स्टेटस अपडेट)
        DB::transaction(function () use ($sender, $targetUser, $request) {
            // सेंडर के एक्टिवेशन वॉलेट से फंड माइनस करें
            $sender->decrement('activation_balance', $request->package_amount);

            // टारगेट यूजर का स्टेटस एक्टिव करें और इन्वेस्टमेंट प्लस करें
            $targetUser->update([
                'status' => 'active',
                'total_investment' => $targetUser->total_investment + $request->package_amount
            ]);

            // 41 लेवल्स के अपलाइन ट्री काउंटर्स को रीयल-टाइम में एक्टिवेट करना
            TreeService::updateUplineTree($targetUser->id, 'activation', $request->package_amount);
        });
// आईडी एक्टिवेट होते ही ऊपर के 41 अपलाइनों को तुरंत लेवल जनरेशन इनकम बांटना
TreeService::distributeGenerationIncome($targetUser->id, $request->package_amount, 'level_income');

        return response()->json([
            'success' => true,
            'message' => 'यूज़र आईडी सफलतापूर्वक एक्टिवेट हो गई है!',
            'data' => [
                'activated_user' => $targetUser->username,
                'package_applied'=> $request->package_amount,
                'remaining_activation_balance' => $sender->activation_balance
            ]
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
