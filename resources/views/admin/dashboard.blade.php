<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LumiFlowOn - Admin Dashboard</title>

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
<style>
/* LEOBOT_ADMIN_DARK_THEME_FIX_V1 */
body {
    background: #080f1e !important;
    color: #e8f1fc !important;
}
main.container {
    background: #0e1b2e !important;
    color: #e8f1fc !important;
    border: 1px solid #283d56 !important;
    border-radius: 18px !important;
}
main.container h1,
main.container h2,
main.container h3 {
    color: #f1f7ff !important;
}
main.container p,
main.container small {
    color: #a9bdd5 !important;
}
main.container .card,
main.container .panel,
main.container .notice {
    background: #13243a !important;
    color: #e8f1fc !important;
    border: 1px solid #2c435d !important;
    box-shadow: none !important;
}
main.container .card h2,
main.container .card h3 {
    color: #eaf4ff !important;
}
main.container .card p {
    color: #b4c7dc !important;
}
main.container a:not(.btn) {
    color: #82c5ff;
}
main.container .card a {
    color: #ffffff !important;
}
main.container table {
    color: #e8f1fc !important;
}
main.container th {
    background: #1a304b !important;
    color: #f0f6ff !important;
}
main.container td {
    border-color: #30465e !important;
}
main.container input,
main.container select,
main.container textarea {
    background: #0b1829 !important;
    color: #f1f7ff !important;
    border: 1px solid #39516c !important;
}
@media(max-width:768px) {
    main.container {
        padding: 16px !important;
    }
}
</style>
<style>
/* LEOBOT_ADMIN_WHITE_PANEL_FIX_V2 */
main.container,
body main.container,
main.container > div,
main.container > section {
    background-color: #0e1b2e !important;
    color: #e8f1fc !important;
}
main.container > h1,
main.container > h2,
main.container > h3,
main.container > p,
main.container > div > h1,
main.container > div > h2,
main.container > div > p {
    color: #e8f1fc !important;
}
main.container .card {
    background-color: #13243a !important;
    color: #e8f1fc !important;
}
main.container .card p {
    color: #b4c7dc !important;
}
</style>
<style id="lumiflowon-admin-sidebar-v1">
:root{--lf-gold:#d8ae5b;--lf-navy:#06152b;--lf-text:#f1f5ff}
body{background:#06142a!important;color:var(--lf-text)!important;font-family:Inter,system-ui,Arial,sans-serif!important}
.lf-shell{display:flex;min-height:100vh}.lf-sidebar{width:256px;flex:0 0 256px;background:linear-gradient(180deg,#061327,#071c39);border-right:1px solid #34425c;padding:20px 12px;position:sticky;top:0;height:100vh;overflow-y:auto;z-index:20}.lf-side-brand{display:flex;align-items:center;gap:10px;color:#f7d887;font-weight:800;font-size:20px;text-decoration:none;margin:3px 6px 28px}.lf-side-brand img{width:40px;height:40px;object-fit:contain;border-radius:6px}.lf-nav-title{font-size:11px;text-transform:uppercase;letter-spacing:.14em;color:#9baec8;margin:20px 13px 8px}.lf-nav a{display:block;color:#e6edfa!important;text-decoration:none;padding:12px 13px;border-radius:9px;margin:3px 0;font-size:14px}.lf-nav a:hover,.lf-nav a:focus-visible{background:#173252;outline:1px solid #bd9652}.lf-nav a.lf-current{background:linear-gradient(95deg,#ffdf91,#d7a64d);color:#091427!important;font-weight:800}.lf-nav .lf-sub{padding-left:29px;font-size:13px}.lf-main{min-width:0;flex:1}.lf-main .header{background:#07172d!important;border-bottom:1px solid #3d455a!important}.lf-main .header-inner{max-width:100%!important}.lf-main .container{max-width:1400px!important;margin:24px auto!important}.lf-sidebar-toggle{display:none;border:1px solid #c49c54;background:#0a1c35;color:#ffe2a0;padding:9px 12px;border-radius:8px;cursor:pointer}.lf-overlay{display:none}.lf-main .card a{background:linear-gradient(95deg,#ffe2a0,#cfa053)!important;color:#111d2d!important;font-weight:700}.lf-main .footer{color:#bac7d9!important}.lf-main .notice{color:#f3e5c2!important}
@media(max-width:850px){.lf-sidebar{position:fixed;transform:translateX(-105%);transition:transform .2s;width:256px;box-shadow:0 0 40px #0007}.lf-sidebar.lf-open{transform:translateX(0)}.lf-sidebar-toggle{display:inline-block}.lf-overlay.lf-open{display:block;position:fixed;inset:0;background:#0009;z-index:19}.lf-main .header-inner{justify-content:flex-start!important}.lf-main .container{padding:14px!important}}
</style>
</head>

<body>
<div class="lf-shell">
<div class="lf-overlay" id="lfOverlay" onclick="lfCloseNav()"></div>
<aside class="lf-sidebar" id="lfSidebar" aria-label="Admin navigation">
 <a href="{{ route('admin.dashboard') }}" class="lf-current">Dashboard</a>
 <nav class="lf-nav">
  <a href="{{ route('admin.dashboard') }}" class="lf-current">Dashboard</a>
  <a href="{{ route('admin.members.index') }}">Members</a>
  <a href="{{ route('admin.magic.index') }}">Magic Matrix</a>
  <a href="{{ route('admin.staking.index') }}">Staking Control</a>
<a href="{{ route('admin.deposit-control.index') }}">Deposit Control</a>
<a href="{{ route('admin.withdrawals.index') }}">Withdrawals</a>
  <a href="{{ route('admin.deposit-review.index') }}">Deposit Review</a>
  <a href="{{ route('admin.income.index') }}">Income Settings</a>
  <a href="{{ route('admin.unified-funding.index') }}">Funding Audit</a>
  <div class="lf-nav-title">Settings</div>
  <a href="{{ route('admin.registration-otp.index') }}" class="lf-sub ">Registration Email OTP</a>
  <a href="{{ route('admin.lumiflowon.logo.index') }}" class="lf-sub ">Logo Management</a>
  <a href="{{ route('admin.referral-privacy.index') }}" class="lf-sub">Referral Privacy</a>
  <a href="{{ route('admin.quick-links.index') }}">Quick Links</a>
 <a style="color:white" href="{{ route('admin.members.deletion') }}">Delete Member ID</a><a href="{{ route('admin.transfer-controls') }}">Transfer Controls</a><a href="{{ route('admin.income-distribution') }}">Income Distribution</a><a href="{{route('admin.member-access')}}">Member Activation</a><a href="{{route('admin.member-email')}}">Change Member Email</a></nav>
</aside>
<div class="lf-main">




<header class="header">
<button type="button" class="lf-sidebar-toggle" aria-label="Open admin menu" onclick="lfToggleNav()">☰ Menu</button>
    <div class="header-inner">
        <div>
            <div class="brand">LumiFlowOn</div>
            <div class="subtitle">Secure Administration Panel</div>
        </div>

        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button class="logout" type="submit">
                Logout
            </button>
        </form>
    </div>
</header>

<main class="container"><section class="card" style="padding:18px;margin:16px 0"><h2>Deposit Control</h2><p>Manage BEP20/TRC20 receiving addresses and QR images.</p><a href="{{ route('admin.deposit-review.index') }}">Deposit Review</a></section>
<!-- LUMIFLOWON_STAKING_ADMIN_UI_V1 -->
<section class="card" aria-label="Staking administration" style="margin:16px 0;padding:18px">
  <h2>Staking Control</h2><p><a href="{{ route('admin.staking.profit.index') }}">Trading Profit</a></p>
  @php
    $stakingControlReady = \Illuminate\Support\Facades\Schema::hasTable('staking_controls');
    $stakingEnabled = $stakingControlReady && (bool) \Illuminate\Support\Facades\DB::table('staking_controls')->orderBy('id')->value('enabled');
  @endphp
  <p>Current staking status: <strong>{{ $stakingEnabled ? 'ON' : 'OFF' }}</strong></p>
  @if(!$stakingControlReady)
    <p>Staking control table unavailable. No changes permitted.</p>
  @elseif($stakingEnabled)
    <form method="POST" action="{{ route('admin.staking.toggle') }}">
      @csrf
      <input type="hidden" name="enabled" value="0">
      <button type="submit">Turn Staking OFF</button>
    </form>
    <p>Disabling prevents new stakes; existing positions and history remain unchanged.</p>
  @elseif(config('leobot.staking_enabled', false) === true && config('leobot.production_financial_approval', false) === true)
    <form method="POST" action="{{ route('admin.staking.toggle') }}">
      @csrf
      <input type="hidden" name="enabled" value="1">
      <button type="submit">Turn Staking ON</button>
    </form>
  @else
    <p>Staking is OFF. Activation is withheld until financial tests and separate production approval.</p>
    <button type="button" disabled title="Requires financial verification and separate approval">Turn Staking ON (locked)</button>
  @endif
</section>

<section class="lb-terminal" aria-label="LeoBot premium financial dashboard">
  <div class="lb-terminal-head">
    <div><div class="lb-terminal-eyebrow">LumiFlowOn / ADMIN CONSOLE</div><h2>Financial Operations Overview</h2><p>Institutional-style command center for verified LumiFlowOn records.</p></div>
    <div class="lb-terminal-status"><span class="lb-terminal-dot"></span> Protected admin session</div>
  </div>
  <div class="lb-terminal-grid">
    <div class="lb-terminal-tile"><small>Platform</small><strong>LumiFlowOn Control</strong></div>
    <div class="lb-terminal-tile"><small>Financial operations</small><strong class="accent">Approval gated</strong></div>
    <div class="lb-terminal-tile"><small>Dashboard mode</small><strong>Read-only overview</strong></div>
  </div>
  <p class="lb-terminal-foot lb-terminal-note">UI theme only · No MT5 feed · No simulated trading values · Existing financial permissions remain unchanged.</p>
</section>
<section class="welcome">
        <h1>Admin Dashboard</h1>

        <p>
            Welcome,
            <strong>{{ auth()->user()->username }}</strong>
        </p>

        <p>
            Manage LumiFlowOn settings and review audit history.
        </p>
        <div class="card">
            <h2>Magic Matrix</h2>
            <p>Review 20-level Magic settings, enrollment, top-up and settlement controls. Financial operations remain disabled.</p>
            <a href="{{ route('admin.magic.index') }}">Magic Matrix</a>
        </div>

        <div class="card">
            <h2>Admin Withdrawals</h2>
            <p>Review withdrawal requests, approval controls and principal withdrawal fees. External payouts require administrator review and confirmed chain verification.</p>
            <a href="{{ route('admin.withdrawals.index') }}">Withdrawals</a>
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
        <a href="{{ route('admin.unified-funding.index') }}">Funding Audit</a>
    </div>

        <div class="card">
            <h2>51-Level Commission</h2>
            <p>
                View and manage generation commission
                configuration across 51 levels.
            </p>
            <a href="{{ route('admin.levels.index') }}">51-Level Settings</a>
        </div>

        <div class="card">
            <h2>Commission Audit History</h2>
            <p>
                Review recorded changes to the
                generation commission configuration.
            </p>
            <a href="{{ route('admin.levels.history') }}">Level History</a>
        </div>

        <div class="card">
            <h2>Income Settings</h2>
            <p>
                Review Self Income, Generation Income,
                Magic Income and related settings.
            </p>
            <a href="{{ route('admin.income.index') }}">Income Settings</a>
        </div>

        <div class="card">
            <h2>Income Audit History</h2>
            <p>
                Review recorded changes to
                income-related configuration.
            </p>
            <a href="{{ route('admin.income.history') }}">Income History</a>
        </div>

        <div class="card">
            <h2>Referral Privacy</h2>
            <p>
                Manage direct referral contact
                privacy and consent settings.
            </p>
            <a href="{{ route('admin.referral-privacy.index') }}" class="lf-sub">Referral Privacy</a>
        </div>

        <div class="card">
            <h2>Member Management</h2>
            <p>
                View registered members, search usernames,
                and review sponsor and account status.
                View member details and audited block/unblock controls.
            </p>
            <a href="{{ route('admin.members.index') }}">Members</a>
        </div>
        <div class="card">
            <h2>Magic Matrix</h2>
            <p>Review 20-level Magic settings, enrollment, top-up and settlement controls. Financial operations remain disabled.</p>
            <a href="{{ route('admin.magic.index') }}">Magic Matrix</a>
        </div>


    </section>

</main>

<footer class="footer">
    LumiFlowOn - Admin Dashboard
</footer>


</div></div>
<script>function lfToggleNav(){document.getElementById('lfSidebar')?.classList.toggle('lf-open');document.getElementById('lfOverlay')?.classList.toggle('lf-open')}function lfCloseNav(){document.getElementById('lfSidebar')?.classList.remove('lf-open');document.getElementById('lfOverlay')?.classList.remove('lf-open')}</script>
<style>.management-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:20px;margin:24px}.management-card{padding:24px;border:1px solid #40516b;border-radius:14px;background:#101b2e}.management-actions{display:flex;gap:12px;flex-wrap:wrap}.management-actions a{display:inline-block;padding:12px 16px;border:1px solid #40516b;border-radius:8px;color:#fff!important;background:#1b2b42}.management-actions a:hover{border-color:#e9c583}</style><div class="management-grid"><section class="management-card"><h2>Communication & Member Management</h2><div class="management-actions"><a href="{{route('admin.support.index')}}">Support Inbox</a><a href="{{route('admin.announcements.index')}}">Announcements</a><a href="{{route('admin.members.deletion')}}">Delete Member ID</a></div></section><section class="management-card"><h2>Member Accounts</h2><div class="management-actions"><a href="{{route('admin.member-access')}}">Member Activation</a><a href="{{route('admin.member-email')}}">Change Member Email</a></div></section></div><style>.display-controls{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;margin:24px}.display-control-card{padding:26px;border:1px solid #40516b;border-radius:16px;background:linear-gradient(135deg,#172941,#0d192b)}.display-control-card h2{color:#e9c583}.display-control-card a{color:#fff!important;text-decoration:none;background:#1b2b42}.display-control-card button{background:#e9c583;color:#142034;font-weight:700}.display-control-card a,.display-control-card button{display:inline-block;padding:12px 18px;border:1px solid #52637b;border-radius:8px;margin-top:8px}</style><div class="display-controls"><!-- LUMIFLOWON_MT5_UI_FIXED -->
<section class="display-control-card">
<h2>Dashboard Quick Links</h2>
<p>Manage Telegram, WhatsApp, YouTube, Arattai and PDF visibility.</p>
@php
$enabledDashboardLinks = \Illuminate\Support\Facades\Schema::hasTable('dashboard_quick_links') ? \Illuminate\Support\Facades\DB::table('dashboard_quick_links')->where('enabled', true)->get()->keyBy('platform') : collect();
@endphp
<div style="display:flex;gap:8px;flex-wrap:wrap;margin:12px 0">
@foreach(['whatsapp','youtube','arattai','telegram','pdf'] as $platform)
@php
$quickLinkUrl = trim((string) ($enabledDashboardLinks->get($platform)->url ?? ''));
@endphp
@if(\App\Support\QuickLinkUrl::isSafe($quickLinkUrl))
<a data-quick-link="{{ $platform }}" href="{{ $quickLinkUrl }}" target="_blank" rel="noopener noreferrer" @if($platform === 'pdf') download @endif aria-label="{{ $platform === 'telegram' ? 'Open Telegram in a new tab' : ($platform === 'pdf' ? 'Download PDF' : 'Join '.ucfirst($platform).' in a new tab') }}" style="display:inline-block;padding:8px 12px;border:1px solid #52637b;border-radius:8px;color:#e6f0ff">{{ $platform==='pdf'?'PDF':ucfirst($platform) }}<small style="display:block;color:#22c55e!important">{{ $platform === 'pdf' ? 'Download' : 'Join now' }}</small></a>
@else
<span style="display:inline-block;padding:8px 12px;border:1px solid #52637b;border-radius:8px;color:#e6f0ff">{{ ucfirst($platform) }}</span>
@endif
@endforeach
</div>
<p>Each link needs its own official HTTPS URL and Enabled setting. Configure them using the management page.</p>
<a href="{{ route('admin.quick-links.index') }}">Quick Links</a>
</section>
<section class="display-control-card">
    <h2>MT5 Live Trading Display</h2>
    <p>View-only. Trading and settlement actions are not available.</p>

    @php
        $mt5Enabled = \Illuminate\Support\Facades\Schema::hasTable('mt5_display_settings')
            && (bool) \Illuminate\Support\Facades\DB::table('mt5_display_settings')->where('id', 1)->value('enabled');
    @endphp

    <p>Display: <strong>{{ $mt5Enabled ? 'ON' : 'OFF' }}</strong></p>
    <p>Live connection: Not configured</p>

    <form method="POST" action="{{ route('admin.mt5.toggle') }}">
        @csrf
        <input type="hidden" name="enabled" value="{{ $mt5Enabled ? 0 : 1 }}">
        <button type="submit">{{ $mt5Enabled ? 'Turn OFF MT5 Display' : 'Turn ON MT5 Display' }}</button>
    </form>

    <p><a href="{{ route('admin.mt5.view') }}">View MT5 Status (JSON)</a></p>
</section>

</div></body></html>
