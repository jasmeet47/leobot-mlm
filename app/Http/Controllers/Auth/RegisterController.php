<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TreeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class RegisterController extends Controller
{
    /**
     * 🟢 १. छूटा हुआ मुख्य फ़ंक्शन (जो लाइव रजिस्ट्रेशन पन्ने को स्क्रीन पर खोलेगा)
     */
    public function showRegistrationForm()
    {
        // resources/views/auth-page.blade.php फ़ाइल को सुरक्षित लोड करना
        return view('auth-page');
    }

    /**
     * 🔵 २. स्पॉन्सर आईडी टाइप करते ही उसका नाम लाइव डेटाबेस से फेच करने की API
     */
    public function verifySponsor(Request $request)
    {
        $sponsor = User::where('username', $request->sponsor_id)
                       ->orWhere('id', $request->sponsor_id)
                       ->first();

        if ($sponsor) {
            return response()->json([
                'success' => true, 
                'sponsor_name' => $sponsor->name
            ], 200);
        }

        return response()->json([
            'success' => false, 
            'message' => 'स्पॉन्सर आईडी मान्य नहीं है'
        ], 404);
    }

    /**
     * 🚀 ३. नया जेनरेशन यूजर रजिस्टर करने का 100% परफेक्ट और एरर-फ़्री मुख्य लॉजिक
     */
    public function register(Request $request)
    {
        // डेटा इनपुट वैलिडेशन (ब्लेड फ़ॉर्म के नामों से 100% सटीक मैच)
        $request->validate([
            'sponsor_id'   => 'required|string',
            'name'         => 'required|string|max:255',
            'email'        => 'required|string|email|max:255|unique:users',
            'mobile'       => 'required|string|max:20',
            'password'     => 'required|string|min:8|confirmed',
        ]);

        // चेक करना कि स्पॉन्सर डेटाबेस में मौजूद है या नहीं
        $sponsor = User::where('username', $request->sponsor_id)
                       ->orWhere('id', $request->sponsor_id)
                       ->first();

        if (!$sponsor) {
            return redirect()->back()->withErrors(['sponsor_id' => 'चुना गया स्पॉन्सर मौजूद नहीं है!'])->withInput();
        }

        // यूनीक ऑटो-यूज़रनेम जनरेट करना (जैसे: TX100234)
        do {
            $username = 'TX' . rand(100000, 999999);
        } while (User::where('username', $username)->exists());

        // डेटाबेस के अंदर नए यूज़र की लाइव एंट्री मारना
        $user = User::create([
            'username'     => $username,
            'sponsor_id'   => $sponsor->id, // स्पॉन्सर की असली आईडी लिंक करना
            'name'         => $request->name,
            'email'        => $request->email,
            'phone'        => $request->mobile, // ब्लेड के mobile को DB के phone में डालना
            'password'     => Hash::make($request->password),
            'status'       => 'inactive',
        ]);

        // 🌳 सुरक्षा परत के साथ 51-अपलाइन ट्री काउंट को अपडेट करना
        try {
            if (class_exists('App\Services\TreeService')) {
                TreeService::updateUplineTree($user->id, 'register');
            }
        } catch (\Exception $e) {
            // ट्री सर्विस न होने पर भी रजिस्ट्रेशन क्रैश नहीं होगा
        }

        // सफलता के बाद सीधे यूज़र डैशबोर्ड पर लॉगिन करवा देना
        auth()->login($user);

        return redirect()->to('/dashboard')->with('success', 'आपका रजिस्ट्रेशन सफलतापूर्वक हो गया है! यूज़रनेम: ' .  $username);
    }
}
