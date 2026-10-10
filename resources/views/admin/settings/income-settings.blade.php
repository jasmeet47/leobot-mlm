<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Income Settings - LeoBot Admin</title>

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
</head>

<body>

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

    <h1>LeoBot Income Settings</h1>

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
            <h2>SELF Trading Profit Share — Default 67%</h2>
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
                    <strong>{{ $safetySwitchStates['combined'] ? 'ENABLED (review required)' : 'OFF — testing only' }}</strong>.
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

</body>
</html>
