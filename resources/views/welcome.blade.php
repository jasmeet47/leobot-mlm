<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="LeoBot member and administration portal.">
    <title>LeoBot | Member Portal</title>
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
</head>
<body>
    <div class="wrap">
        <header>
            <div class="brand">LEOBOT</div>
            <a class="toplink" href="{{ route('register.form') }}">Member Access</a>
        </header>

        <main>
            <div class="eyebrow">LeoBot Member Portal</div>
            <h1>One place for your account, team and dashboard.</h1>
            <p class="lead">
                Access your LeoBot account, view available team information,
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

        <footer>LeoBot &mdash; Secure member access portal.</footer>
    </div>
</body>
</html>
