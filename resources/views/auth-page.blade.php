<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leobot Software - System Login & Signup</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background: #090d16; color: #fff; padding: 5px 20px; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100vh; }
        .wrapper { background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(15px); border: 1px solid rgba(255,255,255,0.1); padding: 25px; border-radius: 20px; width: 100%; max-width: 460px; box-shadow: 0 20px 40px rgba(0,0,0,0.5); margin-bottom: 25px; }
        h2 { color: #38bdf8; margin-bottom: 15px; text-align: center; font-size: 22px; border-bottom: 1px dashed rgba(56, 189, 240, 0.3); padding-bottom: 8px; }
        .form-group { margin-bottom: 12px; text-align: left; }
        .form-group label { display: block; margin-bottom: 5px; color: #94a3b8; font-size: 13px; }
        .form-group input { width: 100%; padding: 11px 14px; background: #0f172a; border: 1px solid #334155; border-radius: 8px; color: #fff; font-size: 14px; }
        .form-group input:focus { outline: none; border-color: #38bdf8; box-shadow: 0 0 8px rgba(56, 189, 240, 0.2); }
        .action-btn { width: 100%; padding: 13px; background: linear-gradient(135deg, #38bdf8, #4f46e5); border: none; border-radius: 8px; color: #fff; font-weight: 700; cursor: pointer; font-size: 15px; margin-top: 8px; transition: all 0.3s ease; }
        .action-btn:hover { box-shadow: 0 0 15px rgba(56, 189, 240, 0.4); }
        .form-row { display: flex; gap: 10px; }
    </style>
</head>
<body>

    <h1 style="color: #38bdf8; margin-top: 20px; margin-bottom: 5px; font-size: 32px;">Leobot MLM</h1>
    <p style="color: #64748b; margin-bottom: 25px; font-size: 14px; font-weight: 600;">⚡ 51-Level Professional Generation Engine ⚡</p>

    <!-- 🟢 भाग A: लॉगिन फॉर्म (सीधे आपके लोकल रूट पर प्रोसेस होगा) -->
    <div class="wrapper">
        <h2>🔐 यूज़र लॉगिन (Login)</h2>
        <form method="POST" action="{{ url('/login') }}">
            @csrf
            <div class="form-group">
                <label>यूज़रनेम (Username)</label>
                <input type="text" name="username" placeholder="ADMIN या अपना यूज़रनेम" required>
            </div>
            <div class="form-group">
                <label>पासवर्ड (Password)</label>
                <input type="password" name="password" placeholder="पासवर्ड डालें" required>
            </div>
            <button type="submit" class="action-btn">लॉगिन करें 🚀</button>
        </form>
    </div>

    <!-- 🔵 भाग B: नया रजिस्ट्रेशन फॉर्म (पूरी तरह बाहर खुला हुआ, नो बटन अड़ंगा) -->
    <div class="wrapper">
        <h2>📝 नया रजिस्ट्रेशन फॉर्म (Signup)</h2>
        <form method="POST" action="{{ url('/register') }}">
            @csrf
            
            <div class="form-group">
                <label>स्पॉन्सर आईडी (Sponsor ID)</label>
                <input type="text" name="sponsor_id" id="reg_sponsor_id" value="{{ request()->get('ref') }}" placeholder="Sponsor ID दर्ज करें" required>
            </div>

            <div class="form-group">
                <label>स्पॉन्सर का नाम (Sponsor Name)</label>
                <input type="text" id="sponsor_name" readonly placeholder="Sponsor Name अपने आप आएगा" style="background: #1e293b; color: #94a3b8;">
            </div>

            <div class="form-group">
                <label>पूरा नाम (Name)</label>
                <input type="text" name="name" placeholder="अपना पूरा नाम लिखें" required>
            </div>
            
            <div class="form-group">
                <label>ईमेल आईडी (Email ID)</label>
                <input type="email" name="email" placeholder="Email दर्ज करें" required>
            </div>

            <div class="form-group">
                <label>मोबाइल नंबर (mobile No)</label>
                <input type="text" name="mobile" placeholder="10 अंकों का मोबाइल नंबर" required>
            </div>

            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label>पासवर्ड (Password)</label>
                    <input type="password" name="password" placeholder="पासवर्ड बनाएं" required>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>पुनः पासवर्ड (Confirm)</label>
                    <input type="password" name="password_confirmation" placeholder="दोबारा डालें" required>
                </div>
            </div>

            <button type="submit" class="action-btn">सफलतापूर्वक रजिस्टर करें 🎉</button>
        </form>
    </div>

    <!-- स्पॉन्सर नाम ऑटो-लुकअप लाइव स्क्रिप्ट -->
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        document.getElementById('reg_sponsor_id')?.addEventListener('blur', function() {
            let sponsorId = this.value;
            if(sponsorId) {
                fetch(`/api/get-sponsor-name/${sponsorId}`)
                    .then(res => res.json())
                    .then(data => {
                        const sponsorInput = document.getElementById('sponsor_name');
                        if(sponsorInput) sponsorInput.value = data.name || 'गलत स्पॉन्सर आईडी!';
                    })
                    .catch(() => {
                        const sponsorInput = document.getElementById('sponsor_name');
                        if(sponsorInput) sponsorInput.value = 'स्पॉन्सर नहीं मिला!';
                    });
            }
        });
    });
    </script>
</body>
</html>
