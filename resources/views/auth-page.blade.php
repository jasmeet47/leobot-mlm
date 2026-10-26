<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>LumiFlowOn</title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background:
                linear-gradient(
                    135deg,
                    #0f172a,
                    #1e293b
                );

            min-height: 100vh;

            display: flex;

            justify-content: center;

            align-items: center;

            padding: 20px;
        }

        .container {
            width: 100%;
            max-width: 1000px;

            background: white;

            border-radius: 20px;

            overflow: hidden;

            box-shadow:
                0 20px 60px
                rgba(0,0,0,0.30);
        }

        .header {
            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #7c3aed
                );

            color: white;

            text-align: center;

            padding: 35px 20px;
        }

        .header h1 {
            font-size: 38px;
            font-weight: 800;
            margin-bottom: 8px;
        }

        .header p {
            font-size: 16px;
            opacity: 0.9;
        }

        .forms {
            display: grid;
            grid-template-columns: 1fr 1fr;
        }

        .box {
            padding: 35px;
        }

        .box + .box {
            border-left: 1px solid #e5e7eb;
        }

        .box h2 {
            font-size: 25px;
            margin-bottom: 25px;
            color: #111827;
        }

        .form-group {
            margin-bottom: 17px;
        }

        label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 7px;
            color: #374151;
        }

        input {
            width: 100%;

            padding: 13px 14px;

            border:
                1px solid #d1d5db;

            border-radius: 9px;

            font-size: 15px;

            outline: none;
        }

        input:focus {
            border-color: #2563eb;

            box-shadow:
                0 0 0 3px
                rgba(37,99,235,0.12);
        }

        button {
            width: 100%;

            padding: 14px;

            border: none;

            border-radius: 9px;

            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #7c3aed
                );

            color: white;

            font-size: 16px;

            font-weight: 700;

            cursor: pointer;
        }

        button:hover {
            opacity: 0.92;
        }

        .copy-button {
            margin-top: 10px;

            background:
                linear-gradient(
                    135deg,
                    #059669,
                    #047857
                );
        }

        .sponsor {
            background: #f0fdf4;

            border:
                1px solid #bbf7d0;

            padding: 12px;

            border-radius: 8px;

            margin-bottom: 18px;

            color: #166534;

            font-size: 14px;
        }

        .success {
            background: #ecfdf5;

            border:
                1px solid #6ee7b7;

            color: #065f46;

            padding: 15px;

            margin: 20px;

            border-radius: 10px;
        }

        .error {
            background: #fef2f2;

            border:
                1px solid #fecaca;

            color: #991b1b;

            padding: 15px;

            margin: 20px;

            border-radius: 10px;
        }

        .ref-link {
            display: block;

            background: white;

            padding: 10px;

            margin-top: 10px;

            border-radius: 6px;

            word-break: break-all;

            color: #2563eb;

            border: 1px solid #dbeafe;
        }

        .copy-message {
            margin-top: 8px;

            color: #166534;

            font-weight: 600;

            text-align: center;

            display: none;
        }

        .footer {
            text-align: center;

            padding: 20px;

            background: #f8fafc;

            color: #64748b;

            font-size: 13px;
        }

        @media (max-width: 750px) {

            .forms {
                grid-template-columns: 1fr;
            }

            .box + .box {
                border-left: none;

                border-top:
                    1px solid #e5e7eb;
            }

            .header h1 {
                font-size: 30px;
            }
        }

    </style>

<!-- LUMIFLOWON_PREMIUM_GOLD_V1 -->
<style id="lumiflowon-premium-gold-v1">
:root {
    --lumi-navy: #06152b;
    --lumi-deep: #020915;
    --lumi-gold: #f2cc78;
    --lumi-gold-light: #ffe6a4;
    --lumi-text: #f9f6ed;
}

html {
    background: var(--lumi-deep);
}

body {
    color: var(--lumi-text);
    background:
        radial-gradient(ellipse at 92% 35%,
            rgba(15, 91, 169, .34), transparent 42%),
        radial-gradient(ellipse at 5% 78%,
            rgba(188, 131, 43, .16), transparent 38%),
        linear-gradient(135deg,
            #081d3d 0%, #041126 52%, #010713 100%);
    background-attachment: fixed;
    min-height: 100vh;
}

h1, h2, h3 {
    color: #fff4d5;
}

h1 {
    color: var(--lumi-gold-light);
    text-shadow: 0 2px 22px rgba(237, 190, 91, .2);
}

.forms {
    gap: 28px;
    align-items: start;
}

.forms > div,
.forms > section,
.forms > article {
    background: linear-gradient(
        145deg,
        rgba(5, 17, 38, .96),
        rgba(1, 8, 20, .98)
    );
    border: 1px solid rgba(245, 202, 112, .78);
    border-radius: 16px;
    box-shadow:
        0 16px 48px rgba(0, 0, 0, .35),
        0 0 24px rgba(236, 190, 91, .08);
}

input:not([type="checkbox"]):not([type="radio"]),
select,
textarea {
    background: rgba(3, 17, 37, .93);
    color: #fff;
    border: 1px solid rgba(231, 194, 122, .5);
    border-radius: 9px;
}

input::placeholder,
textarea::placeholder {
    color: #b5bbca;
}

input:focus,
select:focus,
textarea:focus {
    outline: 2px solid rgba(246, 206, 124, .7);
    outline-offset: 1px;
    border-color: var(--lumi-gold);
}

button[type="submit"],
input[type="submit"] {
    background: linear-gradient(
        135deg,
        #fff0b8 0%,
        #f0cb78 45%,
        #b88b43 100%
    );
    color: #11100c;
    border: 1px solid #ffdfa0;
    border-radius: 9px;
    font-weight: 750;
    box-shadow: 0 4px 18px rgba(238, 192, 93, .16);
    cursor: pointer;
}

button[type="submit"]:hover,
input[type="submit"]:hover {
    filter: brightness(1.08);
}

a {
    color: #ffe2a0;
}

a:hover {
    color: #fff3ce;
}

@media (max-width: 760px) {
    .forms {
        gap: 18px;
    }
}
</style>
<!-- LUMIFLOWON_DYNAMIC_LOGO_V1 -->
<style>.lumi-dynamic-brand{display:flex;justify-content:center;margin:0 auto 12px}.lumi-dynamic-brand img{max-width:min(320px,70vw);max-height:170px;object-fit:contain}.forms h2,.forms h3,.forms label,.forms p,.forms legend{color:#f7eedb!important}</style>

<!-- LUMIFLOWON_FULL_NAVY_GOLD_V2 -->
<style id="lumiflowon-full-navy-gold-v2">
html,body{background:#041023!important;color:#f8eed9!important;min-height:100vh!important}
body{align-items:flex-start!important;padding:22px 14px!important}
.container{background:#07152b!important;border:1px solid rgba(224,179,90,.65)!important;box-shadow:0 20px 60px rgba(0,0,0,.55)!important;max-width:1040px!important;overflow:hidden!important}
.header{background:linear-gradient(135deg,#06152b 0%,#0a2444 58%,#091a31 100%)!important;color:#ffe4a1!important;border-bottom:1px solid #bd9650!important;padding:24px 20px!important}
.header h1{color:#ffe3a0!important}.header p{color:#e4dfd2!important}
.forms{background:#07152b!important;gap:20px!important;padding:20px!important;align-items:stretch!important}
.forms>.box{background:linear-gradient(145deg,#0c1b32,#040c1b)!important;border:1px solid #bc9655!important;border-radius:15px!important;box-shadow:none!important;padding:28px!important}
.forms>.box+.box{border-left:1px solid #bc9655!important}
.forms h2,.forms label,.forms p,.forms legend{color:#f8eed9!important}
.forms input:not([type=checkbox]):not([type=radio]){background:#0b1a31!important;color:#fff6e3!important;border:1px solid #9d824f!important}
.forms input::placeholder{color:#bac3d0!important}
.forms input:focus{border-color:#f5d38d!important;box-shadow:0 0 0 2px rgba(244,199,111,.22)!important}
.forms button[type=submit]{background:linear-gradient(100deg,#ffe8a5,#c49a50)!important;color:#16100a!important}
.footer{background:#07152b!important;color:#c5c9d0!important;border-top:1px solid rgba(224,179,90,.3)!important}
.sponsor{background:#0b2a27!important;color:#b5f0d4!important;border-color:#31745c!important}
.success{background:#12392b!important;color:#d8ffe7!important;border-color:#397c5c!important}
.error,.lumi-captcha-error{background:#3b1b20!important;color:#ffe3e3!important;border:1px solid #a85e64!important}
.ref-link{background:#0c1d36!important;color:#ffe3a0!important;border-color:#9d824f!important}
.lumi-dynamic-brand img{max-height:125px!important}
@media(max-width:750px){body{padding:8px!important}.forms{grid-template-columns:1fr!important;padding:12px!important;gap:12px!important}.forms>.box{padding:20px!important}.forms>.box+.box{border-left:1px solid #bc9655!important;border-top:1px solid #bc9655!important}.header{padding:18px 12px!important}}
</style>
</head>

<body>

<div class="container">

    <!-- HEADER -->

    <div class="header">

        <div class="lumi-dynamic-brand"><img src="{{ route('lumiflowon.brand.logo') }}" alt="LumiFlowOn logo"></div>
<h1>LumiFlowOn</h1>

        <p>
            Secure Login & Registration
        </p>

    </div>


    <!-- SUCCESS MESSAGE -->

    @if(session('success_reg'))

        <div class="success">

            <strong>
                Registration Successful!
            </strong>

            <br><br>

            Your Username:

            <strong>
                {{ session('new_username') }}
            </strong>

            <br><br>

            Your Referral Link:

            <div
                id="referralLink"
                class="ref-link"
            >
                {{ session('ref_link') }}
            </div>

            <button
                type="button"
                class="copy-button"
                onclick="copyReferralLink()"
            >
                Copy Referral Link
            </button>

            <div
                id="copyMessage"
                class="copy-message"
            >
                Referral link copied!
            </div>

        </div>

    @endif


    <!-- ERRORS -->

    @if($errors->any())

        <div class="error">

            <strong>
                Please fix the following:
            </strong>

            <ul
                style="
                    margin-top:10px;
                    margin-left:20px;
                "
            >

                @foreach($errors->all() as $error)

                    <li>
                        {{ $error }}
                    </li>

                @endforeach

            </ul>

        </div>

    @endif


    <!-- FORMS -->

    <div class="forms">


        <!-- LOGIN -->

        <div class="box">

            <h2>
                Login
            </h2>

            <form
                method="POST"
                action="{{ route('login.submit') }}"
            >

                @csrf

                <div class="form-group">

                    <label>
                        Username
                    </label>

                    <input
                        type="text"
                        name="username"
                        placeholder="Enter Username"
                        value="{{ old('username') }}"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Password
                    </label>

                    <input
                        type="password"
                        id="lfoLoginPassword"
                        name="password"
                        placeholder="Enter Password"
                        required
                    >
                    <button type="button" class="lfo-password-toggle" data-target="lfoLoginPassword" aria-controls="lfoLoginPassword" aria-pressed="false">Show password</button>

                </div>


                <!-- LUMIFLOWON_LOGIN_CAPTCHA_V1 -->
                <div class="form-group">
                    <label for="lumiLoginCaptcha">Login security check: {{ $loginCaptchaA }} + {{ $loginCaptchaB }} = ?</label>
                    <input id="lumiLoginCaptcha" name="login_captcha" type="text" inputmode="numeric" pattern="[0-9]+" maxlength="3" autocomplete="off" placeholder="Enter the answer" required>
                    @error('login_captcha') <p class="lumi-captcha-error" role="alert">{{ $message }}</p> @enderror
                </div>
                <button type="submit">

                    Login

                </button>

            <div style="margin-top:12px;text-align:right">
    <a href="{{ route('password.otp.request') }}" style="color:#d8b878;text-decoration:underline">
        Forgot Password?
    </a>
</div>
</form>

            @if(session('status'))
                <p class="success" role="status">{{ session('status') }}</p>
            @endif

        </div>


        <!-- REGISTER -->

        <div class="box">

            <h2>
                Create Account
            </h2>


            @if($referralCode)

                <div class="sponsor" id="leobotReferralBanner">

                    Sponsor ID:

                    <strong>
                        {{ $referralCode }}
                    </strong>

                    @if($sponsorName)

                        <br>

                        Sponsor:

                        <strong>
                            {{ $sponsorName }}
                        </strong>

                    @endif

                </div>

            @endif


            <form
                method="POST"
                action="{{ route('register.submit') }}"
            >

                @csrf


                <div class="form-group">

                    <label>
                        Sponsor ID
                    </label>

                    <input
                        type="text"
                        name="sponsor_id"
                        placeholder="Enter Sponsor ID"
                        value="{{ old('sponsor_id', $referralCode) }}"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Full Name
                    </label>

                    <input
                        type="text"
                        name="name"
                        placeholder="Full Name"
                        value="{{ old('name') }}"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Email
                    </label>

                    <input
                        type="email"
                        name="email"
                        placeholder="Email Address"
                        value="{{ old('email') }}"
                        required
                    >

                </div>


                <div class="form-group" id="lfoOtpPanel">
                    <label for="lfoOtpCode">Verify your email</label>
                    @if($registrationOtpEnabled)
                    <p style="margin:8px 0;color:#f3dfb2">Send a six-digit code to the email above, then verify it before registration.</p>
                    @else
                    <p role="status" style="margin:8px 0;color:#f5d28e">Email verification is currently disabled by the administrator. Enable Registration Email OTP in Admin Settings to activate these buttons.</p>
                    @endif
                    <input type="hidden" name="challenge_token" id="lfoOtpToken" value="">
                    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                        <button type="button" id="lfoSendOtp" @unless($registrationOtpEnabled) disabled title="Enable Email OTP in Admin Settings" @endunless style="width:auto">Send OTP</button>
                        <input id="lfoOtpCode" @unless($registrationOtpEnabled) disabled @endunless type="text" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" placeholder="6-digit OTP" style="flex:1;min-width:110px">
                        <button type="button" id="lfoVerifyOtp" @unless($registrationOtpEnabled) disabled title="Enable Email OTP in Admin Settings" @endunless style="width:auto">Verify OTP</button>
                    </div>
                    <p id="lfoOtpStatus" role="status" aria-live="polite" style="margin-top:9px;color:#f3dfb2"></p>
                </div>
                <div class="form-group">
                    <label for="lfoCaptcha">Security check: {{ $captchaA }} + {{ $captchaB }} = ?</label>
                    <input id="lfoCaptcha" name="registration_captcha" type="text" inputmode="numeric" pattern="[0-9]+" maxlength="3" required autocomplete="off" placeholder="Enter the answer">
                    <p style="color:#f3dfb2;margin-top:6px">This is a basic session check, not a replacement for production bot protection.</p>
                </div>
                @if($errors->any())
                    <div role="alert" style="color:#ffb4a9;margin:10px 0">
                        @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                @endif

                <div class="form-group">

                    <label>
                        Mobile
                    </label>

                    <input
                        type="text"
                        name="mobile"
                        placeholder="Mobile Number"
                        value="{{ old('mobile') }}"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Password
                    </label>

                    <input
                        type="password"
                        id="lfoRegisterPassword"
                        name="password"
                        placeholder="Minimum 8 characters"
                        required
                    >
                    <button type="button" class="lfo-password-toggle" data-target="lfoRegisterPassword" aria-controls="lfoRegisterPassword" aria-pressed="false">Show password</button>

                </div>


                <div class="form-group">

                    <label>
                        Confirm Password
                    </label>

                    <input
                        type="password"
                        id="lfoConfirmPassword"
                        name="password_confirmation"
                        placeholder="Confirm Password"
                        required
                    >
                    <button type="button" class="lfo-password-toggle" data-target="lfoConfirmPassword" aria-controls="lfoConfirmPassword" aria-pressed="false">Show password</button>

                </div>


                <button type="submit">

                    Create Account

                </button>

            </form>

        </div>

    </div>


    <div class="footer">

        ┬⌐ {{ date('Y') }} LumiFlowOn

    </div>


</div>


<script>

    function copyReferralLink() {

        const referralLink =
            document.getElementById('referralLink').innerText.trim();

        const copyMessage =
            document.getElementById('copyMessage');

        navigator.clipboard.writeText(referralLink)
            .then(function () {

                copyMessage.style.display = 'block';

                setTimeout(function () {
                    copyMessage.style.display = 'none';
                }, 3000);

            })
            .catch(function () {

                const textArea =
                    document.createElement('textarea');

                textArea.value = referralLink;

                document.body.appendChild(textArea);

                textArea.select();

                document.execCommand('copy');

                document.body.removeChild(textArea);

                copyMessage.style.display = 'block';

                setTimeout(function () {
                    copyMessage.style.display = 'none';
                }, 3000);

            });
    }


    // LUMIFLOWON_REGISTRATION_OTP_UI_V1
    (function () {
        const panel = document.getElementById('lfoOtpPanel');
        if (!panel || !@json((bool) $registrationOtpEnabled)) return;
        const email = document.querySelector('form[action="{{ route('register.submit') }}"] input[name="email"]');
        const token = document.getElementById('lfoOtpToken');
        const code = document.getElementById('lfoOtpCode');
        const status = document.getElementById('lfoOtpStatus');
        const send = document.getElementById('lfoSendOtp');
        const verify = document.getElementById('lfoVerifyOtp');
        const form = panel.closest('form');
        let sentEmail = '';
        let verified = false;
        const csrf = form.querySelector('input[name="_token"]').value;
        function message(s, ok) { status.textContent = s; status.style.color = ok ? '#a7f3d0' : '#ffb4a9'; }
        email.addEventListener('input', function () { token.value=''; verified=false; sentEmail=''; message('Email changed. Please request a new OTP.', false); });
        async function post(url, payload) {
            const r = await fetch(url, {method:'POST', credentials:'same-origin', headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf}, body:JSON.stringify(payload)});
            const data = await r.json().catch(()=>({}));
            if (!r.ok) throw new Error(data.message || (data.errors && Object.values(data.errors).flat()[0]) || 'Request failed ('+r.status+')');
            return data;
        }
        send.addEventListener('click', async function () {
            const value = email.value.trim().toLowerCase();
            if (!value || !email.checkValidity()) { message('Enter a valid email first.', false); return; }
            send.disabled=true; token.value=''; verified=false;
            try {
                const d = await post(@json(route('registration-otp.send')), {email:value});
                if (!d.challenge_token) throw new Error('OTP service did not return a challenge.');
                token.value=d.challenge_token; sentEmail=value;
                message('OTP sent. Check your email, then enter the code.', true);
            } catch(e) { message(e.message, false); }
            finally { send.disabled=false; }
        });
        verify.addEventListener('click', async function () {
            if (!token.value || !sentEmail) { message('Send OTP first.', false); return; }
            if (!/^[0-9]{6}$/.test(code.value.trim())) { message('Enter the six-digit OTP.', false); return; }
            verify.disabled=true;
            try {
                await post(@json(route('registration-otp.verify')), {email:sentEmail,otp:code.value.trim(),challenge_token:token.value});
                verified=true; message('Email verified successfully.', true);
            } catch(e) { verified=false; message(e.message, false); }
            finally { verify.disabled=false; }
        });
        form.addEventListener('submit', function(e) {
            if (!verified || sentEmail !== email.value.trim().toLowerCase()) {
                e.preventDefault(); message('Verify your email OTP before creating the account.', false);
            }
        });
    }());

    // LEOBOT_SPONSOR_VERIFY_V1: read-only lookup; server validates again on registration.
    (function () {
        'use strict';
        const input = document.querySelector('input[name="sponsor_id"]');
        if (!input || !input.closest('form')) return;
        // LEOBOT_REFERRAL_BANNER_SYNC_V1
        const banner = document.getElementById('leobotReferralBanner');
        const originalCode = banner ? banner.querySelector('strong').textContent.trim() : '';

        const feedback = document.createElement('div');
        feedback.id = 'leobotSponsorVerificationMessage';
        feedback.setAttribute('role', 'status');
        feedback.setAttribute('aria-live', 'polite');
        feedback.style.cssText = 'margin-top:7px;font-size:13px;min-height:17px;';
        input.insertAdjacentElement('afterend', feedback);

        let timer = null;
        let pending = null;
        let requestNumber = 0;

        function display(message, kind) {
            feedback.textContent = message;
            feedback.style.color = kind === 'valid' ? '#15803d'
                : kind === 'invalid' ? '#b91c1c' : '#64748b';
        }

        async function verify(value, number) {
            const controller = new AbortController();
            pending = controller;
            try {
                const response = await fetch('/api/verify-sponsor', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ sponsor_id: value }),
                    signal: controller.signal
                });
                if (number !== requestNumber || input.value.trim() !== value) return;

                if (response.status === 429) {
                    display('Too many checks. Please wait and try again.', 'invalid');
                    return;
                }
                if (!response.ok) {
                    display('Sponsor verification is unavailable. Please try again.', 'invalid');
                    return;
                }

                const data = await response.json();
                if (number !== requestNumber || input.value.trim() !== value) return;
                if (data.valid === true && typeof data.sponsor_name === 'string') {
                    display('Verified Sponsor: ' + data.sponsor_name, 'valid');
                } else {
                    display('Sponsor ID not found.', 'invalid');
                }
            } catch (error) {
                if (error.name !== 'AbortError' && number === requestNumber) {
                    display('Sponsor verification is unavailable. Please try again.', 'invalid');
                }
            } finally {
                if (pending === controller) pending = null;
            }
        }

        function scheduleCheck() {
            const number = ++requestNumber;
            clearTimeout(timer);
            if (pending) pending.abort();
            const value = input.value.trim();
            if (banner) banner.style.display = value === originalCode ? '' : 'none';
            if (!value) {
                display('', '');
                return;
            }
            display('Checking Sponsor ID...', 'pending');
            timer = setTimeout(function () { verify(value, number); }, 550);
        }

        input.addEventListener('input', scheduleCheck);
        if (input.value.trim()) scheduleCheck();
    }());
</script>


<!-- LUMIFLOWON_PASSWORD_VISIBILITY_V1 -->
<script>
document.addEventListener('DOMContentLoaded', function () {
 document.querySelectorAll('.lfo-password-toggle').forEach(function(btn) {
  btn.addEventListener('click', function() {
   var input=document.getElementById(btn.getAttribute('data-target'));
   if(!input) return;
   var show=input.type==='password';
   input.type=show?'text':'password';
   btn.textContent=show?'Hide password':'Show password';
   btn.setAttribute('aria-pressed',show?'true':'false');
  });
 });
});
</script>
<style id="lfo-password-toggle-style">
.lfo-password-toggle{display:inline-block!important;width:auto!important;margin:8px 0 0!important;padding:6px 12px!important;background:transparent!important;color:#ffe2a0!important;border:1px solid #ae8b52!important;border-radius:7px!important;cursor:pointer!important;font-size:13px!important}
.lfo-password-toggle:focus-visible{outline:2px solid #ffe2a0!important;outline-offset:2px!important}
#lfoSendOtp:disabled,#lfoVerifyOtp:disabled{opacity:.45!important;cursor:not-allowed!important}
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const sponsor = document.querySelector('input[name="sponsor_id"]');
  if (!sponsor) return;
  const form = sponsor.closest('form');
  if (!form || form.querySelector('[name="beta_invite_code"]')) return;
  const wrapper = document.createElement('div');
  wrapper.style.margin = '12px 0';
  const label = document.createElement('label');
  label.textContent = 'Beta Invitation Code (required)';
  label.style.display = 'block';
  label.style.marginBottom = '6px';
  const field = document.createElement('input');
  field.name = 'beta_invite_code';
  field.type = 'text';
  field.required = true;
  field.maxLength = 19;
  field.placeholder = 'XXXX-XXXX-XXXX-XXXX';
  field.autocomplete = 'off';
  field.style.cssText = 'display:block;width:100%;box-sizing:border-box;padding:12px;border:1px solid #c3a766;border-radius:8px;background:#fff;color:#142544';
  wrapper.append(label, field);
  sponsor.parentElement.insertAdjacentElement('afterend', wrapper);
});
</script>
</body>

</html>