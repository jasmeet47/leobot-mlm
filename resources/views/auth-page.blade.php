<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leobot Software - Advanced Login & Registration</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background: #090d16; min-height: 100vh; display: flex; justify-content: center; align-items: center; color: #fff; overflow-x: hidden; position: relative; }
        .wrapper { background: rgba(15, 23, 42, 0.5); backdrop-filter: blur(15px); border: 1px solid rgba(255,255,255,0.1); padding: 30px; border-radius: 20px; width: 480px; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.5); }
        h1 { color: #38bdf8; margin-bottom: 5px; font-size: 28px; }
        .subtitle { color: #64748b; font-size: 13px; margin-bottom: 20px; }
        .tab-nav { display: flex; background: rgba(0, 0, 0, 0.3); padding: 5px; border-radius: 10px; margin-bottom: 20px; border: 1px solid rgba(255,255,255,0.05); }
        .tab-btn { flex: 1; padding: 12px; background: none; border: none; color: #94a3b8; font-size: 14px; font-weight: 600; cursor: pointer; border-radius: 6px; transition: all 0.3s ease; }
        .tab-btn.active { background: linear-gradient(135deg, #38bdf8 0%, #4f46e5 100%); color: #ffffff; box-shadow: 0 4px 15px rgba(56, 189, 240, 0.2); }
        .forms-window { width: 100%; overflow: hidden; position: relative; }
        .forms-slider { display: flex; width: 200%; transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
        .form-section { width: 50%; padding: 0 5px; }
        .form-group { margin-bottom: 14px; text-align: left; }
        .form-group label { display: block; margin-bottom: 6px; color: #94a3b8; font-size: 12px; }
        .form-group input { width: 100%; padding: 11px 14px; background: #0f172a; border: 1px solid #334155; border-radius: 8px; color: #fff; font-size: 13px; }
        .form-group input:focus { outline: none; border-color: #38bdf8; box-shadow: 0 0 8px rgba(56, 189, 240, 0.2); }
        .form-row { display: flex; gap: 10px; margin-bottom: 14px; }
        .forgot-link { display: block; text-align: right; color: #38bdf8; font-size: 12px; text-decoration: none; margin-top: 6px; cursor: pointer; }
        .captcha-container { display: flex; align-items: center; gap: 12px; margin-top: 10px; padding: 10px; background: rgba(0, 0, 0, 0.2); border-radius: 8px; border: 1px dashed #334155; }
        .captcha-box { background: #0f172a; color: #38bdf8; font-size: 16px; font-weight: 700; padding: 6px 14px; border-radius: 6px; user-select: none; }
        .action-btn { width: 100%; padding: 13px; background: linear-gradient(135deg, #38bdf8, #4f46e5); border: none; border-radius: 8px; color: #fff; font-weight: 700; cursor: pointer; font-size: 15px; margin-top: 18px; }
        .action-btn:hover { box-shadow: 0 0 15px rgba(56, 189, 240, 0.4); }
    </style>
</head>
<body>
    <div class="wrapper">
        <h1>Leobot MLM</h1>
        <p class="subtitle">51-Level Generation Platform</p>
        
        <!-- मुख्य नेविगेशन टैब्स -->
        <div class="tab-nav">
            <button id="btn-login" class="tab-btn active">लॉगिन (Login)</button>
            <button id="btn-register" class="tab-btn">रजिस्ट्रेशन (Register)</button>
        </div>
        
        <div class="forms-window">
            <div id="main-slider" class="forms-slider">
                <!-- 1. लॉगिन फॉर्म सेक्शन -->
                <div class="form-section">
                    <form id="loginForm" method="POST" action="https://onrender.com">
                        @csrf
                        <div class="form-group">
                            <label>यूज़रनेम (Username)</label>
                            <input type="text" name="username" placeholder="ADMIN या अपना यूज़रनेम" required>
                        </div>
                        <div class="form-group">
                            <label>पासवर्ड (Password)</label>
                            <input type="password" name="password" placeholder="पासवर्ड डालें" required>
                            <a class="forgot-link" href="#">Forgot Password?</a>
                        </div>
                        <button type="submit" class="action-btn">लॉगिन करें 🚀</button>
                    </form>
                </div>
                
                <!-- 2. रजिस्ट्रेशन फॉर्म सेक्शन -->
                <div class="form-section">
                    <form id="registerForm" method="POST" action="https://onrender.com">
                        @csrf

                        <!-- स्पॉन्सर आईडी फ़ील्ड -->
                        <div class="form-group" id="group-sponsor-id">
                            <label>% स्पॉन्सर आईडी (Sponsor ID)</label>
                            <input type="text" name="sponsor_id" id="reg_sponsor_id" value="{{ request()->get('ref') }}" placeholder="Sponsor ID दर्ज करें" required>
                        </div>

                        <!-- स्पॉन्सर नाम फ़ील्ड -->
                        <div class="form-group" id="group-sponsor-name">
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
                            <div class="form-group">
                                <label>पासवर्ड (Password)</label>
                                <input type="password" name="password" placeholder="पासवर्ड बनाएं" required>
                            </div>
                            <div class="form-group">
                                <label>पुनः पासवर्ड (Re-enter Password)</label>
                                <input type="password" name="password_confirmation" placeholder="पासवर्ड दोबारा डालें" required>
                            </div>
                        </div>

                        <!-- सुरक्षा जांच (कैप्चा बॉक्स) -->
                        <div class="form-group" id="group-google-captcha">
                            <div class="captcha-container">
                                <div class="captcha-box" id="captcha-text">7 + 5</div>
                                <input type="text" id="captcha-input" placeholder="जोड़ लिखें" required>
                            </div>
                        </div>

                        <button type="submit" class="action-btn">रजिस्टर करें 🎉</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- 🌟 बिल्कुल अचूक और एरर-फ़्री स्विचिंग स्क्रिप्ट लॉजिक -->
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const loginTab = document.getElementById('btn-login');
        const registerTab = document.getElementById('btn-register');
        const mainSlider = document.getElementById('main-slider');

        // रजिस्ट्रेशन बटन दबाने पर फॉर्म को खिसकाने का पक्का लॉजिक
        if (registerTab) {
            registerTab.addEventListener('click', function() {
                if(loginTab) loginTab.classList.remove('active');
                registerTab.classList.add('active');
                if(mainSlider) mainSlider.style.transform = 'translateX(-50%)';
            });
        }

        // लॉगिन बटन दबाने पर वापस लाने का लॉजिक
        if (loginTab) {
            loginTab.addEventListener('click', function() {
                if(registerTab) registerTab.classList.remove('active');
                loginTab.classList.add('active');
                if(mainSlider) mainSlider.style.transform = 'translateX(0%)';
            });
        }

        // स्पॉन्सर नेम लाइव लुकअप स्क्रिप्ट
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
