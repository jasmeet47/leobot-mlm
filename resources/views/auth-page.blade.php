<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>LEOBOT MLM</title>

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

</head>

<body>

<div class="container">

    <!-- HEADER -->

    <div class="header">

        <h1>LEOBOT MLM</h1>

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
                        name="password"
                        placeholder="Enter Password"
                        required
                    >

                </div>


                <button type="submit">

                    Login

                </button>

            </form>

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

    </div>


    <div class="footer">

        © {{ date('Y') }} LEOBOT MLM

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

</body>

</html>