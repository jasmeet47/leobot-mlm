<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>51-Level Team - LumiFlowOn Admin</title>
    <style>
        body {font-family:Arial,sans-serif;background:#f4f6f9;color:#222;margin:0;padding:24px}
        .container {max-width:1100px;margin:auto}
        .card {background:#fff;padding:24px;border-radius:12px}
        a {color:#1557b0}
        table {width:100%;border-collapse:collapse;margin-top:15px}
        th,td {text-align:left;border-bottom:1px solid #ddd;padding:10px}
        .levels {display:grid;grid-template-columns:repeat(auto-fill,minmax(112px,1fr));gap:8px}
        .levels a {background:#edf3fb;padding:9px;text-decoration:none;border-radius:6px}
        input,select,button {padding:8px}
        .table-wrap {overflow-x:auto}
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
<div class="container"><div class="card">
    <a href="{{ route('admin.members.show', $member->id) }}">&larr; Back to Member Details</a>
    <h1>51-Level Team</h1>
    <p>Member: <strong>{{ $member->username }}</strong> | Read-only team view based on sponsor relationships</p>
    <h2>Level Counts</h2>
    <div class="levels">
        @foreach ($counts as $number => $count)
            <a href="{{ route('admin.members.team-levels', ['member' => $member->id, 'level' => $number]) }}">
                Level {{ $number }}: {{ $count }}
            </a>
        @endforeach
    </div>
    <h2>Level {{ $level }} Members</h2>
    <form method="GET" action="{{ route('admin.members.team-levels', $member->id) }}">
        <label for="level">Level</label>
        <select name="level" id="level">
            @for ($i = 1; $i <= 51; $i++)
                <option value="{{ $i }}" @selected($i === $level)>{{ $i }}</option>
            @endfor
        </select>
        <label for="search">Username search</label>
        <input id="search" type="text" name="search" value="{{ $search }}" maxlength="100">
        <button type="submit">Filter</button>
    </form>
    <div class="table-wrap"><table>
        <thead><tr><th>Username</th><th>Name</th><th>Status</th><th>Registered</th><th>Details</th></tr></thead>
        <tbody>
        @forelse ($teamMembers as $person)
            <tr>
                <td>{{ $person->username }}</td><td>{{ $person->name }}</td>
                <td>{{ $person->is_blocked ? 'Blocked (' . $person->status . ')' : $person->status }}</td><td>{{ $person->created_at ?? 'N/A' }}</td>
                <td><a href="{{ route('admin.members.show', $person->id) }}">View Details</a></td>
            </tr>
        @empty
            <tr><td colspan="5">No members found on this level.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $teamMembers->links() }}
    <p>View only: no changes to accounts, team counters, balances or commissions.</p>
</div></div>
</body>
</html>