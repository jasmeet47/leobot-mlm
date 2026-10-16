<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LeoBot MLM - Admin Dashboard</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f6fb;
            color: #1e293b;
        }

        .header {
            background: #142b52;
            color: white;
            padding: 22px 28px;
        }

        .header-inner {
            max-width: 1150px;
            margin: auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            flex-wrap: wrap;
        }

        .brand {
            font-size: 25px;
            font-weight: bold;
        }

        .subtitle {
            font-size: 13px;
            color: #cbd5e1;
            margin-top: 5px;
        }

        .logout {
            padding: 10px 17px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 7px;
            cursor: pointer;
        }

        .container {
            max-width: 1150px;
            margin: 35px auto;
            padding: 0 20px;
        }

        .welcome {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            border: 1px solid #e2e8f0;
        }

        h1 {
            margin: 0 0 12px;
            font-size: 27px;
        }

        .notice {
            background: #fff8e7;
            border: 1px solid #f2d99c;
            border-radius: 8px;
            padding: 14px 18px;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(270px, 1fr));
            gap: 20px;
        }

        .card {
            background: white;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            padding: 24px;
        }

        .card h2 {
            font-size: 19px;
            margin: 0 0 12px;
        }

        .card p {
            font-size: 14px;
            color: #64748b;
            line-height: 1.6;
            min-height: 48px;
        }

        .card a {
            display: inline-block;
            margin-top: 12px;
            padding: 11px 16px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 7px;
            font-size: 14px;
        }

        .card a:hover {
            background: #1d4ed8;
        }

        .footer {
            text-align: center;
            padding: 30px 15px;
            color: #64748b;
            font-size: 13px;
        }
    </style>
</head>

<body>

<header class="header">
    <div class="header-inner">
        <div>
            <div class="brand">LEOBOT MLM</div>
            <div class="subtitle">Secure Administration Panel</div>
        </div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="logout" type="submit">
                Logout
            </button>
        </form>
    </div>
</header>

<main class="container">

    <section class="welcome">
        <h1>Admin Dashboard</h1>

        <p>
            Welcome,
            <strong>{{ auth()->user()->username }}</strong>
        </p>

        <p>
            Manage LeoBot settings and review audit history.
        </p>
    </section>

    <div class="notice">
        <strong>Financial Safety Notice</strong>
        <div>
            Trading funding, member activation and payouts
            must remain disabled until separately approved
            and verified.
        </div>
        <div>
            This dashboard does not enable financial operations.
        </div>
    </div>

    <section class="grid">
    <div class="card">
        <h2>Unified Funding Audit</h2>
        <p>Review 67/30/3 internal reserves. Funding entry remains disabled without separate approval. No payouts.</p>
        <a href="{{ route('admin.unified-funding.index') }}">Review Funding</a>
    </div>

        <div class="card">
            <h2>51-Level Commission</h2>
            <p>
                View and manage generation commission
                configuration across 51 levels.
            </p>
            <a href="{{ route('admin.levels.index') }}">
                Open Settings
            </a>
        </div>

        <div class="card">
            <h2>Commission Audit History</h2>
            <p>
                Review recorded changes to the
                generation commission configuration.
            </p>
            <a href="{{ route('admin.levels.history') }}">
                View History
            </a>
        </div>

        <div class="card">
            <h2>Income Settings</h2>
            <p>
                Review Self Income, Generation Income,
                Magic Income and related settings.
            </p>
            <a href="{{ route('admin.income.index') }}">
                Open Settings
            </a>
        </div>

        <div class="card">
            <h2>Income Audit History</h2>
            <p>
                Review recorded changes to
                income-related configuration.
            </p>
            <a href="{{ route('admin.income.history') }}">
                View History
            </a>
        </div>

        <div class="card">
            <h2>Referral Privacy</h2>
            <p>
                Manage direct referral contact
                privacy and consent settings.
            </p>
            <a href="{{ route('admin.referral-privacy.index') }}">
                Open Settings
            </a>
        </div>

        <div class="card">
            <h2>Member Management</h2>
            <p>
                View registered members, search usernames,
                and review sponsor and account status.
                View member details and audited block/unblock controls.
            </p>
            <a href="{{ route('admin.members.index') }}">
                View Members
            </a>
        </div>
    </section>

</main>

<footer class="footer">
    LeoBot MLM - Admin Dashboard
</footer>

</body>
</html>