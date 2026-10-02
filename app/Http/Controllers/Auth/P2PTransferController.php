<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class P2PTransferController extends Controller
{
    public function transfer(Request $request)
    {
        // 1. इनपुट डेटा को वैलिडेट करें
        $request->validate([
            'receiver_username' => 'required|string|exists:users,username',
            'amount'            => 'required|numeric|min:1', // न्यूनतम $1 ट्रांसफर
            'security_pin'      => 'required|numeric|digits:6',
        ]);

        // 2. ट्रांसफर करने वाले (Sender) की जानकारी (टोकन से)
        // टेस्टिंग के लिए सेंडर को मैन्युअली फिक्स किया गया है
// डेटाबेस में जो भी पहला यूज़र मौजूद है, उसे ऑटोमैटिक सेंडर मान लें
// लाइव मोड: केवल लॉगिन करने वाले यूज़र के खाते से ही बैलेंस कटेगा
$sender = $request->user();


        
        // 3. पैसे पाने वाले (Receiver) की जानकारी खोजना
        $receiver = User::where('username', $request->receiver_username)->first();

        // 4. सुरक्षा जांच: खुद को ट्रांसफर नहीं कर सकते
        if ($sender->username === $receiver->username) {
            return response()->json(['success' => false, 'message' => 'You cannot transfer funds to yourself.'], 400);
        }

        // 5. सुरक्षा जांच: सिक्योरिटी पिन वेरिफाई करना
        if (!Hash::check($request->security_pin, $sender->security_pin)) {
            return response()->json(['success' => false, 'message' => 'Invalid Security PIN.'], 401);
        }

        // 6. सुरक्षा जांच: पर्याप्त बैलेंस है या नहीं
        if ($sender->available_balance < $request->amount) {
            return response()->json(['success' => false, 'message' => 'Insufficient balance in Available Wallet.'], 400);
        }

        // 7. डेटाबेस ट्रांजेक्शन (ताकि सर्वर क्रैश होने पर भी फंड गायब न हो - ACID Property)
        DB::transaction(function () use ($sender, $receiver, $request) {
            // भेजने वाले के Available Wallet से बैलेंस माइनस करें
            $sender->decrement('available_balance', $request->amount);
            
            // पाने वाले के Activation Wallet में बैलेंस प्लस करें
            $receiver->increment('activation_balance', $request->amount);
        });

        return response()->json([
            'success' => true,
            'message' => 'P2P Fund Transfer successful!',
            'data' => [
                'sender_remaining_balance' => $sender->available_balance,
                'receiver_username'        => $receiver->username
            ]
        ], 200);
    }
}
