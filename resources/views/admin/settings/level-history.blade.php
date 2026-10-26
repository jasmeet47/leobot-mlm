
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>51-Level Admin Audit History</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f5f7fb;
            margin: 0;
            padding: 20px;
            color: #1f2937;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }

        h1 {
            margin-top: 0;
            font-size: 26px;
        }

        .subtitle {
            color: #6b7280;
            margin-bottom: 20px;
        }

        .back-link {
            display: inline-block;
            padding: 10px 16px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .audit-card {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 18px;
            margin-bottom: 20px;
        }

        .audit-header {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 12px;
        }

        .audit-id {
            font-weight: bold;
            color: #1d4ed8;
        }

        .meta {
            font-size: 14px;
            line-height: 1.9;
        }

        .status {
            background: #eff6ff;
            color: #1d4ed8;
            padding: 10px;
            border-radius: 6px;
            margin-top: 14px;
        }

        .no-changes {
            background: #f3f4f6;
            color: #374151;
            padding: 10px;
            border-radius: 6px;
            margin-top: 14px;
        }

        details {
            margin-top: 14px;
        }

        summary {
            cursor: pointer;
            color: #2563eb;
            font-weight: bold;
        }

        .table-wrap {
            overflow-x: auto;
            margin-top: 12px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            min-width: 650px;
        }

        th, td {
            border: 1px solid #e5e7eb;
            padding: 10px;
            text-align: center;
            font-size: 14px;
        }

        th {
            background: #f3f4f6;
        }

        .empty {
            padding: 25px;
            text-align: center;
            color: #6b7280;
        }

        .pagination {
            margin-top: 24px;
        }

        @media (max-width: 600px) {
            .container {
                padding: 15px;
            }

            h1 {
                font-size: 21px;
            }
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

    <h1>51-Level Admin Audit History</h1>

    <p class="subtitle">
        Secure record of commission settings updates.
        This page is read-only.
    </p>

    <a
        href="{{ route('admin.levels.index') }}"
        class="back-link"
    >
        Back to Commission Settings
    </a>

    @forelse($audits as $audit)

        @php
            $oldSettings = json_decode(
                $audit->old_settings ?? '[]',
                true
            );

            $newSettings = json_decode(
                $audit->new_settings ?? '[]',
                true
            );

            $oldSettings = is_array($oldSettings)
                ? $oldSettings
                : [];

            $newSettings = is_array($newSettings)
                ? $newSettings
                : [];

            $oldLevels = collect($oldSettings)
                ->keyBy('level_number');

            $changes = [];

            foreach ($newSettings as $newLevel) {
                $levelNumber = (int) (
                    $newLevel['level_number'] ?? 0
                );

                $oldLevel = $oldLevels->get(
                    $levelNumber,
                    []
                );

                $oldRate = (string) (
                    $oldLevel['commission_percentage']
                    ?? '0'
                );

                $newRate = (string) (
                    $newLevel['commission_percentage']
                    ?? '0'
                );

                $oldActive = !empty(
                    $oldLevel['is_active']
                );

                $newActive = !empty(
                    $newLevel['is_active']
                );

                if (
                    $oldRate !== $newRate ||
                    $oldActive !== $newActive
                ) {
                    $changes[] = [
                        'level' => $levelNumber,
                        'old_rate' => $oldRate,
                        'new_rate' => $newRate,
                        'old_active' => $oldActive,
                        'new_active' => $newActive,
                    ];
                }
            }
        @endphp

        <div class="audit-card">

            <div class="audit-header">
                <span class="audit-id">
                    Audit Record #{{ $audit->id }}
                </span>

                <span>
                    {{ $audit->created_at }}
                </span>
            </div>

            <div class="meta">
                <div>
                    <strong>Admin:</strong>
                    {{ $audit->admin_username }}
                </div>

                <div>
                    <strong>Admin User ID:</strong>
                    {{ $audit->admin_user_id }}
                </div>

                <div>
                    <strong>IP Address:</strong>
                    {{ $audit->ip_address ?? 'Not available' }}
                </div>

                <div>
                    <strong>Levels Changed:</strong>
                    {{ count($changes) }}
                </div>
            </div>

            @if(count($changes) > 0)

                <div class="status">
                    Commission settings changes recorded.
                </div>

                <details>
                    <summary>
                        View Changed Levels
                    </summary>

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Level</th>
                                    <th>Old Commission</th>
                                    <th>New Commission</th>
                                    <th>Old Status</th>
                                    <th>New Status</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach($changes as $change)
                                    <tr>
                                        <td>
                                            {{ $change['level'] }}
                                        </td>

                                        <td>
                                            {{ $change['old_rate'] }}%
                                        </td>

                                        <td>
                                            {{ $change['new_rate'] }}%
                                        </td>

                                        <td>
                                            {{ $change['old_active'] ? 'Active' : 'Inactive' }}
                                        </td>

                                        <td>
                                            {{ $change['new_active'] ? 'Active' : 'Inactive' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>

            @else

                <div class="no-changes">
                    Settings saved without changing
                    any commission rate or status.
                </div>

            @endif

        </div>

    @empty

        <div class="empty">
            No audit history found.
        </div>

    @endforelse

    <div class="pagination">
        {{ $audits->links() }}
    </div>

</div>

</main></div><script>function lf2Toggle(open){document.getElementById('lf2Sidebar')?.classList.toggle('lf2-open',!!open);document.getElementById('lf2Overlay')?.classList.toggle('lf2-open',!!open)}</script></body>
</html>
