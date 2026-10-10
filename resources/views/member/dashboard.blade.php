<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Member Dashboard | LeoBot</title>
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #090d16; color: #e2e8f0; font: 15px/1.55 Arial, sans-serif; }
        .container { width: min(1160px, 94%); margin: 0 auto; padding: 28px 0 55px; }
        header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 25px; }
        .brand { font-size: 25px; font-weight: 800; color: #38bdf8; letter-spacing: .05em; }
        .brand small { font-size: 13px; display: block; font-weight: 400; color: #94a3b8; letter-spacing: 0; }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        .action { border-radius: 9px; border: 1px solid #334155; background: #192335; color: #e2e8f0; padding: 10px 15px; text-decoration: none; font: inherit; cursor: pointer; }
        .action:hover, .action:focus-visible { border-color: #38bdf8; }
        h1 { font-size: clamp(25px, 4vw, 34px); margin: 0 0 5px; }
        .subheading, .muted { color: #94a3b8; }
        .subheading { margin: 0 0 24px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 15px; }
        .card { border: 1px solid #263244; background: #111827; border-radius: 14px; padding: 19px; min-width: 0; }
        .label { color: #94a3b8; font-size: 13px; }
        .value { font-size: clamp(19px, 2.6vw, 25px); font-weight: 700; margin-top: 8px; overflow-wrap: anywhere; }
        .unit { font-size: 13px; color: #94a3b8; }
        section { margin-top: 27px; }
        h2 { font-size: 20px; margin: 0 0 12px; }
        .table-wrap { overflow-x: auto; border: 1px solid #263244; border-radius: 14px; }
        table { width: 100%; border-collapse: collapse; background: #111827; }
        th, td { padding: 12px 16px; border-bottom: 1px solid #263244; text-align: left; white-space: nowrap; }
        th { background: #172235; color: #cbd5e1; font-size: 13px; }
        tr:last-child td { border-bottom: 0; }
        .notice { margin-top: 25px; border: 1px solid #334155; background: #111827; padding: 15px; border-radius: 10px; color: #94a3b8; font-size: 13px; }
        .success { color: #86efac; }
    </style>
</head>
<body>
<main class="container">
    <header>
        <div class="brand">LEOBOT <small>Member Dashboard</small></div>
        <nav class="actions" aria-label="Member actions">
            <a class="action" href="{{ route('security-pin.form') }}">Change Security PIN</a>
            <form method="POST" action="{{ route('logout') }}" style="margin:0">
                @csrf
                <button class="action" type="submit">Logout</button>
            </form>
        </nav>
    </header>

    <h1>Welcome, {{ $member->name }}</h1>
    <p class="subheading">Member ID: {{ $member->username }} · Account status: {{ ucfirst($member->status) }}</p>

    @if (session('success'))
        <p class="success" role="status">{{ session('success') }}</p>
    @endif

    <section aria-label="Member financial summary">
        <h2>Your Account Summary</h2>
        <div class="grid">
            <div class="card"><div class="label">Available Balance</div><div class="value">{{ bcadd((string) ($member->available_balance ?? '0'), '0', 8) }}</div><div class="unit">USDT</div></div>
            <div class="card"><div class="label">Activation Balance</div><div class="value">{{ bcadd((string) ($member->activation_balance ?? '0'), '0', 8) }}</div><div class="unit">USDT</div></div>
            <div class="card"><div class="label">Total Investment</div><div class="value">{{ bcadd((string) ($member->total_investment ?? '0'), '0', 8) }}</div><div class="unit">USDT</div></div>
            <div class="card"><div class="label">Lifetime Income</div><div class="value">{{ bcadd((string) ($member->lifetime_income ?? '0'), '0', 8) }}</div><div class="unit">USDT</div></div>
        </div>
    </section>

    <section aria-label="Team summary">
        <h2>Your Team Overview</h2>
        <div class="grid">
            <div class="card"><div class="label">Active Team</div><div class="value">{{ (int) ($teamSummary->total_active_team ?? 0) }}</div></div>
            <div class="card"><div class="label">Inactive Team</div><div class="value">{{ (int) ($teamSummary->total_inactive_team ?? 0) }}</div></div>
            <div class="card"><div class="label">Total Team Business</div><div class="value">{{ bcadd((string) ($teamSummary->total_team_business ?? '0'), '0', 8) }}</div><div class="unit">USDT</div></div>
        </div>
    </section>

    <section aria-label="51-level team breakdown">
        <h2>51-Level Team Breakdown</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th scope="col">Level</th><th scope="col">Active</th><th scope="col">Inactive</th><th scope="col">Team Business (USDT)</th></tr></thead>
                <tbody>
                @for ($level = 1; $level <= 51; $level++)
                    @php($entry = $levelRows->get($level))
                    <tr>
                        <td>Level {{ $level }}</td>
                        <td>{{ (int) ($entry->active_count ?? 0) }}</td>
                        <td>{{ (int) ($entry->inactive_count ?? 0) }}</td>
                        <td>{{ bcadd((string) ($entry->total_business ?? '0'), '0', 8) }}</td>
                    </tr>
                @endfor
                </tbody>
            </table>
        </div>
    </section>
    <p class="notice">Read-only dashboard. Values reflect currently recorded data; no transfer, activation, trading funding or income payout is performed by this page. Security PIN setup is an access step, not financial transaction authorization.</p>
</main>
</body>
</html>
