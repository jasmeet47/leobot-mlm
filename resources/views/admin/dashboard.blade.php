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
<section class="lb-terminal" aria-label="LeoBot premium financial dashboard">
  <div class="lb-terminal-head">
    <div><div class="lb-terminal-eyebrow">LEOBOT / ADMIN CONSOLE</div><h2>Financial Operations Overview</h2><p>Institutional-style command center for verified LeoBot records.</p></div>
    <div class="lb-terminal-status"><span class="lb-terminal-dot"></span> Protected admin session</div>
  </div>
  <div class="lb-terminal-grid">
    <div class="lb-terminal-tile"><small>Platform</small><strong>LeoBot Control</strong></div>
    <div class="lb-terminal-tile"><small>Financial operations</small><strong class="accent">Approval gated</strong></div>
    <div class="lb-terminal-tile"><small>Dashboard mode</small><strong>Read-only overview</strong></div>
  </div>
  <p class="lb-terminal-foot lb-terminal-note">UI theme only Â· No MT5 feed Â· No simulated trading values Â· Existing financial permissions remain unchanged.</p>
</section>
<section class="welcome">
        <h1>Admin Dashboard</h1>

        <p>
            Welcome,
            <strong>{{ auth()->user()->username }}</strong>
        </p>

        <p>
            Manage LeoBot settings and review audit history.
        </p>
        <div class="card">
            <h2>Magic Matrix</h2>
            <p>Review 20-level Magic settings, enrollment, top-up and settlement controls. Financial operations remain disabled.</p>
            <a href="{{ route('admin.magic.index') }}">Open Magic Matrix</a>
        </div>

        <div class="card">
            <h2>Admin Withdrawals</h2>
            <p>Review withdrawal requests, approval controls and principal withdrawal fees. Payouts remain disabled.</p>
            <a href="{{ route('admin.withdrawals.index') }}">Open Withdrawals</a>
        </div>
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
        <div class="card">
            <h2>Magic Matrix</h2>
            <p>Review 20-level Magic settings, enrollment, top-up and settlement controls. Financial operations remain disabled.</p>
            <a href="{{ route('admin.magic.index') }}">Open Magic Matrix</a>
        </div>

        <div class="card">
            <h2>Admin Withdrawals</h2>
            <p>Review withdrawal requests, approval controls and principal withdrawal fees. Payouts remain disabled.</p>
            <a href="{{ route('admin.withdrawals.index') }}">Open Withdrawals</a>
        </div>
    </section>

</main>

<footer class="footer">
    LeoBot MLM - Admin Dashboard
</footer>

</body>
</html>