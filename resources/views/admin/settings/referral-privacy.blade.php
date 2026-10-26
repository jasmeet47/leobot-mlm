
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
<style id="lumiflowon-shared-admin-sidebar-v2">
html,body{min-height:100%;}body{margin:0!important;background:#061327!important;color:#edf3ff!important}.lf2-shell{display:flex;min-height:100vh;width:100%;align-items:stretch}.lf2-sidebar{box-sizing:border-box;flex:0 0 252px;width:252px;height:100vh;position:sticky;top:0;overflow-y:auto;background:linear-gradient(180deg,#061327,#071b37);border-right:1px solid #8c713c;padding:16px 11px;z-index:9991;font:14px/1.5 system-ui,Arial,sans-serif}.lf2-brand{display:flex;gap:10px;align-items:center;padding:8px 10px 20px;color:#f5cf7c!important;font-size:21px;font-weight:800;text-decoration:none!important}.lf2-brand img{width:42px;height:42px;object-fit:contain}.lf2-nav{display:flex;flex-direction:column;gap:4px}.lf2-nav a,.lf2-back{display:block;padding:11px 12px;color:#f2f4fa!important;text-decoration:none!important;border:1px solid transparent;border-radius:10px}.lf2-nav a:hover,.lf2-nav a.lf2-active{background:#183555!important;border-color:#d2a64f!important;color:#ffe19b!important}.lf2-back{margin-top:14px;border-top:1px solid #34425c;border-radius:0}.lf2-content{min-width:0;flex:1;padding:18px 22px;box-sizing:border-box;overflow-x:auto}.lf2-content :is(a){color:#f3c86f}.lf2-toggle{display:none;background:#d5a64d;color:#08152a;border:0;border-radius:9px;padding:10px 16px;font-weight:700;margin-bottom:14px;cursor:pointer}.lf2-overlay{display:none}@media(max-width:850px){.lf2-sidebar{position:fixed;transform:translateX(-110%);transition:transform .2s ease;box-shadow:0 0 30px #0008}.lf2-sidebar.lf2-open{transform:translateX(0)}.lf2-toggle{display:block}.lf2-overlay.lf2-open{display:block;position:fixed;inset:0;background:#0009;z-index:9990}.lf2-content{padding:14px;max-width:100vw}}
</style><style id="lf-current-admin-gold-v4">
/* Scoped presentation only. Existing forms, routes, data, and sidebar preserved. */
.lf2-content{background:#061327!important;color:#f1f4fb!important;font-family:system-ui,Arial,sans-serif!important;line-height:1.55}
.lf2-content :is(main,section,.card,.panel,.container,.box,.content,.wrap,.wrapper){box-sizing:border-box;background:#0b1c35!important;color:#edf3ff!important;border-color:#b58d49!important;border-radius:14px!important}
.lf2-content :is(main,section,.card,.panel){padding:clamp(14px,2vw,26px)!important;margin-bottom:16px!important;border:1px solid #b58d49!important;box-shadow:0 10px 28px #0002!important}
.lf2-content :is(h1,h2,h3,h4,h5,h6){color:#ffe4a1!important;line-height:1.35!important}
.lf2-content :is(p,small,label,legend,dt,dd,li){color:#e3ebf8!important}
.lf2-content :is(a,a:visited){color:#f3c86e!important}
.lf2-content :is(a:hover){color:#ffe9ad!important}
.lf2-content :is(input:not([type=checkbox]):not([type=radio]):not([type=hidden]),select,textarea){background:#f7f9fd!important;color:#12253e!important;border:1px solid #c6aa75!important;border-radius:7px!important;max-width:100%;padding:9px!important;box-sizing:border-box}
.lf2-content :is(input,select,textarea):focus-visible{outline:2px solid #ffd67e!important;outline-offset:2px}
.lf2-content :is(button,input[type=submit]){border-radius:8px!important;padding:9px 14px!important;border:1px solid #bc9047!important}
.lf2-content :is(button,input[type=submit]):not(:disabled){background:linear-gradient(110deg,#ffe9a4,#c99e54)!important;color:#10233c!important;font-weight:700!important}
.lf2-content :is(button,input[type=submit]):disabled{opacity:.6!important;cursor:not-allowed!important}
.lf2-content table{width:100%;border-collapse:collapse;background:#0b1c35!important;color:#eaf1ff!important}
.lf2-content :is(th,td){padding:10px!important;border-bottom:1px solid #40536d!important;color:#eaf1ff!important}
.lf2-content th{background:#17304f!important;color:#ffe2a1!important}
.lf2-content tr{background:transparent!important}
.lf2-content tr:hover{background:#142e4b!important}
.lf2-content :is(.table-responsive,.table-wrap,.table-wrapper){overflow-x:auto}
.lf2-content :is(.notice,.warning,.alert,.error,.success){border-radius:10px!important;padding:12px!important}
.lf2-content :is(.notice,.warning){background:#fff4df!important;color:#743c11!important;border:1px solid #d9a65c!important}
.lf2-content :is(.notice,.warning) :is(p,span,strong,small){color:#743c11!important}
.lf2-content :is(.error){background:#4a2029!important;color:#ffe1e5!important}
.lf2-content :is(.success){background:#173d32!important;color:#dfffee!important}
@media(max-width:760px){.lf2-content{padding:12px!important}.lf2-content :is(main,section,.card,.panel){padding:14px!important}}
</style>
</head>
<body>
<div class="lf2-shell"><div class="lf2-overlay" id="lf2Overlay" onclick="lf2Toggle(false)"></div><aside class="lf2-sidebar" id="lf2Sidebar" aria-label="Admin navigation"><a class="lf2-brand" href="{{ route('admin.dashboard') }}"><img src="{{ route('lumiflowon.brand.logo') }}" alt="LumiFlowOn"><span>LumiFlowOn</span></a><nav class="lf2-nav">
<a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'lf2-active' : '' }}">▦ &nbsp; Dashboard</a>
<a href="{{ route('admin.members.index') }}" class="{{ request()->routeIs('admin.members.*') ? 'lf2-active' : '' }}">♙ &nbsp; Members</a>
<a href="{{ route('admin.magic.index') }}" class="{{ request()->routeIs('admin.magic.*') ? 'lf2-active' : '' }}">◇ &nbsp; Magic Matrix</a>
<a href="{{ route('admin.withdrawals.index') }}" class="{{ request()->routeIs('admin.withdrawals.*') ? 'lf2-active' : '' }}">▣ &nbsp; Withdrawals</a>
<a href="{{ route('admin.income.index') }}" class="{{ request()->routeIs('admin.income.*') ? 'lf2-active' : '' }}">▤ &nbsp; Income Settings</a>
<a href="{{ route('admin.unified-funding.index') }}" class="{{ request()->routeIs('admin.unified-funding.*') ? 'lf2-active' : '' }}">▥ &nbsp; Funding Audit</a>
<a href="{{ route('admin.registration-otp.index') }}" class="{{ request()->routeIs('admin.registration-otp.*') ? 'lf2-active' : '' }}">✉ &nbsp; Registration Email OTP</a>
<a href="{{ route('admin.lumiflowon.logo.index') }}" class="{{ request()->routeIs('admin.lumiflowon.logo.*') ? 'lf2-active' : '' }}">▧ &nbsp; Logo Management</a>
<a href="{{ route('admin.password-otp.index') }}" class="{{ request()->routeIs('admin.password-otp.*') ? 'lf2-active' : '' }}">◉ &nbsp; Password OTP</a>
<a href="{{ route('admin.referral-privacy.index') }}" class="{{ request()->routeIs('admin.referral-privacy.*') ? 'lf2-active' : '' }}">◈ &nbsp; Referral Privacy</a>
<a href="{{ route('admin.quick-links.index') }}" class="{{ request()->routeIs('admin.quick-links.*') ? 'lf2-active' : '' }}">↗ &nbsp; Quick Links</a>
</nav><a class="lf2-back" href="{{ route('admin.dashboard') }}">← Admin Dashboard</a></aside><main class="lf2-content"><button class="lf2-toggle" type="button" onclick="lf2Toggle(true)">☰ Admin Menu</button>

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
</main></div><script>function lf2Toggle(open){document.getElementById('lf2Sidebar')?.classList.toggle('lf2-open',!!open);document.getElementById('lf2Overlay')?.classList.toggle('lf2-open',!!open)}</script></body>
</html>