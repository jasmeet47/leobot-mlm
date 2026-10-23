<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Member Dashboard | LumiFlowOn</title>
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
<style>
/* LEOBOT_UI_FINAL_POLISH_V3: navigation, spacing, responsive-only */
main.container { padding-top:18px!important; }
main.container>header { margin:0 0 16px; position:relative; z-index:50; box-shadow:0 8px 30px #020a1580; }
main.container>.lb-terminal { margin:0 0 18px; padding:clamp(16px,2.2vw,26px); border-radius:17px; }
.lb-terminal-grid { margin-top:15px; }
.lb-terminal-tile { min-height:86px; padding:13px 15px; }
.lb-terminal-tile small { margin-bottom:6px; }
.lb-terminal-foot { margin-top:12px!important; }
.lb-profile-strip { margin-top:0; margin-bottom:20px; }
.lb-nav-groups { justify-content:flex-end; }
.lb-nav-groups>.lb-nav-dropdown { position:relative; }
.lb-nav-groups .lb-member-menu-panel { z-index:100; }
.lb-nav-groups .lb-nav-dropdown:first-of-type .lb-member-menu-panel { left:0; right:auto; }
.lb-nav-groups .lb-nav-dropdown:last-of-type .lb-member-menu-panel { left:auto; right:0; }
.lb-nav-groups summary:focus-visible, .lb-nav-groups a:focus-visible, .lb-nav-groups button:focus-visible { outline:2px solid #e6bd6a; outline-offset:3px; }
.lb-member-menu-panel form button { width:100%; text-align:left; }
.lb-nav-groups .lb-member-menu-panel .action:disabled { color:#8190a5; }
@media(max-width:900px) { main.container>header { align-items:center; } .lb-nav-groups { width:auto; flex:1; justify-content:flex-end; } }
@media(max-width:700px) {
 main.container { width:96%; }
 main.container>header { padding:14px; gap:12px; }
 main.container>header .brand { width:100%; }
 .lb-nav-groups { width:100%; display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px; }
 .lb-nav-groups .lb-home-link { display:flex; align-items:center; justify-content:center; }
 .lb-nav-groups .lb-nav-dropdown { width:100%; }
 .lb-nav-groups .lb-nav-dropdown>summary { display:block; width:100%; text-align:center; }
 .lb-nav-groups .lb-member-menu-panel { width:min(310px,92vw); }
 .lb-nav-groups .lb-nav-dropdown:nth-of-type(2) .lb-member-menu-panel { left:auto; right:0; }
 .lb-terminal-grid { grid-template-columns:1fr; gap:8px; }
 .lb-terminal-tile { min-height:0; }
 .lb-profile-strip { padding:14px; }
}
@media(prefers-reduced-motion:reduce) { html { scroll-behavior:auto; } }
</style>
<!-- LEOBOT_TOP_RIGHT_HAMBURGER_V1: member presentation only -->
<style>
main.container > header {display:flex; flex-wrap:nowrap; align-items:center; justify-content:space-between; gap:12px}
main.container > header .brand {width:auto!important; min-width:0}
.lb-hamburger-nav {display:flex!important; flex:0 0 auto!important; width:auto!important; margin-left:auto; justify-content:flex-end!important}
.lb-hamburger-nav .lb-hamburger {position:relative; width:auto!important}
.lb-hamburger-nav .lb-hamburger-trigger {display:flex!important; align-items:center; justify-content:center; gap:6px; min-width:50px; min-height:45px; padding:7px 12px!important; font-size:25px!important; line-height:1; width:auto!important; text-align:center; border:1px solid #78613c; color:#f7dfaa}
.lb-hamburger-nav .lb-hamburger-panel {position:absolute; top:calc(100% + 10px); right:0!important; left:auto!important; width:min(330px,calc(100vw - 28px))!important; max-height:75vh; overflow-y:auto; z-index:300; display:flex; flex-direction:column}
.lb-hamburger-nav .lb-hamburger-panel .action {text-align:left!important; width:100%; white-space:normal}
body.lb-menu-ready .lb-menu-detail {display:none}
body.lb-menu-ready .lb-menu-detail.lb-menu-visible {display:block}
body.lb-menu-ready .lb-bottom-grid.lb-menu-visible {display:grid}
body.lb-menu-ready .lb-profile-strip.lb-menu-visible {display:flex}
@media(max-width:700px) {main.container > header .brand {font-size:19px} .lb-hamburger-nav {display:flex!important; width:auto!important; flex:none!important} .lb-menu-caption {display:none}}
</style>
<style>
/* LEOBOT_MEMBER_MENU_FOUR_GROUPS_V1: presentation only */
.lb-hamburger-panel .lb-menu-group-title { position:sticky; top:0; z-index:1; padding:10px 12px 6px; margin-top:6px; font-size:12px; font-weight:800; letter-spacing:.09em; text-transform:uppercase; color:#f1cd86; background:#0b1b2d; border-bottom:1px solid rgba(241,205,134,.25); }
.lb-hamburger-panel .lb-menu-group-title:first-child { margin-top:0; }
.lb-hamburger-panel .action { padding-left:15px!important; }
</style>

<style id="LEOBOT_SHARED_DARK_GOLD_V2">
/* Local UI theme only. No application logic or event handlers are modified. */
:root { color-scheme: dark; --lb-bg:#091729; --lb-surface:#10243a; --lb-surface-2:#17314b; --lb-gold:#e6ba62; --lb-text:#eaf1f9; --lb-muted:#b7c7d9; --lb-border:#3b526b; }
html, body { background-color:var(--lb-bg) !important; color:var(--lb-text); }
body, main, .container, .wrapper, .page, .page-wrap, .content, .content-wrapper { color:var(--lb-text); }
h1,h2,h3,h4,h5,h6 { color:var(--lb-gold); }
a { color:var(--lb-gold); }
a:hover { color:#ffe0a1; }
/* Explicitly theme common card/table/form containers while preserving layout. */
.card, .panel, .box, .stat-card, .metric-card, .table-responsive, fieldset, .form-card, .summary-card { background-color:var(--lb-surface) !important; color:var(--lb-text) !important; border-color:var(--lb-border) !important; }
input:not([type="checkbox"]):not([type="radio"]):not([type="range"]), select, textarea { background-color:#0d2136 !important; color:var(--lb-text) !important; border-color:var(--lb-border) !important; }
input::placeholder,textarea::placeholder { color:#a9bdd0 !important; opacity:1; }
label,legend,small { color:var(--lb-muted); }
table { color:var(--lb-text); border-color:var(--lb-border); }
th { color:var(--lb-gold); border-color:var(--lb-border) !important; }
td { border-color:var(--lb-border) !important; }
thead { background-color:var(--lb-surface-2); }
tr:hover td { background-color:rgba(230,186,98,.06); }
button, .btn { border-color:var(--lb-border); }
button:disabled, .btn:disabled, [aria-disabled="true"] { opacity:.55; cursor:not-allowed; }
.alert, .notice, .message { border-color:var(--lb-border); }
:focus-visible { outline:2px solid var(--lb-gold); outline-offset:2px; }
@media (max-width: 640px) { table { max-width:100%; } input, select, textarea { max-width:100%; box-sizing:border-box; } }
</style>

<!-- LEOBOT_REFERRAL_ALWAYS_VISIBLE_V1 -->
<style>
#referral-link{display:block!important;visibility:visible!important;opacity:1!important;max-height:none!important;overflow:visible!important}
</style>
</head>
<body>



<main class="container">
    <header>
        <div class="brand">LumiFlowOn <small>Member Dashboard</small></div>
        <!-- LEOBOT_MEMBER_MENU_V2: anchored groups preserve all existing dashboard sections -->
        <nav class="actions lb-nav-groups lb-hamburger-nav" aria-label="Member actions">
    <details class="lb-member-menu lb-nav-dropdown lb-hamburger" id="lb-hamburger-menu">
        <summary class="action lb-hamburger-trigger" aria-label="Open member menu" title="Member menu"><span aria-hidden="true">&#9776;</span><span class="lb-menu-caption"> Menu</span></summary>
        <div class="lb-member-menu-panel lb-hamburger-panel" aria-label="Member navigation">
            <!-- LEOBOT_MEMBER_MENU_FOUR_GROUPS_V1 -->
            <div class="lb-menu-group-title" role="heading" aria-level="3">Dashboard</div>
            <a class="action" href="{{ route('member.dashboard') }}">Dashboard Overview</a>

            <div class="lb-menu-group-title" role="heading" aria-level="3">My Team</div>
            <a class="action" href="#team-breakdown">51-Level Team Breakdown</a>
            <a class="action" href="{{ route('member.team-levels') }}">View My 51-Level Team</a>
            @if(isset($magicTeam) && ($magicTeam['visible_levels'] ?? 0) > 0)
            <a class="action" href="#magic-team" style="color:#86efac!important;border:1px solid #22c55e!important;background:#123526!important;font-weight:700">✨ My 20-Level Magic Team (2×2 Matrix)</a>
            @endif
            <a class="action" href="#team-overview">Team / Referral</a>
            <a class="action" href="#direct-referrals">My Direct Referrals</a>
            <a class="action" href="#referral-link">Referral Link</a>
            <a class="action" href="#referral-privacy">Referral Contact Privacy</a>

            <div class="lb-menu-group-title" role="heading" aria-level="3">My Finance</div>
            <a class="action" href="#income-summary">Income Details</a>
            <a class="action" href="#wallet-history">My Wallet Transaction History</a>
            <a class="action" href="#financial-records">Financial Records</a>
            <a class="action" href="{{ route('member.usdt-wallets.page') }}">USDT Wallet</a>
            <a class="action" href="{{ route('member.withdrawals.index') }}">Withdrawals</a>
            <a class="action" href="{{ route('member.deposit-transfer.index') }}">Deposit / Fund Transfer (status &amp; requests)</a>

            <div class="lb-menu-group-title" role="heading" aria-level="3">My Account</div>
            <a class="action" href="#member-profile">Profile / Settings</a>
            <a class="action" href="#account-settings">Account / Security PIN</a>
            <a class="action" href="#member-support">Support</a>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="action" type="submit">Logout</button></form>
        </div>
    </details>
</nav>
    </header>
<!-- LUMIFLOWON_MT5_UI_FIXED -->
@php
$dashboardLinks = \Illuminate\Support\Facades\Schema::hasTable('dashboard_quick_links')
    ? \Illuminate\Support\Facades\DB::table('dashboard_quick_links')
        ->where('enabled', true)
        ->whereNotNull('url')
        ->get()
    : collect();
@endphp

<section class="lb-quick-links" style="margin:20px 0;padding:20px;border:1px solid #64748b;border-radius:12px">
<h2>LumiFlowOn Quick Links</h2>
<p style="color:#9fb1c9">Admin-controlled official links. Unconfigured links are not clickable.</p>
<div style="display:flex;gap:12px;flex-wrap:wrap">
@foreach(['whatsapp','youtube','arattai','telegram','pdf'] as $platform)
@php
  $configuredLink = $dashboardLinks->firstWhere('platform', $platform);
  $linkUrl = trim((string) ($configuredLink->url ?? ''));
  $linkValid = filter_var($linkUrl, FILTER_VALIDATE_URL) && strtolower((string) parse_url($linkUrl, PHP_URL_SCHEME)) === 'https';
@endphp
@if($configuredLink && $linkValid)
<a href="{{ $linkUrl }}" target="_blank" rel="noopener noreferrer" style="padding:12px 18px;background:#1e293b;color:white;border-radius:8px">{{ ucfirst($platform) }}</a>
@else
<span style="padding:12px 18px;background:#263449;color:#b8c4d5;border-radius:8px" title="Admin must configure and enable a valid HTTPS URL">{{ ucfirst($platform) }} · Not configured</span>
@endif
@endforeach
</div>
</section>


@if(true)
<section class="lb-magic-summary" aria-label="Magic Income Summary" style="margin:18px 0;padding:18px;border:1px solid #2b435e;border-radius:14px;background:#101d30">
  <h2>Magic Income (Settled)</h2>
  <div class="grid">
    <div class="card"><div class="label">Weekly</div><div class="value">@if(isset($magicIncomeSummary) && is_array($magicIncomeSummary)){{ $magicIncomeSummary['weekly_usdt'] ?? 'Unavailable' }} USDT @else Unavailable @endif</div></div>
    <div class="card"><div class="label">Monthly</div><div class="value">@if(isset($magicIncomeSummary) && is_array($magicIncomeSummary)){{ $magicIncomeSummary['monthly_usdt'] ?? 'Unavailable' }} USDT @else Unavailable @endif</div></div>
    <div class="card"><div class="label">Lifetime</div><div class="value">@if(isset($magicIncomeSummary) && is_array($magicIncomeSummary)){{ $magicIncomeSummary['lifetime_usdt'] ?? 'Unavailable' }} USDT @else Unavailable @endif</div></div>
  </div>
  <p class="muted">Only credited Magic entitlements. No pending or held amounts.</p>
</section>
@endif
<!-- LEOBOT_QUICK_ACTIONS_SAFE_V1 -->
<style>
.lb-quick-actions{margin:18px 0 22px;padding:18px;border:1px solid #2b435e;border-radius:16px;background:#101d30}
.lb-quick-actions h2{margin:0 0 12px;font-size:20px;color:#f1f7ff}
.lb-quick-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px}
.lb-quick-link{display:flex;flex-direction:column;justify-content:center;min-height:96px;gap:6px;padding:15px;border:1px solid #35516e;border-radius:12px;background:#192b43;color:#f1f7ff;text-decoration:none;font:inherit;text-align:left}
.lb-quick-link strong{font-size:17px}.lb-quick-link small{color:#b2c6dd}
.lb-quick-link:not([disabled]):hover,.lb-quick-link:not([disabled]):focus-visible{border-color:#38bdf8}
.lb-quick-link[disabled]{opacity:.6;cursor:not-allowed}
</style>
<section class="lb-quick-actions" aria-label="Member quick actions">
  <h2>Quick Actions</h2>
  <div class="lb-quick-grid">
    <a class="lb-quick-link" href="{{ route('member.withdrawals.index') }}"><strong>Withdraw</strong><small>Withdrawal requests</small></a>
    <a class="lb-quick-link" href="{{ route('member.deposit-transfer.index') }}"><strong>Deposit</strong><small>View deposit request page; financial approval required</small></a>
    <button class="lb-quick-link" type="button" disabled aria-disabled="true" title="Staking is coming soon"><strong>Staking</strong><small>Coming Soon</small></button>
    <a class="lb-quick-link" href="{{ route('member.deposit-transfer.index') }}"><strong>Fund Transfer</strong><small>View transfer page; financial approval required</small></a>
  </div>
</section>
<!-- /LEOBOT_QUICK_ACTIONS_SAFE_V1 -->
@if(isset($magicTeam) && ($magicTeam['visible_levels'] ?? 0) > 0)
<section id="magic-team" class="lb-quick-actions" aria-label="20-Level Magic Team Overview">
  <h2>My 20-Level Magic Team (2×2 Matrix)</h2>
  @if(isset($magicTeam) && is_array($magicTeam) && $magicTeam['available'])
    <div class="lb-quick-grid">
      <div class="lb-quick-link"><strong>{{ $magicTeam['total'] }}</strong><small>Total unique Magic Team Members</small></div>
      <div class="lb-quick-link"><strong>{{ $magicTeam['direct'] }}</strong><small>Direct Magic Members</small></div>
      <div class="lb-quick-link"><strong>{{ $magicTeam['filled_levels'] }} / {{ $magicTeam['visible_levels'] }}</strong><small>Filled Levels</small></div>
      <div class="lb-quick-link"><strong>{{ $magicTeam['has_position'] ? 'Placed' : 'Not placed' }}</strong><small>My Matrix Position</small></div>
    </div>
    <details style="margin-top:14px"><summary>View 20-Level Magic Team Breakdown</summary>
      <div class="lb-quick-grid" style="margin-top:12px">
        @foreach($magicTeam['levels'] as $level => $count)
          <div class="lb-quick-link"><strong>Level {{ $level }}</strong><small>{{ $count }} member(s)</small></div>
        @endforeach
      </div>
    </details>
  @else
    <p>Magic Matrix data unavailable; no team count is being assumed.</p>
  @endif
</section>
@endif
<section class="lb-terminal" aria-label="LumiFlowOn member financial dashboard">
  <div class="lb-terminal-head">
    <div><div class="lb-terminal-eyebrow">LumiFlowOn / MEMBER PORTAL</div><h2>Portfolio Overview</h2><p>Welcome, {{ $member->name }}. Your LumiFlowOn account information is shown below.</p></div>
    <div class="lb-terminal-status"><span class="lb-terminal-dot"></span> Member account overview</div>
  </div>
  <div class="lb-terminal-grid">
    <div class="lb-terminal-tile"><small>Account</small><strong>Member Portal</strong></div>
    <div class="lb-terminal-tile"><small>Financial records</small><strong class="accent">Verified records only</strong></div>
    <div class="lb-terminal-tile"><small>Trading feed</small><strong>Not connected</strong></div>
  </div>
  <p class="lb-terminal-foot lb-terminal-note">International trading-inspired interface only. Income, wallet balances and history below are from LumiFlowOn records, not MT5.</p>
</section>


    <section id="member-profile" class="lb-profile-strip" aria-label="Member profile">
        <div><span class="lb-eyebrow">MEMBER PROFILE</span><strong>{{ $member->name }}</strong><small>ID: {{ $member->username }}</small></div>
        <span class="lb-account-status">{{ ucfirst($member->status) }} account</span>
    </section>

    @if (session('success'))
        <p class="success" role="status">{{ session('success') }}</p>
    @endif
<section id="account-summary" aria-label="Member financial summary">
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
        <a class="action lb-inline-link" href="#wallet-history">View Wallet Transaction History ΓåÆ</a>
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
                       value="{{ 'https://lumiflowon.com/join?ref=' . rawurlencode($member->username) }}"
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
                    @php
    $entry = $levelRows->get($level);
@endphp
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
            <a class="action lb-inline-link" href="{{ route('security-pin.form') }}">Security PIN Settings ΓåÆ</a>
        </div>
        <div id="member-support" class="card lb-info-card">
            <span class="lb-eyebrow">MEMBER ASSISTANCE</span>
            <h2>Support</h2>
            <p class="muted">For assistance, use the official LumiFlowOn support contact provided by your administrator. No support channel is configured on this dashboard.</p>
        </div>
    </section>
    <p class="notice">Read-only dashboard. Values reflect currently recorded data; no transfer, activation, trading funding or income payout is performed by this page. Security PIN setup is an access step, not financial transaction authorization.</p>

<!-- MT5 display intentionally last on Member Dashboard; read-only and admin-controlled -->
<section style="margin:20px 0;padding:20px;border:1px solid #64748b;border-radius:12px">
    <h2>MT5 Live Trading</h2>

    @php
        $mt5Visible = \Illuminate\Support\Facades\Schema::hasTable('mt5_display_settings')
            && (bool) \Illuminate\Support\Facades\DB::table('mt5_display_settings')->where('id', 1)->value('enabled');
    @endphp

    @if($mt5Visible)
        <p>MT5 Live Connection: Awaiting verified connection</p>
        <p>Balance, Equity, Profit/Loss and Trades will appear only after a verified read-only feed is connected.</p>
        <p><a href="{{ route('member.mt5.view') }}">View MT5 Status</a></p>
    @endif
</section>
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
            status.textContent = 'Your referral link copied successfully!';
        } catch (error) {
            input.focus();
            input.select();
            status.textContent = 'Link selected. Press Ctrl+C to copy.';
        }
    });
})();
</script>
<script>
/* LEOBOT_UI_FINAL_POLISH_V3: exclusive menus; no financial actions */
(function () {
    const menus = Array.from(document.querySelectorAll('.lb-nav-groups details.lb-nav-dropdown'));
    menus.forEach(function (menu) {
        menu.addEventListener('toggle', function () {
            if (!menu.open) return;
            menus.forEach(function (other) { if (other !== menu) other.open = false; });
        });
        menu.querySelectorAll('a[href^="#"]').forEach(function (link) {
            link.addEventListener('click', function () { menu.open = false; });
        });
    });
    document.addEventListener('click', function (event) {
        if (event.target.closest('.lb-nav-groups')) return;
        menus.forEach(function (menu) { menu.open = false; });
    });
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        menus.forEach(function (menu) { menu.open = false; });
    });
})();
</script>
<script>
/* LEOBOT_TOP_RIGHT_HAMBURGER_V1: visibility only, no data or financial changes */
(function () {
    var ids = ['magic-team','team-breakdown','referral-privacy','wallet-history','financial-records','member-profile','income-summary','team-overview','direct-referrals','referral-link','account-settings','member-support'];
    var menu = document.getElementById('lb-hamburger-menu');
    var bottom = document.querySelector('section.lb-bottom-grid');
    ids.forEach(function(id) { var el=document.getElementById(id); if(el && el!==bottom) el.classList.add('lb-menu-detail'); });
    if (bottom) bottom.classList.add('lb-menu-detail');
    function reveal() {
        ids.forEach(function(id) { var el=document.getElementById(id); if(el) el.classList.remove('lb-menu-visible'); });
        if (bottom) bottom.classList.remove('lb-menu-visible');
        var id = decodeURIComponent((location.hash || '').slice(1));
        if (ids.indexOf(id) === -1) return;
        var el = document.getElementById(id);
        if (!el) return;
        if (id === 'account-settings' || id === 'member-support') {
            if (bottom) bottom.classList.add('lb-menu-visible');
        }
        el.classList.add('lb-menu-visible');
        requestAnimationFrame(function () { el.scrollIntoView({block:'start',behavior:'auto'}); });
    }
    document.body.classList.add('lb-menu-ready');
    window.addEventListener('hashchange', reveal);
    if (menu) menu.querySelectorAll('a[href^="#"]').forEach(function(link) {
        link.addEventListener('click', function() { menu.open=false; if (location.hash === link.getAttribute('href')) reveal(); });
    });
    // Preserve the current panel when following paginated records.
    document.querySelectorAll('#wallet-history a[href],#direct-referrals a[href]').forEach(function(a) {
        try { var u=new URL(a.href,location.href); if(u.origin===location.origin && !u.hash) { u.hash = a.closest('#wallet-history') ? 'wallet-history' : 'direct-referrals'; a.href=u.toString(); } } catch(e) {}
    });
    reveal();
})();
</script>
<!-- LEOBOT_REFERRAL_OVERVIEW_VISIBLE_V1: presentation-only card; original referral generation remains unchanged -->
<style id="LEOBOT_REFERRAL_OVERVIEW_VISIBLE_V1">
#leobot-referral-overview-card{display:block!important;visibility:visible!important;opacity:1!important;background:#10243b!important;border:1px solid #b68b41!important;border-radius:14px!important;padding:18px!important;margin:16px 0 22px!important;color:#e7eef8!important;box-sizing:border-box!important;max-width:100%!important}
#leobot-referral-overview-card h2{color:#f4c66b!important;font-size:1.15rem!important;margin:0 0 10px!important}
#leobot-referral-overview-card .lb-referral-row{display:flex;flex-wrap:wrap;gap:10px;align-items:center}
#leobot-referral-overview-card input{flex:1 1 280px;min-width:0;padding:12px!important;border:1px solid #52667d!important;border-radius:8px!important;background:#091729!important;color:#fff!important;box-sizing:border-box!important}
#leobot-referral-overview-card button{padding:12px 16px!important;border:0!important;border-radius:8px!important;background:#f4c66b!important;color:#091729!important;font-weight:700!important;cursor:pointer!important}
#leobot-referral-overview-card p{margin:9px 0 0!important;color:#cbd7e6!important}
</style>
<script>
(function () {
  function initLeoBotReferralCard() {
    var original = document.getElementById('member-referral-link');
    if (true) return; // Disable duplicate top referral card; keep original section.
    var section = document.getElementById('referral-link');
    var target = document.querySelector('main') || (section && section.parentElement);
    if (!target) return;
    var card = document.createElement('div');
    card.id = 'leobot-referral-overview-card';
    card.setAttribute('aria-label', 'Your referral link');
    var heading = document.createElement('h2');
    heading.textContent = 'Your Referral Link';
    var row = document.createElement('div');
    row.className = 'lb-referral-row';
    var field = document.createElement('input');
    field.type = 'text';
    field.readOnly = true;
    field.setAttribute('aria-label', 'Personal referral URL');
    field.value = original.value || original.getAttribute('value') || '';
    var button = document.createElement('button');
    button.type = 'button';
    button.textContent = 'Copy Referral Link';
    var status = document.createElement('p');
    status.setAttribute('role', 'status');
    status.setAttribute('aria-live', 'polite');
    if (!field.value) status.textContent = 'Referral URL is empty. Please check registration link generation.';
    button.addEventListener('click', function () {
      var value = original.value || field.value;
      if (!value) { status.textContent = 'Referral URL is unavailable.'; return; }
      field.value = value;
      function fallback() {
        field.focus(); field.select();
        try { status.textContent = document.execCommand('copy') ? 'Your referral link copied successfully!' : 'Select and copy the link manually.'; }
        catch (e) { status.textContent = 'Select and copy the link manually.'; }
      }
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(value).then(function () { status.textContent = 'Your referral link copied successfully!'; }, fallback);
      } else { fallback(); }
    });
    row.appendChild(field); row.appendChild(button);
    card.appendChild(heading); card.appendChild(row); card.appendChild(status);
    target.insertBefore(card, target.firstChild);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initLeoBotReferralCard);
  else initLeoBotReferralCard();
})();
</script>
<!-- LEOBOT_REFERRAL_COPY_FINAL_V1: clipboard enhancement, no referral generation changes -->
<script>
(function () {
    'use strict';
    function setupReferralCopy() {
        var button = document.getElementById('copy-referral-button');
        var input = document.getElementById('member-referral-link');
        var status = document.getElementById('referral-copy-status');
        if (!button || !input) return;
        // Capture phase handles the click before older bubbling handlers.
        button.addEventListener('click', async function (event) {
            event.preventDefault();
            event.stopImmediatePropagation();
            var value = input.value || '';
            if (!value) {
                if (status) status.textContent = 'Referral link is empty.';
                return;
            }
            var copied = false;
            // Clipboard API generally requires HTTPS or localhost.
            if (navigator.clipboard && window.isSecureContext) {
                try {
                    await navigator.clipboard.writeText(value);
                    copied = true;
                } catch (error) { /* Try legacy copy below. */ }
            }
            if (!copied) {
                try {
                    input.focus();
                    input.select();
                    input.setSelectionRange(0, value.length);
                    copied = !!document.execCommand('copy');
                } catch (error) { copied = false; }
            }
            if (status) { status.textContent = copied ? 'Your referral link copied successfully!' : 'Link selected. Press Ctrl+C to copy.'; status.style.setProperty('color', copied ? '#22c55e' : '#fbbf24', 'important'); status.style.fontWeight = copied ? '700' : '400'; }
            if (!copied) { input.focus(); input.select(); }
        }, true);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupReferralCopy, { once: true });
    } else {
        setupReferralCopy();
    }
})();
</script>
</body>
</html>