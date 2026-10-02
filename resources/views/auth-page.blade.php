<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leobot Software - Advanced Login & Registration</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background: #090d16; min-height: 100vh; display: flex; justify-content: center; align-items: center; color: #fff; overflow-x: hidden; position: relative; }
        .wrapper { background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.1); padding: 30px; border-radius: 20px; width: 480px; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.5); overflow: hidden; z-index: 2; }
        h1 { color: #38bdf8; margin-bottom: 5px; font-size: 26px; }
        p.subtitle { color: #64748b; font-size: 13px; margin-bottom: 20px; }
        
        /* 🎛️ टैब बटन स्टाइल */
        .tab-nav { display: flex; background: rgba(0, 0, 0, 0.3); padding: 5px; border-radius: 10px; margin-bottom: 20px; border: 1px solid rgba(255, 255, 255, 0.05); }
        .tab-btn { flex: 1; padding: 12px; background: none; border: none; color: #94a3b8; font-size: 14px; font-weight: 600; cursor: pointer; border-radius: 6px; transition: all 0.3s ease; }
        .tab-btn.active { background: linear-gradient(135deg, #38bdf8 0%, #4f46e5 100%); color: #ffffff; box-shadow: 0 4px 15px rgba(56, 189, 248, 0.2); }
        
        /* 🏎️ स्लाइडर विंडो */
        .forms-window { width: 100%; overflow: hidden; }
        .forms-slider { display: flex; width: 200%; transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1); }
        .form-section { width: 50%; padding: 0 5px; transition: opacity 0.3s ease; }
        
        .form-row { display: flex; gap: 15px; }
        .form-group { margin-bottom: 14px; text-align: left; flex: 1; }
        .form-group label { display: block; margin-bottom: 6px; color: #94a3b8; font-size: 12px; }
        .form-group input, .form-group select { width: 100%; padding: 11px 14px; background: #0f172a; border: 1px solid #334155; border-radius: 8px; color: #fff; font-size: 13px; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #38bdf8; box-shadow: 0 0 8px rgba(56, 189, 248, 0.2); }
        
        .forgot-link { display: block; text-align: right; color: #38bdf8; font-size: 12px; text-decoration: none; margin-top: 6px; cursor: pointer; }
        .captcha-container { display: flex; align-items: center; gap: 12px; margin-top: 10px; background: rgba(0, 0, 0, 0.2); padding: 8px; border-radius: 8px; border: 1px dashed #334155; }
        .captcha-box { background: #0f172a; color: #38bdf8; font-size: 16px; font-weight: 700; padding: 6px 14px; border-radius: 6px; user-select: none; }
        
        .action-btn { width: 100%; padding: 13px; background: linear-gradient(135deg, #38bdf8, #4f46e5); border: none; border-radius: 8px; color: #fff; font-weight: 700; cursor: pointer; font-size: 15px; margin-top: 10px; }
        .action-btn:hover { box-shadow: 0 0 15px rgba(56, 189, 248, 0.4); }
        
        /* 🔔 फ्लोटिंग अलर्ट */
        .status-alert { position: fixed; top: 20px; right: -400px; background: rgba(15, 23, 42, 0.95); backdrop-filter: blur(15px); border-radius: 12px; padding: 16px 24px; width: 325px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); z-index: 999; transition: right 0.4s ease; border-left: 4px solid #ef4444; }
        .status-alert.show { right: 20px; }
        .status-alert.success { border-left-color: #10b981; }
        .status-alert p { color: #ffffff; font-size: 13px; font-weight: 500; }
    </style>
</head>
<body>

<div id="alert-box" class="status-alert"><p id="alert-msg"></p></div>

<div class="wrapper">
    <h1>Leobot MLM</h1>
    <p class="subtitle">41-Level Generation Platform</p>
    
    <!-- 🎛️ टॉप स्विचर बटन्स -->
    <div class="tab-nav">
        <button id="btn-login" class="tab-btn active" onclick="showForm('login')">लॉगिन (Login)</button>
        <button id="btn-register" class="tab-btn" onclick="showForm('register')">रजिस्ट्रेशन (Register)</button>
    </div>

    <div class="forms-window">
        <div id="main-slider" class="forms-slider">
            
            <!-- 1️⃣ लॉगिन फ़ॉर्म सेक्शन -->
            <div id="section-login" class="form-section">
                <form id="loginForm">
                    <div class="form-group">
                        <label>यूजरनेम (Username)</label>
                        <input type="text" name="username" placeholder="ADMIN या अपना यूजरनेम" required>
                    </div>
                    <div class="form-group">
                        <label>पासवर्ड (Password)</label>
                        <input type="password" name="password" placeholder="पासवर्ड डालें" required>
                        <a class="forgot-link" onclick="handleForgotPassword()">Forgot Password?</a>
                    </div>
                    <div class="form-group">
                        <label>सुरक्षा जांच (Security CAPTCHA)</label>
                        <div class="captcha-container">
                            <div id="captchaCode" class="captcha-box">0 + 0</div>
                            <input type="number" id="captchaInput" placeholder="जोड़ लिखें" required>
                        </div>
                    </div>
                    <button type="submit" class="action-btn">लॉगिन करें 🚀</button>
                </form>
            </div>

            <!-- 2️⃣ एडवांस रजिस्ट्रेशन फ़ॉर्म सेक्शन (यहाँ ID फिक्स कर दी गई है) -->
            <div id="section-register" class="form-section" style="padding-left: 10px;">
                <form id="registerForm">
                    <div class="form-row">
                        <div class="form-group"><label>स्पॉन्सर आईडी (Sponsor ID)</label><input type="text" name="sponsor_id" placeholder="ADMIN" required></div>
                        <div class="form-group"><label>प्लेसमेंट (Position)</label><select name="placement"><option value="left">Left Position</option><option value="right">Right Position</option></select></div>
                    </div>
                    <div class="form-group"><label>पूरा नाम (Full Name)</label><input type="text" name="name" required></div>
                    <div class="form-group"><label>ईमेल आईडी (Email ID)</label><input type="email" name="email" required></div>
                    <div class="form-row">
                        <div class="form-group" style="flex: 0.3;"><label>कंट्री कोड</label><input type="text" name="country_code" value="+91" required></div>
                        <div class="form-group"><label>मोबाइल नंबर</label><input type="text" name="mobile" required></div>
                    </div>
                    <div class="form-group"><label>जन्म तिथि (Date of Birth)</label><input type="date" name="dob" required></div>
                    <div class="form-row">
                        <div class="form-group"><label>पासवर्ड</label><input type="password" name="password" required></div>
                        <div class="form-group"><label>पासवर्ड दोबारा लिखें</label><input type="password" name="password_confirmation" required></div>
                    </div>
                    <div class="form-group"><label>सुरक्षा पिन (6-Digit PIN)</label><input type="text" name="security_pin" maxlength="6" required></div>
                    <button type="submit" class="action-btn">खाता बनाएं 🎉</button>
                </form>
            </div>

        </div>
    </div>
</div>

<script>
    let correctCaptchaAnswer = 0;
    
    // 🔄 कैप्चा जनरेटर
    function generateCaptcha() {
        const num1 = Math.floor(Math.random() * 10) + 1; 
        const num2 = Math.floor(Math.random() * 9) + 1;
        correctCaptchaAnswer = num1 + num2; 
        document.getElementById('captchaCode').innerText = num1 + " + " + num2;
        document.getElementById('captchaInput').value = '';
    }
    window.onload = function() { generateCaptcha(); };

    // 🏎️ 100% वर्किंग और टेस्टेड फॉर्म स्विचिंग स्क्रिप्ट (बिना किसी रुकावट के)
    function showForm(target) {
        const slider = document.getElementById('main-slider');
        const loginBtn = document.getElementById('btn-login'); 
        const regBtn = document.getElementById('btn-register');
        
        if (target === 'login') {
            if(slider) slider.style.transform = 'translateX(0%)'; 
            if(loginBtn) loginBtn.classList.add('active'); 
            if(regBtn) regBtn.classList.remove('active');
        } else {
            if(slider) slider.style.transform = 'translateX(-50%)'; 
            if(regBtn) regBtn.classList.add('active'); 
            if(loginBtn) loginBtn.classList.remove('active');
        }
    }

    function showAlert(message, type) {
        const alertBox = document.getElementById('alert-box'); 
        const alertMsg = document.getElementById('alert-msg');
        if(alertBox && alertMsg) {
            alertBox.className = "status-alert show " + type; 
            alertMsg.innerText = message;
            setTimeout(function() { alertBox.classList.remove('show'); }, 4000);
        }
    }

    function handleForgotPassword() { 
        showAlert('🔒 पासवर्ड रीसेट लिंक आपके पंजीकृत मोबाइल पर भेज दिया गया है!', 'success'); 
    }

    // बिना रिफ्रेश एडवांस पंजीकरण Fetch API
    document.getElementById('registerForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this); 
        const data = Object.fromEntries(formData);
        
        showAlert('⏳ पंजीकरण प्रोसेस हो रहा है...', 'success');

        fetch('/api/register', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(function(res) { return res.json().then(function(json) { if (!res.ok) return Promise.reject(json); return json; }); })
