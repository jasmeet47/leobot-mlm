<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leobot Software Hub - Access & Enrollment</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background: #090d16; color: #fff; padding: 40px 20px; display: flex; flex-direction: column; align-items: center; min-height: 100vh; }
        .wrapper { background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(15px); border: 1px solid rgba(255,255,255,0.1); padding: 30px; border-radius: 20px; width: 100%; max-width: 480px; box-shadow: 0 20px 40px rgba(0,0,0,0.5); margin-bottom: 25px; }
        h2 { color: #38bdf8; margin-bottom: 20px; text-align: center; font-size: 22px; border-bottom: 1px dashed rgba(56, 189, 240, 0.3); padding-bottom: 10px; }
        .form-group { margin-bottom: 16px; text-align: left; }
        .form-group label { display: block; margin-bottom: 6px; color: #94a3b8; font-size: 13px; }
        .form-group input { width: 100%; padding: 12px 14px; background: #0f172a; border: 1px solid #334155; border-radius: 8px; color: #fff; font-size: 14px; }
        .form-group input:focus { outline: none; border-color: #38bdf8; box-shadow: 0 0 8px rgba(56, 189, 240, 0.2); }
        .action-btn { width: 100%; padding: 14px; background: linear-gradient(135deg, #38bdf8, #4f46e5); border: none; border-radius: 8px; color: #fff; font-weight: 700; cursor: pointer; font-size: 15px; margin-top: 10px; }
        .success-box { background: rgba(16, 185, 129, 0.15); border: 1px solid #10b981; padding: 15px; border-radius: 10px; margin-bottom: 15px; }
        .link-footer { display: flex; justify-content: space-between; margin-top: 15px; font-size: 13px; }
        .link-footer a { color: #38bdf8; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>

    <h1 style="color: #38bdf8; margin-bottom: 5px; font-size: 36px;">LEOBOT CORE</h1>
    <p style="color: #64748b; margin-bottom: 35px; font-size: 14px; font-weight: 600; letter-spacing: 1px;">⚡ 51-LEVEL EXPONENTIAL GENERATION PLATFORM ⚡</p>

    <!-- SUCCESS REGISTRATION DRAWER AND LINK CLIPBOARD OUTFLOW -->
    @if(session('success_reg'))
    <div class="wrapper" style="border: 2px solid #10b981; background: rgba(15, 23, 42, 0.9);">
        <div class="success-box">
            <h4 style="color: #10b981; margin-bottom: 5px;">🎉 Node Generation Complete!</h4>
            <p style="font-size: 13px;">Allocated Username: <strong>{{ session('new_username') }}</strong></p>
        </div>
        <div class="form-group">
            <label style="color: #10b981; font-weight: bold;">📋 Permanent Marketing Referral Link:</label>
            <input type="text" id="raw_ref_link" value="{{ session('ref_link') }}" readonly style="border-color: #10b981; color: #10b981; font-weight: bold; background: #0f172a;">
        </div>
        <button onclick="triggerClipboardCopy()" class="action-btn" style="background: linear-gradient(135deg, #10b981, #059669);">Copy Link to Clipboard</button>
    </div>
    @endif

    <!-- LOGIN CONTAINER -->
    <div class="wrapper">
        <h2>🔒 Security Verification Login</h2>
        <form method="POST" action="{{ secure_url('/login') }}">
            @csrf
            <div class="form-group">
                <label>Network Account Username</label>
                <input type="text" name="username" placeholder="Enter ADMIN or account string" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter security passphrase" required>
            </div>
            <button type="submit" class="action-btn">Authenticate & Access 🚀</button>
            <div class="link-footer">
                <a href="https://onrender.com">🛡️ Direct System Panel</a>
            </div>
        </form>
    </div>

    <!-- REGISTRATION CONTAINER -->
    <div class="wrapper">
        <h2>📝 Node Matrix Registration</h2>
        <form method="POST" action="{{ secure_url('/register') }}">
            @csrf
            <div class="form-group">
                <label>Parent Sponsor Node Identifier</label>
                <input type="text" name="sponsor_id" id="reg_sponsor_id" value="{{ \$referralCode ?? request()->get('ref') }}" placeholder="Sponsor ID" required>
            </div>
            <div class="form-group">
                <label>Verified Parent Name</label>
                <input type="text" id="sponsor_name" value="{{ \$sponsorName ?? '' }}" readonly style="background: #1e293b; color: #94a3b8;">
            </div>
            <div class="form-group">
                <label>Full Legal Name</label>
                
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="Enter unique mail routing address" required>
            </div>
            <div class="form-group">
                <label>Mobile Communications Line</label>
                <input type="text" name="mobile" placeholder="10-digit international terminal number" required>
            </div>
            <div class="form-group">
                <label>Password Selection</label>
                <input type="password" name="password" placeholder="Min 8 alphanumeric components" required>
            </div>
            <div class="form-group">
                <label>Confirm Password Alignment</label>
                <input type="password" name="password_confirmation" placeholder="Re-enter verification sequence" required>
            </div>
            <button type="submit" class="action-btn">Finalize Generation Node 🎉</button>
        </form>
    </div>

    <script>
    function triggerClipboardCopy() {
        var copyText = document.getElementById("raw_ref_link");
        if (copyText) {
            copyText.select();
            copyText.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(copyText.value);
            alert("✨ Link secured into clipboard buffer memory:\n" + copyText.value);
        }
    }

    document.addEventListener("DOMContentLoaded", function() {
        document.getElementById('reg_sponsor_id')?.addEventListener('blur', function() {
            let sponsorId = this.value;
            if(sponsorId) {
                fetch(`/api/get-sponsor-name/${sponsorId}`)
                    .then(res => res.json())
                    .then(data => {
                        const target = document.getElementById('sponsor_name');
                        if(target) target.value = data.name || 'Unknown Node Block Address';
                    })
                    .catch(() => {
                        const target = document.getElementById('sponsor_name');
                        if(target) target.value = 'Failed network connection';
                    });
            }
        });
    });
    </script>
</body>
</html>
