<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Member Details - LumiFlowOn Admin</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 24px;
            color: #222;
        }
        .container {
            max-width: 760px;
            margin: 0 auto;
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 10px rgba(0,0,0,.08);
        }
        h1 { margin-top: 0; }
        .field {
            padding: 14px 0;
            border-bottom: 1px solid #eee;
        }
        .label {
            color: #666;
            font-size: 13px;
            margin-bottom: 5px;
        }
        .value {
            font-size: 16px;
            overflow-wrap: anywhere;
        }
        .back {
            display: inline-block;
            margin-bottom: 18px;
            color: #1557b0;
            text-decoration: none;
        }
        .notice {
            margin-top: 20px;
            color: #666;
            font-size: 13px;
        }
    </style>
<style id="lumiflowon-shared-admin-sidebar-v2">
html,body{min-height:100%;}body{margin:0!important;background:#061327!important;color:#edf3ff!important}.lf2-shell{display:flex;min-height:100vh;width:100%;align-items:stretch}.lf2-sidebar{box-sizing:border-box;flex:0 0 252px;width:252px;height:100vh;position:sticky;top:0;overflow-y:auto;background:linear-gradient(180deg,#061327,#071b37);border-right:1px solid #8c713c;padding:16px 11px;z-index:9991;font:14px/1.5 system-ui,Arial,sans-serif}.lf2-brand{display:flex;gap:10px;align-items:center;padding:8px 10px 20px;color:#f5cf7c!important;font-size:21px;font-weight:800;text-decoration:none!important}.lf2-brand img{width:42px;height:42px;object-fit:contain}.lf2-nav{display:flex;flex-direction:column;gap:4px}.lf2-nav a,.lf2-back{display:block;padding:11px 12px;color:#f2f4fa!important;text-decoration:none!important;border:1px solid transparent;border-radius:10px}.lf2-nav a:hover,.lf2-nav a.lf2-active{background:#183555!important;border-color:#d2a64f!important;color:#ffe19b!important}.lf2-back{margin-top:14px;border-top:1px solid #34425c;border-radius:0}.lf2-content{min-width:0;flex:1;padding:18px 22px;box-sizing:border-box;overflow-x:auto}.lf2-content :is(a){color:#f3c86f}.lf2-toggle{display:none;background:#d5a64d;color:#08152a;border:0;border-radius:9px;padding:10px 16px;font-weight:700;margin-bottom:14px;cursor:pointer}.lf2-overlay{display:none}@media(max-width:850px){.lf2-sidebar{position:fixed;transform:translateX(-110%);transition:transform .2s ease;box-shadow:0 0 30px #0008}.lf2-sidebar.lf2-open{transform:translateX(0)}.lf2-toggle{display:block}.lf2-overlay.lf2-open{display:block;position:fixed;inset:0;background:#0009;z-index:9990}.lf2-content{padding:14px;max-width:100vw}}
</style><style id="lf-current-admin-gold-v4">
/* Scoped presentation only. Existing forms, routes, data, and sidebar preserved. */
.lf2-content{background:#061327!important;color:#f1f4fb!important;font-family:system-ui,Arial,sans-serif!important;line-height:1.55}
.lf2-content :is(main,section,.card,.panel,.container,.box,.content,.wrap,.wrapper){box-sizing:border-box;background:#0b1c35!important;color:#edf3ff!important;border-color:#b58d49!important;border-radius:14px!important}
.lf2-content :is(main,section,.card,.panel){padding:clamp(14px,2vw,26px)!important;margin-bottom:16px!important;border:1px solid #b58d49!important;box-shadow:0 10px 28px #0002!important}
.lf2-content :is(h1,h2,h3,h4,h5,h6){color:#ffe4a1!important;line-height:1.35!important}
.lf2-content :is(p,small,label,legend,dt,dd,li){color:#e3ebf8!important}
.lf2-content :is(a,a:visited){color:#f3c86e!important}
.lf2-content :is(a:hover){color:#ffe9ad!important}
.lf2-content :is(input:not([type=checkbox]):not([type=radio]):not([type=hidden]),select,textarea){background:#f7f9fd!important;color:#12253e!important;border:1px solid #c6aa75!important;border-radius:7px!important;max-width:100%;padding:9px!important;box-sizing:border-box}
.lf2-content :is(input,select,textarea):focus-visible{outline:2px solid #ffd67e!important;outline-offset:2px}
.lf2-content :is(button,input[type=submit]){border-radius:8px!important;padding:9px 14px!important;border:1px solid #bc9047!important}
.lf2-content :is(button,input[type=submit]):not(:disabled){background:linear-gradient(110deg,#ffe9a4,#c99e54)!important;color:#10233c!important;font-weight:700!important}
.lf2-content :is(button,input[type=submit]):disabled{opacity:.6!important;cursor:not-allowed!important}
.lf2-content table{width:100%;border-collapse:collapse;background:#0b1c35!important;color:#eaf1ff!important}
.lf2-content :is(th,td){padding:10px!important;border-bottom:1px solid #40536d!important;color:#eaf1ff!important}
.lf2-content th{background:#17304f!important;color:#ffe2a1!important}
.lf2-content tr{background:transparent!important}
.lf2-content tr:hover{background:#142e4b!important}
.lf2-content :is(.table-responsive,.table-wrap,.table-wrapper){overflow-x:auto}
.lf2-content :is(.notice,.warning,.alert,.error,.success){border-radius:10px!important;padding:12px!important}
.lf2-content :is(.notice,.warning){background:#fff4df!important;color:#743c11!important;border:1px solid #d9a65c!important}
.lf2-content :is(.notice,.warning) :is(p,span,strong,small){color:#743c11!important}
.lf2-content :is(.error){background:#4a2029!important;color:#ffe1e5!important}
.lf2-content :is(.success){background:#173d32!important;color:#dfffee!important}
@media(max-width:760px){.lf2-content{padding:12px!important}.lf2-content :is(main,section,.card,.panel){padding:14px!important}}
</style>
</head>
<body>
<div class="lf2-shell"><div class="lf2-overlay" id="lf2Overlay" onclick="lf2Toggle(false)"></div><aside class="lf2-sidebar" id="lf2Sidebar" aria-label="Admin navigation"><a class="lf2-brand" href="{{ route('admin.dashboard') }}"><img src="{{ route('lumiflowon.brand.logo') }}" alt="" onerror="this.style.display='none'"><span>LumiFlowOn</span></a><nav class="lf2-nav">
<a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'lf2-active' : '' }}">▦ &nbsp; Dashboard</a>
<a href="{{ route('admin.members.index') }}" class="{{ request()->routeIs('admin.members.*') ? 'lf2-active' : '' }}">♙ &nbsp; Members</a>
<a href="{{ route('admin.magic.index') }}" class="{{ request()->routeIs('admin.magic.*') ? 'lf2-active' : '' }}">◇ &nbsp; Magic Matrix</a>
<a href="{{ route('admin.withdrawals.index') }}" class="{{ request()->routeIs('admin.withdrawals.*') ? 'lf2-active' : '' }}">▣ &nbsp; Withdrawals</a>
<a href="{{ route('admin.income.index') }}" class="{{ request()->routeIs('admin.income.*') ? 'lf2-active' : '' }}">▤ &nbsp; Income Settings</a>
<a href="{{ route('admin.unified-funding.index') }}" class="{{ request()->routeIs('admin.unified-funding.*') ? 'lf2-active' : '' }}">▥ &nbsp; Funding Audit</a>
<a href="{{ route('admin.registration-otp.index') }}" class="{{ request()->routeIs('admin.registration-otp.*') ? 'lf2-active' : '' }}">✉ &nbsp; Registration Email OTP</a>
<a href="{{ route('admin.lumiflowon.logo.index') }}" class="{{ request()->routeIs('admin.lumiflowon.logo.*') ? 'lf2-active' : '' }}">▧ &nbsp; Logo Management</a>
<a href="{{ route('admin.password-otp.index') }}" class="{{ request()->routeIs('admin.password-otp.*') ? 'lf2-active' : '' }}">◉ &nbsp; Password OTP</a>
<a href="{{ route('admin.referral-privacy.index') }}" class="{{ request()->routeIs('admin.referral-privacy.*') ? 'lf2-active' : '' }}">◈ &nbsp; Referral Privacy</a>
<a href="{{ route('admin.quick-links.index') }}" class="{{ request()->routeIs('admin.quick-links.*') ? 'lf2-active' : '' }}">↗ &nbsp; Quick Links</a>
</nav><a class="lf2-back" href="{{ route('admin.dashboard') }}">← Admin Dashboard</a></aside><main class="lf2-content"><button class="lf2-toggle" type="button" onclick="lf2Toggle(true)">☰ Admin Menu</button>

<div class="container">
    <a class="back" href="{{ route('admin.members.index') }}">
        &larr; Back to Members
    </a>

    <div class="card">
        <h1>Member Details</h1>

        <div class="field">
            <div class="label">Username</div>
            <div class="value">{{ $member->username }}</div>
        </div>

        <div class="field">
            <div class="label">Name</div>
            <div class="value">{{ $member->name }}</div>
        </div>

        <div class="field">
            <div class="label">Sponsor Username</div>
            <div class="value">{{ $member->sponsor?->username ?? 'N/A' }}</div>
        </div>

        <div class="field">
            <div class="label">Direct Referrals</div>
            <div class="value">{{ $member->referrals_count }}</div>
        </div>

        <div class="field">
            <div class="label">Account Status</div>
            <div class="value">{{ $member->status }}{{ $member->is_blocked ? ' (Account Blocked)' : '' }}</div>
        </div>

        <div class="field">
            <div class="label">Registration Date</div>
            <div class="value">
                {{ $member->created_at?->format('d M Y, h:i A') ?? 'N/A' }}
            </div>
        </div>

        <p>
            <a href="{{ route('admin.members.referrals', $member->id) }}">
                View Direct Referrals
            </a>
        </p>
        <p><a href="{{ route('admin.members.team-levels', $member->id) }}">View 51-Level Team</a></p>

        @if (session('success'))
            <p role="status">{{ session('success') }}</p>
        @endif
        @if ($member->role === 'member')
            <h2>Account Access Management</h2>
            <p>Blocking stops logins and new income eligibility. Existing team, principal and history are preserved.</p>
            @if (!$member->is_blocked)
                <form method="POST" action="{{ route('admin.members.block', $member->id) }}">
                    @csrf
                    <label for="block-reason">Reason for block (5ΓÇô500 characters)</label>
                    <textarea id="block-reason" name="reason" required minlength="5" maxlength="500" rows="3" style="display:block;width:100%;box-sizing:border-box;">{{ old('reason') }}</textarea>
                    <button type="submit">Block Member ID</button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.members.unblock', $member->id) }}">
                    @csrf
                    <label for="unblock-reason">Reason for unblock (5ΓÇô500 characters)</label>
                    <textarea id="unblock-reason" name="reason" required minlength="5" maxlength="500" rows="3" style="display:block;width:100%;box-sizing:border-box;">{{ old('reason') }}</textarea>
                    <button type="submit">Unblock Member ID</button>
                </form>
            @endif
            @error('reason') <p role="alert">{{ $message }}</p> @enderror
        @endif

        <h2>Block/Unblock History (latest 20)</h2>
        @forelse ($blockAudits as $audit)
            <div class="field">
                <strong>{{ ucfirst($audit->action) }}</strong>
                by {{ $audit->admin_username }}
                at {{ $audit->created_at }}
                <div>{{ $audit->previous_status }} ΓåÆ {{ $audit->new_status }}</div>
                <div>Reason: {{ $audit->reason }}</div>
            </div>
        @empty
            <p>No block/unblock history.</p>
        @endforelse
        <p class="notice">Member details and team views are read-only. Account access actions are audited; no wallet, income or principal adjustments are made.</p>
    </div>
</div>
</main></div><script>function lf2Toggle(open){document.getElementById('lf2Sidebar')?.classList.toggle('lf2-open',!!open);document.getElementById('lf2Overlay')?.classList.toggle('lf2-open',!!open)}</script></body>
</html>