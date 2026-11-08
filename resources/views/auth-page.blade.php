@extends('layouts.auth')
@section('title',($adminLogin??false)?'Admin Login':($authMode==='login'?'Login':'Create Account'))
@section('card-class',$authMode==='register'?'wide':'')
@section('content')
<div class="auth-kicker">LumiFlowOn {{($adminLogin??false)?'ADMIN ACCESS':'ACCOUNT'}}</div>@if(($adminLogin??false)&&auth()->check())<p class="auth-feedback">Currently signed in as <strong>{{auth()->user()->username}}</strong>. Enter your administrator credentials below to switch accounts securely.</p>@endif
<h1>{{($adminLogin??false)?'Admin Login':($authMode==='login'?'Welcome back':'Create your account')}}</h1>
<p class="auth-intro">{{$authMode==='login'?'Sign in with your User ID or email.':'Enter your details and verify your email to get started.'}}</p>
@if($errors->any())<div class="auth-feedback error" role="alert"><strong>Please check your details</strong><ul>@foreach($errors->all() as $error)<li>{{$error}}</li>@endforeach</ul></div>@endif
@if(session('status'))<div class="auth-feedback" role="status">{{session('status')}}</div>@endif
@if(session('success_reg'))<div class="auth-feedback" role="status">Account created successfully.<br>Your User ID: <strong>{{session('new_username')}}</strong><br>Referral link: <a href="{{session('ref_link')}}">{{session('ref_link')}}</a></div>@endif
@if($authMode==='login')
<form
                method="POST"
                action="{{ route('login.submit') }}"
            >

                @csrf
                @if($adminLogin??false)<input type="hidden" name="portal" value="admin">@endif

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
@if($adminLogin??false)<p class="auth-bottom"><a href="{{route('login.form')}}">Member Login</a></p>@endif<p class="auth-bottom"><a href="{{route('password.otp.request')}}">Forgot Password?</a></p><div class="auth-divider"></div><p class="auth-bottom">New to LumiFlowOn? <a href="{{route('register.form')}}">Create Account</a></p>
@else
@if($referralCode)<div class="sponsor" id="leobotReferralBanner">Sponsor ID: <strong>{{$referralCode}}</strong>@if($sponsorName)<br>Sponsor: {{$sponsorName}}@endif</div>@endif
<form class="register-grid" method="POST" action="{{route('register.submit')}}">@csrf
<div class="form-group full-width"><label for="account-sponsor">Sponsor ID</label><input id="account-sponsor" name="sponsor_id" value="{{old('sponsor_id',$referralCode)}}" placeholder="Enter sponsor User ID" autocomplete="off" required></div>
<div class="form-group"><label for="account-name">Full name</label><input id="account-name" name="name" value="{{old('name')}}" autocomplete="name" placeholder="Your full name" required></div>
<div class="form-group"><label for="account-mobile">Mobile number</label><input id="account-mobile" type="tel" name="mobile" value="{{old('mobile')}}" autocomplete="tel" placeholder="Your mobile number" required></div>
<div class="form-group full-width"><label for="account-email">Email address</label><input id="account-email" type="email" name="email" value="{{old('email')}}" autocomplete="email" placeholder="you@example.com" required></div>
<div class="form-group" id="lumi-registration-otp"><label for="lumi-otp-code">Verify your email</label><input type="hidden" name="challenge_token" id="lumi-otp-token" value=""><button type="button" id="lumi-send-otp" @disabled(!$registrationOtpEnabled)>Send Email OTP</button><input id="lumi-otp-code" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" placeholder="Enter six-digit OTP" autocomplete="one-time-code" @disabled(!$registrationOtpEnabled)><button type="button" id="lumi-verify-otp" disabled>Verify OTP</button><p id="lumi-otp-status" role="status" aria-live="polite">@unless($registrationOtpEnabled)Email verification is currently unavailable. Please contact support.@endunless</p></div>
<div class="form-group"><label for="account-new-password">Password</label><input id="account-new-password" type="password" name="password" autocomplete="new-password" placeholder="At least 8 characters" minlength="8" required></div><div class="form-group"><label for="account-confirm-password">Confirm password</label><input id="account-confirm-password" type="password" name="password_confirmation" autocomplete="new-password" placeholder="Enter password again" minlength="8" required></div><button type="submit">Create Account</button></form>
<p class="auth-bottom">Already have an account? <a href="{{route('login.form')}}">Login</a></p>
@endif
@endsection
@push('scripts')
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
@endpush
