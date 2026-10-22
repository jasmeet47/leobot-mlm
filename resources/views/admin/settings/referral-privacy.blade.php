
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Referral Privacy Settings | LumiFlowOn Admin</title>
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #090d16;
            color: #e2e8f0;
            font: 15px/1.6 Arial, sans-serif;
        }
        .container {
            max-width: 780px;
            width: 94%;
            margin: 35px auto;
        }
        h1 { color: #38bdf8; font-size: 28px; }
        .card {
            background: #111827;
            border: 1px solid #334155;
            border-radius: 14px;
            padding: 24px;
        }
        .field { margin-bottom: 22px; }
        label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
        }
        select {
            width: 100%;
            padding: 12px;
            background: #192335;
            color: #e2e8f0;
            border: 1px solid #475569;
            border-radius: 8px;
            font: inherit;
        }
        button {
            padding: 12px 24px;
            background: #0284c7;
            color: white;
            border: none;
            border-radius: 8px;
            font: inherit;
            cursor: pointer;
        }
        button:hover { background: #0369a1; }
        .notice { color: #94a3b8; font-size: 13px; }
        .success { color: #86efac; }
        .error { color: #fca5a5; }
    </style>
</head>
<body>
<main class="container">
    <h1>LumiFlowOn - Referral Privacy Settings</h1>
    <p class="notice">
        Control which details members can see about their
        direct referrals (Level 1 only).
    </p>

    @if (session('success'))
        <p class="success" role="status">
            {{ session('success') }}
        </p>
    @endif

    @if ($errors->any())
        <div class="error" role="alert">
            <p>Please correct the following:</p>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST"
          action="{{ route('admin.referral-privacy.update') }}">
        @csrf

        <div class="card">
            <div class="field">
                <label for="phone_visibility">Phone Number</label>
                <select id="phone_visibility" name="phone_visibility">
                    @foreach (['off' => 'OFF', 'masked' => 'Masked', 'full' => 'Full'] as $value => $label)
                        <option value="{{ $value }}"
                            @selected(old('phone_visibility', $settings->phone_visibility) === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="email_visibility">Email Address</label>
                <select id="email_visibility" name="email_visibility">
                    @foreach (['off' => 'OFF', 'masked' => 'Masked', 'full' => 'Full'] as $value => $label)
                        <option value="{{ $value }}"
                            @selected(old('email_visibility', $settings->email_visibility) === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="show_investment_amount">Investment Amount</label>
                <select id="show_investment_amount" name="show_investment_amount">
                    <option value="0"
                        @selected(old('show_investment_amount', (int) in_array(strtolower((string) $settings->show_investment_amount), ['1', 't', 'true'], true)) == 0)>
                        OFF
                    </option>
                    <option value="1"
                        @selected(old('show_investment_amount', (int) in_array(strtolower((string) $settings->show_investment_amount), ['1', 't', 'true'], true)) == 1)>
                        ON
                    </option>
                </select>
            </div>

            <div class="field">
                <label for="show_package_status">Package Status</label>
                <select id="show_package_status" name="show_package_status">
                    <option value="0"
                        @selected(old('show_package_status', (int) in_array(strtolower((string) $settings->show_package_status), ['1', 't', 'true'], true)) == 0)>
                        OFF
                    </option>
                    <option value="1"
                        @selected(old('show_package_status', (int) in_array(strtolower((string) $settings->show_package_status), ['1', 't', 'true'], true)) == 1)>
                        ON
                    </option>
                </select>
            </div>

            <button type="submit">Save Privacy Settings</button>
        </div>
    </form>

    <p class="notice">
        Full phone/email visibility requires both the admin's Full setting and the referred member's individual consent. Members can revoke consent at any time.
    </p>

    <p class="notice">
        These settings do not activate packages, move funds,
        or distribute income.
    </p>
</main>
</body>
</html>