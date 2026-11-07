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
<style>.lumi-dynamic-brand{display:flex;justify-content:center;margin:0 auto 12px}.lumi-dynamic-brand img{max-width:min(320px,70vw);max-height:125px;object-fit:contain}</style>
</head>

<body>
@include('partials.beta-notice')

<div class="container">

    <!-- HEADER -->

    <div class="header">

        <div class="lumi-dynamic-brand"><img src="{{ route('lumiflowon.brand.logo') }}" alt="LumiFlowOn"></div><h1>LumiFlowOn</h1>

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


        @if($authMode==='login')
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
                        User ID / Email
                    </label>

                    <input
                        type="text"
                        name="username"
                        placeholder="User ID or Email"
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
                        name="password"
                        placeholder="Enter Password"
                        required
                    >

                </div>


                @if($loginCaptcha)
<div class="form-group"><label for="lumi-login-captcha">Security check: {{ $loginCaptcha['left'] }} + {{ $loginCaptcha['right'] }} = ?</label><input id="lumi-login-captcha" name="login_captcha" inputmode="numeric" pattern="[0-9]{1,2}" maxlength="2" autocomplete="off" required placeholder="Enter the answer">@error('login_captcha')<p role="alert" style="color:#fca5a5">{{ $message }}</p>@enderror</div>
@endif
<button type="submit">

                    Login

                </button>

            </form>
<!-- LUMIFLOWON_FORGOT_PASSWORD_UI_V1 -->
<p style="margin-top:12px;text-align:center"><a href="{{ route('password.otp.request') }}">Forgot Password?</a></p>

            @if(session('status'))
                <p class="success" role="status">{{ session('status') }}</p>
            @endif

        </div>


        @endif
        @if($authMode==='register')
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


                <div class="form-group" id="lumi-registration-otp"><label for="lumi-otp-code">Email verification</label>
<input type="hidden" name="challenge_token" id="lumi-otp-token" value="">
<button type="button" id="lumi-send-otp" @disabled(!$registrationOtpEnabled)>Send Email OTP</button>
<input id="lumi-otp-code" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" placeholder="Enter six-digit OTP" autocomplete="one-time-code" @disabled(!$registrationOtpEnabled)>
<button type="button" id="lumi-verify-otp" disabled>Verify OTP</button>
<p id="lumi-otp-status" role="status" aria-live="polite">@unless($registrationOtpEnabled)Email verification is currently unavailable. Please contact support.@endunless</p></div>
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
                        name="password"
                        placeholder="Minimum 8 characters"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Confirm Password
                    </label>

                    <input
                        type="password"
                        name="password_confirmation"
                        placeholder="Confirm Password"
                        required
                    >

                </div>


                <button type="submit">

                    Create Account

                </button>

            </form>

        </div>
        @endif
    </div>

    <p style="text-align:center"><a href="{{ route('login.form') }}">Login</a> · <a href="{{ route('register.form') }}">Create Account</a></p>
    <div class="footer">

        © {{ date('Y') }} LumiFlowOn

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

<script>
document.querySelectorAll('input[type="password"]').forEach(function(input, index) {
    if (!input.id) input.id = 'lumi-password-' + index;
    const button = document.createElement('button');
    button.type = 'button'; button.textContent = 'Show password';
    button.setAttribute('aria-controls', input.id); button.setAttribute('aria-pressed', 'false');
    button.addEventListener('click', function() {
        const show = input.type === 'password'; input.type = show ? 'text' : 'password';
        button.textContent = show ? 'Hide password' : 'Show password'; button.setAttribute('aria-pressed', String(show));
    });
    input.insertAdjacentElement('afterend', button);
});
</script><script>
(function(){
 const panel=document.getElementById('lumi-registration-otp');if(!panel)return;
 const form=panel.closest('form'),email=form.querySelector('[name=email]'),send=document.getElementById('lumi-send-otp'),verify=document.getElementById('lumi-verify-otp'),token=document.getElementById('lumi-otp-token'),code=document.getElementById('lumi-otp-code'),status=document.getElementById('lumi-otp-status');
 const enabled=@json($registrationOtpEnabled);let verified=false,boundEmail='',busy=false;
 function feedback(message,ok){status.textContent=message;status.style.setProperty('color',ok?'#22c55e':'#fca5a5','important');status.dataset.state=ok?'success':'error';}
 function reset(){verified=false;token.value='';code.value='';verify.disabled=true;status.textContent='';status.dataset.state='';}
 email.addEventListener('input',function(){if(enabled)reset();});
 async function call(url,data){const response=await fetch(url,{method:'POST',credentials:'same-origin',headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':@json(csrf_token())},body:JSON.stringify(data)});const result=await response.json();if(!response.ok||result.success!==true)throw new Error(Object.values(result.errors||{}).flat()[0]||result.message||'Please try again.');return result;}
 send.addEventListener('click',async function(){if(!enabled||busy)return;if(!email.checkValidity()){email.reportValidity();return;}reset();busy=true;send.disabled=true;const target=email.value.trim().toLowerCase();try{const result=await call(@json(route('registration-email-otp.send')),{email:target});if(email.value.trim().toLowerCase()!==target)return;boundEmail=target;token.value=result.challenge_token;verify.disabled=false;feedback('OTP sent. Check your email.',true);}catch(error){feedback(error.message,false);}finally{busy=false;send.disabled=!enabled;}});
 verify.addEventListener('click',async function(){if(!enabled||busy||!token.value)return;busy=true;verify.disabled=true;const challenge=token.value,target=boundEmail;try{if(email.value.trim().toLowerCase()!==boundEmail)throw new Error('Email changed. Request a new OTP.');const result=await call(@json(route('registration-email-otp.verify')),{email:target,otp:code.value,challenge_token:challenge});if(email.value.trim().toLowerCase()!==target||token.value!==challenge)return;verified=true;feedback('OTP verified successfully.',true);}catch(error){verified=false;feedback(error.message,false);}finally{busy=false;verify.disabled=verified||!token.value;}});
 form.addEventListener('submit',function(event){if(enabled&&!verified){event.preventDefault();feedback('Please verify your email OTP before creating the account.',false);}});
})();
</script>
</body>

</html>