
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
            line-height: 1.5;
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
        Admin Control Panel - ROI and Magic Income
    </p>

    @if(session('success'))
        <div class="success">
            {{ session('success') }}
        </div>
    @endif

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

    <div class="warning">
        <strong>Important:</strong>
        These settings do not distribute money.
        ROI and Magic Income payout systems will remain
        disabled until secure distribution logic,
        financial ledger checks and payout approval
        are implemented.
    </div>

    <form method="POST"
          action="{{ route('admin.income.update') }}">

        @csrf

        <!-- GLOBAL ROI SETTINGS -->

        <div class="card">

            <h2>1. Global ROI Settings</h2>

            <div class="field">

                <label for="roi_enabled">
                    ROI System
                </label>

                <select name="roi_enabled"
                        id="roi_enabled"
                        required>

                    <option value="0"
                        @selected(
                            (string) old(
                                'roi_enabled',
                                (int) $roiSettings->roi_enabled
                            ) === '0'
                        )>
                        OFF
                    </option>

                    <option value="1"
                        @selected(
                            (string) old(
                                'roi_enabled',
                                (int) $roiSettings->roi_enabled
                            ) === '1'
                        )>
                        ON
                    </option>

                </select>

                <p class="help">
                    ROI starts OFF by default.
                    Turning it ON will not start payouts
                    until the new distribution system
                    is implemented.
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

        <!-- MAGIC INCOME SETTINGS -->

        <div class="card">

            <h2>2. Magic Income Settings</h2>

            <div class="field">

                <label for="magic_enabled">
                    Magic Income System
                </label>

                <select name="magic_enabled"
                        id="magic_enabled"
                        required>

                    <option value="0"
                        @selected(
                            (string) old(
                                'magic_enabled',
                                (int) $magicSettings->magic_enabled
                            ) === '0'
                        )>
                        OFF
                    </option>

                    <option value="1"
                        @selected(
                            (string) old(
                                'magic_enabled',
                                (int) $magicSettings->magic_enabled
                            ) === '1'
                        )>
                        ON
                    </option>

                </select>

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

                    A member must have at least one ACTIVE
                    Activation Package strictly greater
                    than this amount.

                    Example: 50 USDT is not eligible,
                    but 60 USDT is eligible.

                    Multiple smaller packages
                    cannot be combined.
                </p>

            </div>

            <div class="field">

                <label for="trading_profit_pool_percent">
                    Magic Pool Percentage of Trading Profit (%)
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

                    Admin can change it to 2%, 5%,
                    10% or another valid percentage.

                    Example: Trading Profit 1,000 USDT
                    at 5% creates a 50 USDT Magic Pool.

                    Trading Profit manual entry and
                    Direct Magic Pool funding will
                    be implemented separately.
                </p>

            </div>

        </div>

        <button type="submit">
            Save Income Settings
        </button>

    </form>

    <!-- PACKAGE-WISE SETTINGS -->

    <div class="card" style="margin-top: 20px;">

        <strong>Package-wise Controls</strong>

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

    <!-- AUDIT HISTORY INFORMATION -->

    <div class="card">

        <h2>3. Admin Audit History</h2>

        <p class="help">
            View who changed ROI and Magic Income
            settings, when they changed them,
            and the previous and new values.
        </p>

        <a class="history-link"
           href="{{ route('admin.income.history') }}"
           style="display: inline-block;
                  padding: 12px 18px;
                  border-radius: 8px;
                  text-decoration: none;">
            View Audit History
        </a>

    </div>

</div>

</body>
</html>
