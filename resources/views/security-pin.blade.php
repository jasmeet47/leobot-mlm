
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Security PIN | LeoBot</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 24px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #090d16;
            color: #e2e8f0;
            font-family: Arial, sans-serif;
        }

        .card {
            width: 100%;
            max-width: 460px;
            padding: 30px;
            background: #111827;
            border: 1px solid #263244;
            border-radius: 16px;
        }

        .brand {
            text-align: center;
            color: #38bdf8;
            font-size: 26px;
            font-weight: bold;
            margin-bottom: 8px;
        }

        h1 {
            text-align: center;
            font-size: 22px;
            margin: 16px 0 10px;
        }

        .description {
            color: #94a3b8;
            text-align: center;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 24px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: bold;
        }

        .field {
            margin-bottom: 20px;
        }

        input {
            width: 100%;
            padding: 13px;
            border: 1px solid #475569;
            border-radius: 8px;
            outline: none;
            background: #090d16;
            color: #ffffff;
            font-size: 16px;
        }

        input:focus {
            border-color: #38bdf8;
        }

        .hint {
            display: block;
            color: #94a3b8;
            font-size: 12px;
            margin-top: 7px;
        }

        .button {
            width: 100%;
            padding: 14px;
            border: 0;
            border-radius: 8px;
            background: #0284c7;
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        .button:hover {
            background: #0369a1;
        }

        .alert {
            padding: 13px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-size: 14px;
            line-height: 1.5;
        }

        .alert-success {
            background: #064e3b;
            color: #d1fae5;
        }

        .alert-error {
            background: #7f1d1d;
            color: #fee2e2;
        }

        .alert-error ul {
            margin: 8px 0 0;
            padding-left: 20px;
        }

        .back-link {
            display: block;
            text-align: center;
            color: #38bdf8;
            text-decoration: none;
            margin-top: 22px;
            font-size: 14px;
        }

        @media (max-width: 480px) {
            .card {
                padding: 22px;
            }

            body {
                padding: 12px;
            }
        }
    </style>
</head>

<body>
    <main class="card">

        <div class="brand">
            LEOBOT
        </div>

        <h1>Security PIN</h1>

        <p class="description">
            Set or change your 6-digit Security PIN.
            Enter your account password to confirm
            that this account belongs to you.
        </p>

        @if (session('success'))
            <div class="alert alert-success" role="status">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-error" role="alert">
                <strong>Please correct the following:</strong>

                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @auth

            <form
                method="POST"
                action="{{ route('security-pin.update') }}"
                autocomplete="off"
            >
                @csrf

                <div class="field">
                    <label for="current_password">
                        Account Password
                    </label>

                    <input
                        type="password"
                        id="current_password"
                        name="current_password"
                        autocomplete="current-password"
                        required
                    >
                </div>

                <div class="field">
                    <label for="security_pin">
                        New 6-Digit Security PIN
                    </label>

                    <input
                        type="password"
                        id="security_pin"
                        name="security_pin"
                        inputmode="numeric"
                        pattern="[0-9]{6}"
                        minlength="6"
                        maxlength="6"
                        autocomplete="new-password"
                        required
                    >

                    <span class="hint">
                        Enter exactly 6 digits.
                        Avoid easy-to-guess PINs.
                    </span>
                </div>

                <div class="field">
                    <label for="security_pin_confirmation">
                        Confirm Security PIN
                    </label>

                    <input
                        type="password"
                        id="security_pin_confirmation"
                        name="security_pin_confirmation"
                        inputmode="numeric"
                        pattern="[0-9]{6}"
                        minlength="6"
                        maxlength="6"
                        autocomplete="new-password"
                        required
                    >
                </div>

                <button
                    type="submit"
                    class="button"
                >
                    Save Security PIN
                </button>
            </form>

        @else

            <div class="alert alert-error">
                Please log in before setting
                your Security PIN.
            </div>

        @endauth

        <a href="{{ url('/join') }}" class="back-link">
            Back to LeoBot
        </a>

    </main>
</body>
</html>
