<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="LeoBot member and administration portal.">
    <title>LumiFlowOn | Member Portal</title>
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, Helvetica, sans-serif;
            color: #e2e8f0;
            background: linear-gradient(145deg, #090d16, #12233d);
        }
        .wrap { width: min(1080px, 92%); margin: auto; }
        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 28px 0;
            flex-wrap: wrap;
        }
        .brand { font-size: 27px; font-weight: 800; color: #38bdf8; }
        .toplink { color: #cbd5e1; text-decoration: none; }
        main { padding: 75px 0 90px; }
        .eyebrow {
            color: #7dd3fc;
            font-size: 13px;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        h1 {
            max-width: 780px;
            font-size: clamp(36px, 6vw, 65px);
            line-height: 1.12;
            margin: 18px 0;
        }
        .lead {
            max-width: 660px;
            color: #94a3b8;
            font-size: 18px;
            line-height: 1.7;
        }
        .actions { display: flex; gap: 14px; flex-wrap: wrap; margin-top: 32px; }
        .btn {
            display: inline-block;
            padding: 15px 24px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
            background: #38bdf8;
            color: #07111f;
        }
        .btn.secondary {
            background: transparent;
            color: #e2e8f0;
            border: 1px solid #475569;
        }
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
            margin-top: 70px;
        }
        .feature {
            padding: 25px;
            background: #111c2e;
            border: 1px solid #26354c;
            border-radius: 15px;
        }
        .feature h2 { font-size: 19px; margin: 0 0 12px; }
        .feature p { color: #94a3b8; line-height: 1.6; margin: 0; }
        footer {
            padding: 26px 0;
            border-top: 1px solid #26354c;
            color: #94a3b8;
            font-size: 13px;
        }
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
</head>
<body>
    <div class="wrap">
        <header>
            <div class="brand">LumiFlowOn</div>
            <a class="toplink" href="{{ route('register.form') }}">Member Access</a>
        </header>

        <main>
            <div class="eyebrow">LumiFlowOn Member Portal</div>
            <h1>One place for your account, team and dashboard.</h1>
            <p class="lead">
                Access your LumiFlowOn account, view available team information,
                and manage your profile through protected member services.
            </p>

            <div class="actions">
                @auth
                    @if (auth()->user()->status === 'active' && !auth()->user()->is_blocked)
                        @if (in_array(auth()->user()->role, ['admin', 'super_admin'], true))
                            <a class="btn" href="{{ route('admin.dashboard') }}">Open Admin Dashboard</a>
                        @else
                            <a class="btn" href="{{ route('member.dashboard') }}">Open Member Dashboard</a>
                        @endif
                    @else
                        <p role="status">Your account access is currently restricted.</p>
                    @endif
                @else
                    <a class="btn" href="{{ route('register.form') }}">Login</a>
                    <a class="btn" href="{{ route('register.form') }}">Sign Up</a>
                @endauth
            </div>

            <div class="features">
                <section class="feature">
                    <h2>Member Dashboard</h2>
                    <p>Review available account and team information.</p>
                </section>
                <section class="feature">
                    <h2>51-Level Team</h2>
                    <p>Access team-related information subject to account permissions.</p>
                </section>
                <section class="feature">
                    <h2>Protected Access</h2>
                    <p>Member and administration areas use separate access controls.</p>
                </section>
            </div>
        </main>

        <footer>LumiFlowOn &mdash; Secure member access portal.</footer>
    </div>
</body>
</html>