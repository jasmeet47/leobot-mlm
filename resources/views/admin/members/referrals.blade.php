<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Direct Referrals - LumiFlowOn Admin</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f9; color: #222; margin: 0; padding: 24px; }
        .container { max-width: 1100px; margin: 0 auto; }
        .card { background: white; border-radius: 12px; padding: 24px; box-shadow: 0 2px 10px rgba(0,0,0,.08); }
        .back { display: inline-block; margin-bottom: 18px; color: #1557b0; text-decoration: none; }
        form { display: flex; gap: 8px; margin: 18px 0; flex-wrap: wrap; }
        input { padding: 9px; min-width: 220px; max-width: 100%; }
        button { padding: 9px 16px; cursor: pointer; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; border-bottom: 1px solid #eee; padding: 12px; }
        th { background: #f8f9fb; }
        .pagination { margin-top: 18px; }
        .notice { color: #666; font-size: 13px; margin-top: 20px; }
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
<div class="container">
    <a class="back" href="{{ route('admin.members.show', $member->id) }}">&larr; Back to Member Details</a>
    <div class="card">
        <h1>Direct Referrals</h1>
        <p>Member: <strong>{{ $member->username }}</strong></p>
        <form method="GET" action="{{ route('admin.members.referrals', $member->id) }}">
            <label for="search">Search username</label>
            <input id="search" type="text" name="search" value="{{ $search }}" maxlength="100" placeholder="Username">
            <button type="submit">Search</button>
            <a href="{{ route('admin.members.referrals', $member->id) }}">Clear</a>
        </form>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Username</th><th>Name</th><th>Status</th><th>Registration Date</th><th>Details</th></tr></thead>
                <tbody>
                @forelse ($referrals as $referral)
                    <tr>
                        <td>{{ $referral->username }}</td>
                        <td>{{ $referral->name }}</td>
                        <td>{{ $referral->status }}</td>
                        <td>{{ $referral->created_at?->format('d M Y, h:i A') ?? 'N/A' }}</td>
                        <td><a href="{{ route('admin.members.show', $referral->id) }}">View Details</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5">No direct referrals found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination">{{ $referrals->links() }}</div>
        <p class="notice">Read-only direct referral information. No account or financial changes can be performed from this page.</p>
    </div>
</div>
</body>
</html>