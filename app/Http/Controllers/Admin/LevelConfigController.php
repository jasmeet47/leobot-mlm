<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LevelConfigController extends Controller
{
    /**
     * १. एडमिन पैनल पर ५१ लेवल्स का ग्रिड पेज (ब्लेड व्यू) लोड करना
     */
    public function index()
    {
        // डेटाबेस की 'level_settings' टेबल से 1 से 51 तक का डेटा क्रम (Ascending) में खींचना
        $levels = DB::table('level_settings')->orderBy('level_number', 'asc')->get();
        
        // कल बनाए गए 'admin.settings.level-config' ब्लेड व्यू पर डेटा भेजना
        return view('admin.settings.level-config', compact('levels'));
    }

    /**
     * २. एडमिन पैनल पर जब आप प्रतिशत (%) या ऑन/ऑफ स्विच बदलकर सबमिट करेंगे, तो उसे डेटाबेस में सेव करना
     */
    public function update(Request $request)
    {
        // 1 से 51 तक की सभी रोज़ को लूप चलाकर अपडेट करना
        for ($i = 1; $i <= 51; $i++) {
            // चेक करना कि एडमिन ने इस लेवल का स्विच ऑन रखा है या नहीं
            $isActive = $request->has("active_levels.$i") ? true : false;
            
            // यदि स्विच ऑन है तो इनपुट किया गया % उठाएं, बंद है तो सीधे 0.00 कर दें
            $percentage = $isActive ? ($request->input("commission.$i") ?? 0.00) : 0.00;

            // डेटाबेस में लाइव अपडेट मारना
            DB::table('level_settings')->where('level_number', $i)->update([
                'commission_percentage' => $percentage,
                'is_active' => $isActive,
                'updated_at' => now()
            ]);
        }

        return redirect()->back()->with('success', '51-लेवल एडवांस मैट्रिक्स सफलतापूर्वक अपडेट हो गई है! 🚀');
    }

    /**
     * 🌟 ३. मास्टर बैकएंड लूप लॉजिक (कमीशन डिस्ट्रीब्यूशन का असली दिमागी इंजन)
     * जब भी कोई नया यूज़र एक्टिवेट होगा या ट्रेडिंग प्रॉफिट (ROI) जनरेट होगा, 
     * तब इस फंक्शन को सिस्टम द्वारा बैकएंड में ट्रिगर किया जाएगा।
     */
    public function distributeGenerationCommission($currentUserId, $amountEarned)
    {
        // डेटाबेस से 51 लेवल्स के एक्टिव प्रतिशत और ऑन/ऑफ स्टेटस को एरे (Array) में खींचना ताकि सर्वर पर लोड न पड़े
        $levelRules = DB::table('level_settings')->pluck('commission_percentage', 'level_number')->toArray();
        $statusRules = DB::table('level_settings')->pluck('is_active', 'level_number')->toArray();

        // जिस यूज़र से कमीशन बटना शुरू होना है, उसका डेटा डेटाबेस से निकालना
        $currentUser = DB::table('users')->where('id', $currentUserId)->first();
        if (!$currentUser) {
            return; // यदि यूज़र नहीं मिला तो यहीं रुक जाएं
        }

        // चेन (Hierarchy) की शुरुआत करने के लिए इस यूज़र के तुरंत ऊपर वाले变स्पॉन्सर लीडर की आईडी पकड़ना
        $sponsorId = $currentUser->sponsor_id;

        // 🚀 1 से लेकर 51 स्तरों तक पिता-दादा-परदादा की चेन में ऊपर जाने का मास्टर लूप
        for ($currentLevel = 1; $currentLevel <= 51; $currentLevel++) {
            
            // सुरक्षा जांच: यदि ऊपर कोई स्पॉन्सर लीडर नहीं बचा (आईडी खाली या 0 है), तो लूप को तुरंत रोक दें
            if (empty($sponsorId) || $sponsorId == 0) {
                break;
            }

            // जांचें: क्या एडमिन पैनल से यह पर्टिकुलर लेवल नंबर ON (Active) सेट है?
            if (isset($statusRules[$currentLevel]) && $statusRules[$currentLevel] == true) {
                
                // इस स्तर के लिए एडमिन... द्वारा तय किया गया कमीशन प्रतिशत (%) उठाएं
                $commissionPercent = $levelRules[$currentLevel] ?? 0.00;

                // यदि प्रतिशत 0 से ज़्यादा है, तभी कैलकुलेशन करें
                if ($commissionPercent > 0) {
                    // कमिशन राशि की बिल्कुल सटीक गणना (Earned Amount * Level % / 100)
                    $calculatedPayout = ($amountEarned * $commissionPercent) / 100;

                    // लीडर के मुख्य वॉलेट बैलेंस में पैसा लाइव प्लस (Increment) करना
                    DB::table('users')->where('id', $sponsorId)->increment('wallet_balance', $calculatedPayout);

                    // यूज़र की पासबुक/हिस्ट्री के लिए 'commission_logs' टेबल में लाइव एंट्री मारna
                    try {
                        DB::table('commission_logs')->insert([
                            'user_id' => $sponsorId,
                            'from_user_id' => $currentUserId,
                            'level' => $currentLevel,
                            'amount' => $calculatedPayout,
                            'percent' => $commissionPercent,
                            'created_at' => now()
                        ]);
                    } catch (\Exception $e) {
                        // लॉग्स टेबल न होने पर भी सिस्टम स्मूथ चलता रहेगा
                    }
                }
            }

            // 🔄 सबसे महत्वपूर्ण कदम: अगले लेवल (Level + 1) पर जाने के लिए 
            // अब वर्तमान स्पॉन्सर के भी स्पॉन्सर (उनके ऊपर वाले लीडर) की आईडी डेटाबेस से खींचना
            $nextSponsor = DB::table('users')->where('id', $sponsorId)->value('sponsor_id');
            
            // आईडी को अपडेट करना ताकि लूप अगली बार एक पायदान और ऊपर की ओर चले
            $sponsorId = $nextSponsor;
        }
    }
}
