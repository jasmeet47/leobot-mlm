<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Income Settings - LumiFlowOn Admin</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 25px;
            background: #f3f5f9;
            font-family: Arial, sans-serif;
            color: #1f2937;
        }

        .container {
            max-width: 850px;
            margin: 0 auto;
        }

        h1 {
            margin-bottom: 8px;
        }

        .subtitle {
            color: #64748b;
            margin-bottom: 25px;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.06);
        }

        .card h2 {
            margin-top: 0;
            padding-bottom: 12px;
            border-bottom: 1px solid #e5e7eb;
        }

        .field {
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
        }

        input,
        select {
            display: block;
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            font-size: 15px;
            background: white;
        }

        .help {
            color: #64748b;
            font-size: 13px;
            margin-top: 6px;
            line-height: 1.6;
        }

        .success {
            background: #dcfce7;
            color: #166534;
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .warning {
            background: #fff7ed;
            color: #9a3412;
            border: 1px solid #fed7aa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .top-links {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 22px;
        }

        .top-links a {
            display: inline-block;
            padding: 12px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            font-size: 14px;
        }

        .back-link {
            background: #e2e8f0;
            color: #334155;
        }

        .history-link {
            background: #0f766e;
            color: white;
        }

        button {
            width: 100%;
            padding: 15px;
            background: #1d4ed8;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #1e40af;
        }

        @media (max-width: 600px) {
            body {
                padding: 12px;
            }

            .card {
                padding: 18px;
            }
        }
    </style>
<style id="lumiflowon-shared-admin-sidebar-v2">
html,body{min-height:100%;}body{margin:0!important;background:#061327!important;color:#edf3ff!important}.lf2-shell{display:flex;min-height:100vh;width:100%;align-items:stretch}.lf2-sidebar{box-sizing:border-box;flex:0 0 252px;width:252px;height:100vh;position:sticky;top:0;overflow-y:auto;background:linear-gradient(180deg,#061327,#071b37);border-right:1px solid #8c713c;padding:16px 11px;z-index:9991;font:14px/1.5 system-ui,Arial,sans-serif}.lf2-brand{display:flex;gap:10px;align-items:center;padding:8px 10px 20px;color:#f5cf7c!important;font-size:21px;font-weight:800;text-decoration:none!important}.lf2-brand img{width:42px;height:42px;object-fit:contain}.lf2-nav{display:flex;flex-direction:column;gap:4px}.lf2-nav a,.lf2-back{display:block;padding:11px 12px;color:#f2f4fa!important;text-decoration:none!important;border:1px solid transparent;border-radius:10px}.lf2-nav a:hover,.lf2-nav a.lf2-active{background:#183555!important;border-color:#d2a64f!important;color:#ffe19b!important}.lf2-back{margin-top:14px;border-top:1px solid #34425c;border-radius:0}.lf2-content{min-width:0;flex:1;padding:18px 22px;box-sizing:border-box;overflow-x:auto}.lf2-content :is(a){color:#f3c86f}.lf2-toggle{display:none;background:#d5a64d;color:#08152a;border:0;border-radius:9px;padding:10px 16px;font-weight:700;margin-bottom:14px;cursor:pointer}.lf2-overlay{display:none}@media(max-width:850px){.lf2-sidebar{position:fixed;transform:translateX(-110%);transition:transform .2s ease;box-shadow:0 0 30px #0008}.lf2-sidebar.lf2-open{transform:translateX(0)}.lf2-toggle{display:block}.lf2-overlay.lf2-open{display:block;position:fixed;inset:0;background:#0009;z-index:9990}.lf2-content{padding:14px;max-width:100vw}}
</style><style id="LUMIFLOWON_INCOME_CONTRAST_V1">
main .bg-white,
main [class~="bg-white"] {
    color: #172b45 !important;
}
main .bg-white h1,
main .bg-white h2,
main .bg-white h3,
main .bg-white label,
main .bg-white strong,
main .bg-white p {
    color: #172b45 !important;
}
main .bg-white input,
main .bg-white select,
main .bg-white textarea {
    color: #172b45 !important;
    background-color: #ffffff !important;
}
</style>
<style id="lf-current-admin-gold-v4">
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
<a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'lf2-active' : '' }}">â–¦ &nbsp; Dashboard</a>
<a href="{{ route('admin.members.index') }}" class="{{ request()->routeIs('admin.members.*') ? 'lf2-active' : '' }}">â™™ &nbsp; Members</a>
<a href="{{ route('admin.magic.index') }}" class="{{ request()->routeIs('admin.magic.*') ? 'lf2-active' : '' }}">â—‡ &nbsp; Magic Matrix</a>
<a href="{{ route('admin.withdrawals.index') }}" class="{{ request()->routeIs('admin.withdrawals.*') ? 'lf2-active' : '' }}">â–£ &nbsp; Withdrawals</a>
<a href="{{ route('admin.income.index') }}" class="{{ request()->routeIs('admin.income.*') ? 'lf2-active' : '' }}">â–¤ &nbsp; Income Settings</a>
<a href="{{ route('admin.unified-funding.index') }}" class="{{ request()->routeIs('admin.unified-funding.*') ? 'lf2-active' : '' }}">â–¥ &nbsp; Funding Audit</a>
<a href="{{ route('admin.registration-otp.index') }}" class="{{ request()->routeIs('admin.registration-otp.*') ? 'lf2-active' : '' }}">âœ‰ &nbsp; Registration Email OTP</a>
<a href="{{ route('admin.lumiflowon.logo.index') }}" class="{{ request()->routeIs('admin.lumiflowon.logo.*') ? 'lf2-active' : '' }}">â–§ &nbsp; Logo Management</a>
<a href="{{ route('admin.password-otp.index') }}" class="{{ request()->routeIs('admin.password-otp.*') ? 'lf2-active' : '' }}">â—‰ &nbsp; Password OTP</a>
<a href="{{ route('admin.referral-privacy.index') }}" class="{{ request()->routeIs('admin.referral-privacy.*') ? 'lf2-active' : '' }}">â—ˆ &nbsp; Referral Privacy</a>
<a href="{{ route('admin.quick-links.index') }}" class="{{ request()->routeIs('admin.quick-links.*') ? 'lf2-active' : '' }}">â†— &nbsp; Quick Links</a>
</nav><a class="lf2-back" href="{{ route('admin.dashboard') }}">â† Admin Dashboard</a></aside><main class="lf2-content"><button class="lf2-toggle" type="button" onclick="lf2Toggle(true)">â˜° Admin Menu</button>


<div class="container">

    <!-- ADMIN NAVIGATION -->

    <div class="top-links">

        <a class="back-link"
           href="{{ route('admin.levels.index') }}">
            &larr; 51-Level Settings
        </a>

        <a class="history-link"
           href="{{ route('admin.income.history') }}">
            View Audit History
        </a>

    </div>

    <h1>LumiFlowOn Income Settings</h1>

    <p class="subtitle">
        Admin Control Panel - ROI, Magic Income,
        51-Level Generation and Investment Distribution
    </p>

    <!-- SUCCESS MESSAGE -->

    @if(session('success'))
        <div class="success">
            {{ session('success') }}
        </div>
    @endif

    <!-- VALIDATION ERRORS -->

    @if($errors->any())
        <div class="error">

            <strong>Please correct these errors:</strong>

            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>

        </div>
    @endif

    <!-- SAFETY WARNING -->

    <div class="warning">
        <strong>Important:</strong>

        These settings only control configuration.

        ROI, Magic Income, 51-Level Generation and Investment
        Distribution payouts are locked OFF by this screen until their
        engines, ledger checks, qualification rules and funding
        controls are fully implemented and tested.

        ROI, Magic, Generation and Investment Distribution are locked OFF by this screen.

        Saving these settings does not distribute money.
    </div>

    <!-- MAIN SETTINGS FORM -->

    <form method="POST"
          action="{{ route('admin.income.update') }}">

        @csrf

        <!-- ===================================== -->
        <!-- 1. ROI SETTINGS -->
        <!-- ===================================== -->

        <div class="card">

            <h2>1. Global ROI Settings</h2>

            <div class="field">

                <label for="roi_enabled">
                    ROI System
                </label>

                <input type="hidden" name="roi_enabled" value="0">
                <p class="help">
                    ROI payout switch: <strong>LOCKED OFF</strong>.
                    Current database value:
                    <strong>{{ $safetySwitchStates['roi'] ? 'ON (save to disable)' : 'OFF' }}</strong>.
                    Saving this form sets this switch OFF.
                </p>

                <p class="help">
                    ROI starts OFF by default.
                    A separate payout engine is required.
                </p>

            </div>

            <div class="field">

                <label for="distribution_mode">
                    ROI Distribution Mode
                </label>

                @php
                    $selectedMode = old(
                        'distribution_mode',
                        $roiSettings->distribution_mode
                    );
                @endphp

                <select name="distribution_mode"
                        id="distribution_mode"
                        required>

                    <option value="daily"
                        @selected($selectedMode === 'daily')>
                        Daily
                    </option>

                    <option value="weekly"
                        @selected($selectedMode === 'weekly')>
                        Weekly
                    </option>

                    <option value="monthly"
                        @selected($selectedMode === 'monthly')>
                        Monthly
                    </option>

                    <option value="manual"
                        @selected($selectedMode === 'manual')>
                        Manual
                    </option>

                </select>

            </div>

            <div class="field">

                <label for="default_roi_percentage">
                    Default ROI Percentage (%)
                </label>

                <input
                    type="number"
                    name="default_roi_percentage"
                    id="default_roi_percentage"
                    min="0"
                    max="100"
                    step="0.000000001"
                    required
                    value="{{ old(
                        'default_roi_percentage',
                        $roiSettings->default_roi_percentage
                    ) }}"
                >

                <p class="help">
                    Example: Enter 0.20 for 0.20%.

                    Package-specific ROI percentages
                    will be configured separately.
                </p>

            </div>

        </div>

        <!-- ===================================== -->
        <!-- 2. MAGIC INCOME SETTINGS -->
        <!-- ===================================== -->

        <div class="card">

            <h2>2. Magic Income Settings</h2>

            <div class="field">

                <label for="magic_enabled">
                    Magic Income System
                </label>

                <input type="hidden" name="magic_enabled" value="0">
                <p class="help">
                    Magic payout switch: <strong>LOCKED OFF</strong>.
                    Current database value:
                    <strong>{{ $safetySwitchStates['magic'] ? 'ON (save to disable)' : 'OFF' }}</strong>.
                    Saving this form sets this switch OFF.
                </p>

                <p class="help">
                    Magic Income starts OFF until
                    the payout system is fully tested.
                </p>

            </div>

            <div class="field">

                <label for="minimum_activation_usdt">
                    Minimum Activation Threshold (USDT)
                </label>

                <input
                    type="number"
                    name="minimum_activation_usdt"
                    id="minimum_activation_usdt"
                    min="0"
                    step="0.00000001"
                    required
                    value="{{ old(
                        'minimum_activation_usdt',
                        $magicSettings->minimum_activation_usdt
                    ) }}"
                >

                <p class="help">
                    Default: 50 USDT.

                    A member must have at least one
                    ACTIVE activation package strictly
                    greater than this threshold.

                    Multiple smaller packages
                    cannot be combined.
                </p>

            </div>

            <div class="field">

                <label for="trading_profit_pool_percent">
                    Magic Pool Percentage
                    of Trading Profit (%)
                </label>

                <input
                    type="number"
                    name="trading_profit_pool_percent"
                    id="trading_profit_pool_percent"
                    min="0"
                    max="100"
                    step="0.000000001"
                    required
                    value="{{ old(
                        'trading_profit_pool_percent',
                        $magicSettings->trading_profit_pool_percent
                    ) }}"
                >

                <p class="help">
                    Default: 3%.

                    Admin can change this to 2%, 5%,
                    7%, 10% or another valid percentage.

                    Example: Trading Profit 1,000 USDT
                    at 5% creates a 50 USDT Magic Pool.
                </p>

            </div>

        </div>

        <!-- ===================================== -->
        <!-- 3. 51-LEVEL GENERATION POOL -->
        <!-- ===================================== -->

        <div class="card">

            <h2>3. 51-Level Generation Pool Settings</h2>

            <div class="field">

                <label for="generation_enabled">
                    Generation Income System
                </label>

                <input type="hidden" name="generation_enabled" value="0">
                <p class="help">
                    Generation payout switch: <strong>LOCKED OFF</strong>.
                    Current database value:
                    <strong>{{ $safetySwitchStates['generation'] ? 'ON (save to disable)' : 'OFF' }}</strong>.
                    Saving this form sets this switch OFF.
                </p>

                <p class="help">
                    Default: OFF.

                    Keep OFF until the new 51-Level
                    Commission Engine is implemented
                    and has passed all safety tests.
                </p>

            </div>

            <div class="field">

                <label for="generation_pool_percent">
                    Generation Pool Percentage
                    of Trading Profit (%)
                </label>

                <input
                    type="number"
                    name="generation_pool_percent"
                    id="generation_pool_percent"
                    min="0"
                    max="100"
                    step="0.000000001"
                    required
                    value="{{ old(
                        'generation_pool_percent',
                        $generationSettings->trading_profit_pool_percent
                    ) }}"
                >

                <p class="help">
                    Default: 30%.

                    Admin can change this to 15%, 25%,
                    35% or another valid percentage.

                    Example: Trading Profit 1,000 USDT
                    at 30% creates a 300 USDT
                    Generation Pool.

                    The Generation Pool will be
                    distributed according to the
                    configured 51-Level rates.

                    Unqualified commissions will be
                    allocated to a separate Held Pool.
                </p>

            </div>

            <div class="warning">
                <strong>Pool Safety Rule:</strong>

                SELF Share + Generation Pool + Magic Pool
                must equal exactly 100% of the same Trading Profit.

                The server validates this rule
                before saving settings.
            </div>

        </div>

        <!-- VERIFIED TRADING PROFIT SELF SHARE -->
        <div class="card">
            <h2>SELF Trading Profit Share Î“Ã‡Ã¶ Default 67%</h2>
            <div class="field">
                <label for="self_profit_percent">SELF Profit Percentage (%)</label>
                <input type="number"
                       name="self_profit_percent"
                       id="self_profit_percent"
                       min="0" max="100" step="0.000000001" required
                       value="{{ old('self_profit_percent', $profitSharingSettings->self_profit_percent) }}">
                <p class="help">
                    The verified profit owner gets a separately reserved SELF share.
                    SELF + 51-Level Generation + Magic percentages must total exactly 100%.
                    This form changes settings only: no wallet payout is made.
                </p>
                <p class="help">
                    Unified company-profit funding:
                    <strong>{{ $safetySwitchStates['combined'] ? 'ENABLED (review required)' : 'OFF Î“Ã‡Ã¶ testing only' }}</strong>.
                    This screen does NOT enable it.
                </p>
            </div>
        </div>

        <!-- ===================================== -->
        <!-- 4. INVESTMENT DISTRIBUTION -->
        <!-- ===================================== -->

        <div class="card">

            <h2>4. Investment Distribution Settings</h2>

            <div class="field">
                <label>Investment Distribution Status</label>
                <p class="help">
                    Current database status:
                    <strong>{{ $safetySwitchStates['investment'] ? 'ON (review required)' : 'OFF' }}</strong>.
                    Automatic distribution is not implemented yet.
                    Saving this form will keep Investment Distribution OFF.
                </p>
                <input type="hidden"
                       name="investment_distribution_enabled"
                       value="0">
            </div>

            <div class="field">
                <label for="investment_distribution_percent">
                    Investment Distribution Budget (%)
                </label>

                <input type="number"
                       name="investment_distribution_percent"
                       id="investment_distribution_percent"
                       min="0"
                       max="100"
                       step="0.000000001"
                       required
                       value="{{ old(
                           'investment_distribution_percent',
                           $investmentSettings->distribution_percent
                       ) }}">

                <p class="help">
                    Default: 5%. Admin can choose 2%, 5%, 10% or
                    another valid percentage between 0 and 100.
                    This percentage determines the commission budget
                    from the investment amount, not a deduction from
                    the member's recorded principal.
                </p>
            </div>

            <div class="warning">
                <strong>Funding safety:</strong>
                This is a configuration setting only. It does not
                credit wallets or create commissions. Real distributions
                need verified company funds, member qualification,
                a Held/Unallocated Pool and auditable transactions.
            </div>

        </div>

        <!-- SAVE BUTTON -->

        <button type="submit">
            Save Income Settings
        </button>

    </form>

    <!-- ===================================== -->
    <!-- PACKAGE-WISE SETTINGS INFORMATION -->
    <!-- ===================================== -->

    <div class="card" style="margin-top: 20px;">

        <h2>Package-wise Controls</h2>

        <p class="help">
            Each Activation Package has separate
            ROI ON/OFF, ROI Percentage,
            ROI Distribution Mode,
            ROI Capping Multiplier and
            Magic Income Capping Multiplier.

            Their Admin management screen
            will be created separately.
        </p>

    </div>

    <!-- ===================================== -->
    <!-- AUDIT HISTORY -->
    <!-- ===================================== -->

    <div class="card">

        <h2>5. Admin Audit History</h2>

        <p class="help">
            View who changed ROI, Magic, SELF Income,
            51-Level Generation Pool and Investment Distribution settings.

            The history contains previous values,
            new values and the update time.
        </p>

        <a class="history-link"
           href="{{ route('admin.income.history') }}"
           style="
               display: inline-block;
               padding: 12px 18px;
               border-radius: 8px;
               text-decoration: none;
           ">
            View Audit History
        </a>

    </div>

</div>

</main></div><script>function lf2Toggle(open){document.getElementById('lf2Sidebar')?.classList.toggle('lf2-open',!!open);document.getElementById('lf2Overlay')?.classList.toggle('lf2-open',!!open)}</script></body>
</html>
