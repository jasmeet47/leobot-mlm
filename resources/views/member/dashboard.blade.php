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
<!-- LEOBOT_PREMIUM_UI_V1: presentation only; no market feed or invented prices -->
<style>
:root{--lb-bg:#070d19;--lb-panel:#101d30;--lb-panel2:#15243a;--lb-border:#293a53;--lb-text:#e8eff9;--lb-muted:#9caec4;--lb-green:#21c997;--lb-blue:#50a7ff}
body{background:radial-gradient(ellipse at 90% 0%,#153153 0%,#080f1e 43%,#060c16 100%) fixed!important;color:var(--lb-text)!important}
.container,main.container{max-width:1440px!important}
.lb-terminal{font-family:Inter,ui-sans-serif,system-ui,-apple-system,'Segoe UI',sans-serif;background:linear-gradient(125deg,#13233b,#0b1627 68%,#0b1e2d);border:1px solid #30445e;border-radius:22px;padding:clamp(18px,3vw,32px);margin:22px 0 28px;box-shadow:0 24px 65px #02081477;position:relative;overflow:hidden;color:#eef6ff}
.lb-terminal:before{content:'';position:absolute;width:260px;height:260px;right:-100px;top:-140px;background:#1875c82a;filter:blur(35px);border-radius:50%;pointer-events:none}
.lb-terminal *{box-sizing:border-box}.lb-terminal-head{display:flex;justify-content:space-between;align-items:center;gap:18px;flex-wrap:wrap}.lb-terminal-eyebrow{font-size:11px;letter-spacing:.21em;text-transform:uppercase;color:#6dbbff;font-weight:800}.lb-terminal h2{font-size:clamp(22px,2.5vw,32px);line-height:1.2;color:#f5faff;margin:9px 0}.lb-terminal p{color:#a8bbd2;margin:8px 0 0;line-height:1.6}.lb-terminal-status{display:inline-flex;align-items:center;gap:9px;border:1px solid #36516c;border-radius:999px;padding:9px 13px;font-size:12px;color:#d4e5f8;background:#142840;white-space:nowrap}.lb-terminal-dot{width:8px;height:8px;border-radius:50%;background:#21c997;box-shadow:0 0 0 4px #21c99725}.lb-terminal-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:24px}.lb-terminal-tile{border:1px solid #2a4059;background:#0a1729b8;border-radius:14px;padding:16px;min-height:105px}.lb-terminal-tile small{display:block;color:#9bb2ca;font-size:12px;margin-bottom:12px}.lb-terminal-tile strong{font-size:clamp(15px,1.6vw,22px);color:#f5faff}.lb-terminal-tile .accent{color:#40ddb0}.lb-terminal-foot{margin-top:16px!important;font-size:12px}.lb-terminal-note{color:#7fa1c3!important}.lb-terminal a{color:#87c8ff!important}
main section,main .card,main .stat-card,main .panel,main .notice{border-radius:14px}main table{width:100%}main th{letter-spacing:.025em}main a,main button{transition:filter .18s ease,transform .18s ease}main button:hover{filter:brightness(1.1)}
@media(max-width:720px){.lb-terminal-grid{grid-template-columns:1fr}.lb-terminal{padding:18px;border-radius:16px}.lb-terminal-tile{min-height:auto}.lb-terminal-status{white-space:normal}}
</style>
<style>
/* LEOBOT_MEMBER_PREMIUM_DARK_V1 */
body {
    background: radial-gradient(ellipse at 90% 0%, #173455 0%, #091424 45%, #060c16 100%) fixed !important;
    color: #e9f3ff !important;
    font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif !important;
}
main, main.container, .container, .dashboard-container, .dashboard {
    color: #e9f3ff !important;
}
main.container, .dashboard-container, .dashboard {
    background-color: #0d1b2e !important;
    border-color: #2a405b !important;
}
main h1, main h2, main h3,
.container h1, .container h2, .container h3 {
    color: #f1f7ff !important;
}
main p, main small, .container p, .container small {
    color: #b2c6dd !important;
}
main .card, main .panel, main .stat-card,
.container .card, .container .panel, .container .stat-card,
.dashboard .card {
    background: #13243a !important;
    color: #e9f3ff !important;
    border: 1px solid #2b435e !important;
    border-radius: 16px !important;
    box-shadow: 0 12px 30px rgba(0, 5, 15, .15) !important;
}
main .card h2, main .card h3,
.container .card h2, .container .card h3 {
    color: #f1f7ff !important;
}
main table, .container table {
    color: #e9f3ff !important;
    width: 100%;
}
main th, .container th {
    background: #1b304b !important;
    color: #f1f7ff !important;
}
main td, .container td {
    border-color: #30465f !important;
}
main input, main select, main textarea,
.container input, .container select, .container textarea {
    background: #0a1829 !important;
    color: #f1f7ff !important;
    border: 1px solid #38516d !important;
}
main a, .container a {
    transition: filter .2s ease;
}
main a:hover, .container a:hover {
    filter: brightness(1.12);
}
@media (max-width: 768px) {
    main.container, .dashboard-container, .dashboard {
        padding: 16px !important;
    }
    main table, .container table {
        display: block;
        overflow-x: auto;
    }
}
</style>
<style>
/* LEOBOT_MEMBER_MENU_V1 */
.lb-member-menu {
    position: relative;
}
.lb-member-menu > summary {
    list-style: none;
    display: inline-block;
    cursor: pointer;
}
.lb-member-menu > summary::-webkit-details-marker {
    display: none;
}
.lb-member-menu:not([open]) .lb-member-menu-panel {
    display: none;
}
.lb-member-menu-panel {
    position: absolute;
    right: 0;
    top: calc(100% + 10px);
    z-index: 100;
    width: min(270px, 85vw);
    display: flex;
    flex-direction: column;
    gap: 9px;
    padding: 14px;
    background: #101d30;
    border: 1px solid #30445e;
    border-radius: 14px;
    box-shadow: 0 16px 36px #0008;
}
.lb-member-menu-panel .action {
    display: block;
    width: 100%;
    text-align: left;
}
.lb-member-menu-panel form {
    margin: 0;
}
</style>
<style>
/* LEOBOT_INTERNATIONAL_TRADING_UI_V2: presentation only */
:root { --lb-gold:#e6bd6a; --lb-gold-soft:#f7dfaa; --lb-panel:#0e1b2c; --lb-line:#2b3e54; }
html { scroll-behavior:smooth; scroll-padding-top:22px; }
body { background:radial-gradient(ellipse at 15% -10%,#20344d 0%,transparent 40%),linear-gradient(150deg,#07101d,#0b1727 70%,#08111e) fixed!important; }
main.container { max-width:1400px!important; padding-top:18px; }
header { position:relative; z-index:20; padding:13px 17px; border:1px solid #2d4158; border-radius:18px; background:#0d1b2df2; box-shadow:0 12px 35px #03091665; }
.brand { color:var(--lb-gold)!important; font-size:22px; letter-spacing:.09em; }
.brand small { color:#91a7c0; }
.lb-terminal { border-color:#4b4b4a; background:linear-gradient(120deg,#172a40,#0b192b 75%); }
.lb-terminal-eyebrow,.lb-eyebrow { color:var(--lb-gold)!important; font-size:11px; font-weight:800; letter-spacing:.16em; }
.lb-terminal-status { border-color:#466253; }
.lb-terminal-tile { border-color:#30455b; background:#0c1b2ec9; }
.lb-terminal-tile .accent { color:#50d5ad; }
.lb-nav-groups { gap:8px; }
.lb-nav-groups>.lb-nav-dropdown>summary,.lb-home-link { border-color:#364c64; background:#14263c; font-size:13px; font-weight:700; }
.lb-nav-dropdown[open]>summary { color:var(--lb-gold-soft); border-color:var(--lb-gold); }
.lb-member-menu-panel { width:min(310px,92vw); background:#0c1a2b; border-color:#425773; padding:10px; max-height:65vh; overflow-y:auto; }
.lb-member-menu-panel .action { padding:10px 12px; font-size:13px; border:0; background:transparent; }
.lb-member-menu-panel .action:hover,.lb-member-menu-panel .action:focus-visible { background:#1b314a; color:var(--lb-gold-soft); }
.lb-nav-groups .lb-nav-dropdown:last-of-type .lb-member-menu-panel { right:0; }
main section { scroll-margin-top:24px; }
main section>h2 { padding-left:13px; border-left:3px solid var(--lb-gold); font-size:19px; }
main .card { border-color:#2d425a!important; background:linear-gradient(150deg,#13263c,#0e1d30)!important; }
main .card .value { color:#f2f7ff; font-variant-numeric:tabular-nums; }
main .card .label { color:#9eb2c9; }
.table-wrap { border-color:#30465c; }
main th { color:var(--lb-gold-soft)!important; }
main tr:hover td { background:#152a40; }
.lb-profile-strip { display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; padding:18px 22px; border:1px solid #35465a; background:#101f32; border-radius:15px; }
.lb-profile-strip>div { display:flex; flex-direction:column; gap:4px; }
.lb-profile-strip strong { font-size:19px; }
.lb-profile-strip small { color:#9aafc8; }
.lb-account-status,.lb-small-tag { color:#d6e4f5; background:#1a3047; border:1px solid #34506a; padding:7px 11px; border-radius:999px; font-size:12px; }
.lb-section-topline { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:12px; }
.lb-records-overview { padding:22px; border:1px solid #30465b; border-radius:16px; background:linear-gradient(110deg,#14263b,#0d1c2d); }
.lb-inline-link { display:inline-block; margin-top:8px; border-color:#78613c; color:var(--lb-gold-soft); }
.lb-bottom-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
.lb-info-card { scroll-margin-top:25px; }
.lb-info-card h2 { margin-top:10px; }
@media(max-width:900px) { .lb-nav-groups { width:100%; } header { align-items:flex-start; } }
@media(max-width:700px) { .lb-bottom-grid { grid-template-columns:1fr; } .lb-nav-groups { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); } .lb-nav-groups .lb-member-menu { min-width:0; } .lb-nav-groups .lb-member-menu>summary { width:100%; text-align:center; padding:10px 7px; } .lb-member-menu-panel { right:auto; left:0; width:min(300px,88vw); } .lb-nav-groups .lb-member-menu:nth-last-child(-n+2) .lb-member-menu-panel { right:0; left:auto; } }
@media(max-width:420px) { .lb-nav-groups { grid-template-columns:1fr 1fr; } .lb-home-link { text-align:center; } .lb-terminal-head { align-items:flex-start; } }
</style>
</head>
<body>
<main class="container">
<section class="lb-terminal" aria-label="LeoBot member financial dashboard">
  <div class="lb-terminal-head">
    <div><div class="lb-terminal-eyebrow">LEOBOT / MEMBER PORTAL</div><h2>Portfolio Overview</h2><p>Welcome, {{ $member->name }}. Your LeoBot account information is shown below.</p></div>
    <div class="lb-terminal-status"><span class="lb-terminal-dot"></span> Secure member session</div>
  </div>
  <div class="lb-terminal-grid">
    <div class="lb-terminal-tile"><small>Account</small><strong>Member Portal</strong></div>
    <div class="lb-terminal-tile"><small>Financial records</small><strong class="accent">Verified records only</strong></div>
    <div class="lb-terminal-tile"><small>Trading feed</small><strong>Not connected</strong></div>
  </div>
  <p class="lb-terminal-foot lb-terminal-note">International trading-inspired interface only. Income, wallet balances and history below are from LeoBot records, not MT5.</p>
</section>
    <header>
        <div class="brand">LEOBOT <small>Member Dashboard</small></div>
        <!-- LEOBOT_MEMBER_MENU_V2: anchored groups preserve all existing dashboard sections -->
        <nav class="actions lb-nav-groups" aria-label="Member actions">
            <a class="action lb-home-link" href="{{ route('member.dashboard') }}">Overview</a>
            <details class="lb-member-menu lb-nav-dropdown">
                <summary class="action">My Team <span aria-hidden="true">⌄</span></summary>
                <div class="lb-member-menu-panel">
                    <a class="action" href="#team-breakdown">51-Level Team Breakdown</a>
                    <a class="action" href="#direct-referrals">My Direct Referrals (Level 1)</a>
                    <a class="action" href="#referral-privacy">Referral Contact Privacy</a>
                    <a class="action" href="#team-overview">Your Team Overview</a>
                    <a class="action" href="#referral-link">Your Referral Link</a>
                </div>
            </details>
            <details class="lb-member-menu lb-nav-dropdown">
                <summary class="action">My Finance <span aria-hidden="true">⌄</span></summary>
                <div class="lb-member-menu-panel">
                    <a class="action" href="#wallet-history">My Wallet Transaction History</a>
                    <a class="action" href="#income-summary">My Income Summary</a>
                    <a class="action" href="#financial-records">Financial Records</a>
                    <a class="action" href="{{ route('member.usdt-wallets.page') }}">USDT Wallet</a>
                </div>
            </details>
            <details class="lb-member-menu lb-nav-dropdown">
                <summary class="action">My Account <span aria-hidden="true">⌄</span></summary>
                <div class="lb-member-menu-panel">
                    <a class="action" href="#member-profile">Profile</a>
                    <a class="action" href="#account-summary">Your Account Summary</a>
                    <a class="action" href="#account-settings">Account</a>
                    <a class="action" href="#member-support">Support</a>
                    <a class="action" href="{{ route('security-pin.form') }}">Change Security PIN</a>
                </div>
            </details>
            <details class="lb-member-menu">
                <summary class="action" aria-label="Open member menu">
                    &#9776; Menu
                </summary>
                <div class="lb-member-menu-panel">
                    <a class="action" href="{{ route('member.dashboard') }}">Dashboard</a>
                    <a class="action" href="{{ route('member.usdt-wallets.page') }}">USDT Wallet</a>
                    <button class="action" type="button" disabled
                        title="Fund Transfer is not enabled yet"
                        style="opacity:.55;cursor:not-allowed">
                        Fund Transfer (Coming Soon)
                    </button>
                    <a class="action" href="{{ route('security-pin.form') }}">Change Security PIN</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="action" type="submit">Logout</button>
                    </form>
                </div>
            </details>
        </nav>
    </header>

    <section id="member-profile" class="lb-profile-strip" aria-label="Member profile">
        <div><span class="lb-eyebrow">MEMBER PROFILE</span><strong>{{ $member->name }}</strong><small>ID: {{ $member->username }}</small></div>
        <span class="lb-account-status">{{ ucfirst($member->status) }} account</span>
    </section>
    <h1>Welcome, {{ $member->name }}</h1>
    <p class="subheading">Member ID: {{ $member->username }} &middot; Account status: {{ ucfirst($member->status) }}</p>

    @if (session('success'))
        <p class="success" role="status">{{ session('success') }}</p>
    @endif<section id="account-summary" aria-label="Member financial summary">
        <h2>Your Account Summary</h2>
        <div class="grid">
            <div class="card"><div class="label">Available Balance</div><div class="value">{{ bcadd((string) ($member->available_balance ?? '0'), '0', 8) }}</div><div class="unit">USDT</div></div>
            <div class="card"><div class="label">Activation Balance</div><div class="value">{{ bcadd((string) ($member->activation_balance ?? '0'), '0', 8) }}</div><div class="unit">USDT</div></div>
            <div class="card"><div class="label">Total Investment</div><div class="value">{{ bcadd((string) ($member->total_investment ?? '0'), '0', 8) }}</div><div class="unit">USDT</div></div>
            <div class="card"><div class="label">Lifetime Income</div><div class="value">{{ bcadd((string) ($member->lifetime_income ?? '0'), '0', 8) }}</div><div class="unit">USDT</div></div>
        </div>
    </section>

    <section id="income-summary" aria-label="Member income summary">
        <h2>My Income Summary</h2>
        <p class="muted">
            Reserved and held amounts are not paid income and are not
            included in your available wallet balance.
        </p>

        <div class="grid">
            <div class="card">
                <div class="label">Self Income Reserved</div>
                <div class="value">{{ bcadd($incomeSummary['self_reserved'], '0', 8) }}</div>
                <div class="unit">USDT - Reserved, not paid</div>
            </div>

            <div class="card">
                <div class="label">Generation Qualified Reserve</div>
                <div class="value">{{ bcadd($incomeSummary['generation_qualified'], '0', 8) }}</div>
                <div class="unit">USDT - Qualified, not paid</div>
            </div>

            <div class="card">
                <div class="label">Generation Held</div>
                <div class="value">{{ bcadd($incomeSummary['generation_held'], '0', 8) }}</div>
                <div class="unit">USDT - Unqualified, not payable</div>
            </div>

            <div class="card">
                <div class="label">Magic Income</div>
                <div class="value">Not allocated</div>
                <div class="unit">Company pool is not member income</div>
            </div>
        </div>
    </section>

    <section id="financial-records" class="lb-records-overview" aria-label="Financial records">
        <div class="lb-section-topline"><span class="lb-eyebrow">LEDGER / VERIFIED RECORDS</span><span class="lb-small-tag">Recorded values only</span></div>
        <h2>Financial Records</h2>
        <p class="muted">Your available balance and income reserves are shown above. Completed wallet entries appear in the transaction history below; held and reserved amounts are not paid income.</p>
        <a class="action lb-inline-link" href="#wallet-history">View Wallet Transaction History →</a>
    </section>

    <section id="wallet-history" aria-label="Member wallet transaction history">
        <h2>My Wallet Transaction History</h2>
        <p class="muted">
            Only completed transactions recorded against your member
            wallet are listed. Reserve movements are excluded.
        </p>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Entry Type</th>
                        <th>Wallet</th>
                        <th>Amount (USDT)</th>
                        <th>Balance After (USDT)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($walletHistory as $transaction)
                        <tr>
                            <td>{{ $transaction->created_at ? \Illuminate\Support\Carbon::parse($transaction->created_at)->format('d M Y H:i') : '-' }}</td>
                            <td>{{ $transaction->entry_type }}</td>
                            <td>{{ $transaction->wallet_type }}</td>
                            <td>{{ bcadd((string) $transaction->amount, '0', 8) }}</td>
                            <td>{{ bcadd((string) $transaction->balance_after, '0', 8) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">No completed wallet transactions recorded.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:15px">
            {{ $walletHistory->links() }}
        </div>
    </section>
    <section id="referral-link" aria-label="Member referral link">
        <h2>Your Referral Link</h2>
        <div class="card">
            <label class="label" for="member-referral-link">Share your personal registration link</label>
            <div style="display:flex; flex-wrap:wrap; gap:10px; margin-top:10px; align-items:center">
                <input id="member-referral-link" type="text" readonly
                       value="{{ url('/join?ref=' . $member->username) }}"
                       style="flex:1 1 280px; width:100%; min-width:0; padding:10px; border:1px solid #334155; border-radius:9px; background:#090d16; color:#e2e8f0; font:inherit"
                       aria-label="Your referral URL">
                <button id="copy-referral-button" type="button" class="action">Copy Referral Link</button>
            </div>
            <p id="referral-copy-status" class="muted" role="status" aria-live="polite" style="margin:10px 0 0"></p>
        </div>
    </section>
    <section id="team-overview" aria-label="Team summary">
        <h2>Your Team Overview</h2>
        <div class="grid">
            <div class="card"><div class="label">Active Team</div><div class="value">{{ (int) ($teamSummary->total_active_team ?? 0) }}</div></div>
            <div class="card"><div class="label">Inactive Team</div><div class="value">{{ (int) ($teamSummary->total_inactive_team ?? 0) }}</div></div>
            <div class="card"><div class="label">Total Team Business</div><div class="value">{{ bcadd((string) ($teamSummary->total_team_business ?? '0'), '0', 8) }}</div><div class="unit">USDT</div></div>
        </div>
    </section>

    <section id="referral-privacy" aria-label="Referral Contact Privacy">
        <h2>Referral Contact Privacy</h2>
        <div class="card">
            <p class="muted">
                Choose whether your direct sponsor may see your full
                phone number or email address. Full details are shared
                only when you give permission AND the admin enables
                full visibility. You can withdraw permission anytime. Your direct sponsor can see masked or full contact details only with your consent and when the admin enables the corresponding
            </p>

            <form method="POST"
                  action="{{ route('member.referral-contact-consent.update') }}">
                @csrf

                <input type="hidden" name="phone_consent" value="0">
                <input type="hidden" name="email_consent" value="0">

                <label style="display:block; margin:14px 0">
                    <input type="checkbox"
                           name="phone_consent"
                           value="1"
                           @checked(old('phone_consent', $phoneConsent))>
                    Allow my direct sponsor to see my full phone number
                </label>

                <label style="display:block; margin:14px 0">
                    <input type="checkbox"
                           name="email_consent"
                           value="1"
                           @checked(old('email_consent', $emailConsent))>
                    Allow my direct sponsor to see my full email address
                </label>

                @error('phone_consent')
                    <p role="alert" style="color:#fca5a5">{{ $message }}</p>
                @enderror

                @error('email_consent')
                    <p role="alert" style="color:#fca5a5">{{ $message }}</p>
                @enderror

                <button class="action" type="submit">
                    Save Privacy Choices
                </button>
            </form>
        </div>
    </section>
    <section id="direct-referrals" aria-label="My Direct Referrals">
        <h2>My Direct Referrals (Level 1)</h2>
        <p class="muted">Only members directly referred by you.</p>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Member ID</th>
                        <th>Name</th>
                        <th>Account Status</th>
                        <th>Joining Date</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Investment</th>
                        <th>Package Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($referrals as $referral)
                        <tr>
                            <td>{{ $referral->username }}</td>
                            <td>{{ $referral->name }}</td>
                            <td>{{ ucfirst($referral->status) }}</td>
                            <td>{{ $referral->created_at ? \Illuminate\Support\Carbon::parse($referral->created_at)->format('d M Y') : '-' }}</td>
                            <td>{{ $referral->display_phone }}</td>
                            <td>{{ $referral->display_email }}</td>
                            <td>{{ $referral->display_investment }}</td>
                            <td>{{ $referral->display_package_status }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">No direct referrals found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:15px">
            {{ $referrals->links() }}
        </div>
    </section>
    <section id="team-breakdown" aria-label="51-level team breakdown">
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
    <section class="lb-bottom-grid" aria-label="Account and support">
        <div id="account-settings" class="card lb-info-card">
            <span class="lb-eyebrow">SECURITY CENTER</span>
            <h2>Account</h2>
            <p class="muted">Manage your security PIN and view your account status. Existing login and account controls remain unchanged.</p>
            <a class="action lb-inline-link" href="{{ route('security-pin.form') }}">Security PIN Settings →</a>
        </div>
        <div id="member-support" class="card lb-info-card">
            <span class="lb-eyebrow">MEMBER ASSISTANCE</span>
            <h2>Support</h2>
            <p class="muted">For assistance, use the official LeoBot support contact provided by your administrator. No support channel is configured on this dashboard.</p>
        </div>
    </section>
    <p class="notice">Read-only dashboard. Values reflect currently recorded data; no transfer, activation, trading funding or income payout is performed by this page. Security PIN setup is an access step, not financial transaction authorization.</p>
</main>
<script>
(function () {
    const button = document.getElementById('copy-referral-button');
    const input = document.getElementById('member-referral-link');
    const status = document.getElementById('referral-copy-status');
    if (!button || !input || !status) return;
    button.addEventListener('click', async function () {
        try {
            if (!navigator.clipboard || !navigator.clipboard.writeText) {
                throw new Error('Clipboard API unavailable');
            }
            await navigator.clipboard.writeText(input.value);
            status.textContent = 'Referral link copied.';
        } catch (error) {
            input.focus();
            input.select();
            status.textContent = 'Link selected. Press Ctrl+C to copy.';
        }
    });
})();
</script>
</body>
</html>
