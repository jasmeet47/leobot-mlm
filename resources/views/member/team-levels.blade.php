<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>My 51-Level Team | LumiFlowOn</title>
<style>
:root{color-scheme:dark}*{box-sizing:border-box}body{margin:0;background:#07162b;color:#edf3ff;font-family:system-ui,Arial,sans-serif}main{max-width:1150px;margin:auto;padding:22px 14px}a{color:#eac678}h1{margin:14px 0 4px}.muted{color:#a9b7cd}.panel{background:#10233e;border:1px solid #2c4465;border-radius:15px;padding:18px;margin-top:18px}.levels{display:grid;grid-template-columns:repeat(auto-fill,minmax(112px,1fr));gap:9px;margin-top:14px}.level{display:block;text-decoration:none;background:#193453;border:1px solid #315071;border-radius:9px;padding:11px;color:#fff}.level.active{border-color:#eac678;background:#33415a}.level strong{display:block;color:#eac678;font-size:20px}.filters{display:flex;flex-wrap:wrap;gap:10px;align-items:end}.filters label{display:flex;flex-direction:column;gap:5px;flex:1;min-width:145px}input,select,button{font:inherit;padding:10px;border-radius:8px;border:1px solid #47617e;background:#071a30;color:#fff}button{background:#c9a44e;color:#101b2d;font-weight:700;cursor:pointer}.scroll{overflow-x:auto}table{width:100%;border-collapse:collapse;min-width:540px}th,td{text-align:left;padding:12px;border-bottom:1px solid #29425e}th{color:#eac678}.active-status{color:#71e3a3}.inactive-status{color:#f5c16a}.pagination{margin-top:15px}nav[role=navigation]{overflow-x:auto}@media(max-width:600px){main{padding:12px}.panel{padding:12px}.levels{grid-template-columns:repeat(3,minmax(0,1fr))}.level{font-size:12px;padding:8px}.level strong{font-size:17px}}
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
</head><body><main>
<a href="{{ route('member.dashboard') }}">&larr; Back to Dashboard</a>
<h1>My 51-Level Team</h1><p class="muted">Member ID: {{ $member->username }} | Only your sponsor downline | Read-only</p>
<section class="panel"><h2>Level-wise Member Counts</h2><div class="levels">
@foreach($counts as $number => $count)
<a class="level {{ $level === $number ? 'active' : '' }}" href="{{ route('member.team-levels', ['level'=>$number]) }}">Level {{ $number }}<strong>{{ number_format($count) }}</strong></a>
@endforeach
</div></section>
<section class="panel"><h2>Level {{ $level }} Members</h2>
<form class="filters" method="GET" action="{{ route('member.team-levels') }}">
<label>Level<select name="level">@for($i=1;$i<=51;$i++)<option value="{{ $i }}" @selected($i===$level)>Level {{ $i }}</option>@endfor</select></label>
<label>Member ID Search<input name="search" value="{{ $search }}" maxlength="100" placeholder="Search Member ID"></label><button type="submit">Search</button>
</form>
<div class="scroll"><table><thead><tr><th>Member ID</th><th>Member Name</th><th>Joining Date</th><th>Status</th></tr></thead><tbody>
@forelse($teamMembers as $person)
<tr><td>{{ $person->username }}</td><td>{{ $person->name }}</td><td>{{ $person->created_at ? \Illuminate\Support\Carbon::parse($person->created_at)->format('d M Y') : 'N/A' }}</td><td class="{{ $person->status === 'active' ? 'active-status' : 'inactive-status' }}">{{ $person->status === 'active' ? 'Active' : 'Inactive' }}</td></tr>
@empty<tr><td colspan="4">No members found on this level.</td></tr>@endforelse
</tbody></table></div><div class="pagination">{{ $teamMembers->links() }}</div>
<p class="muted">No contact details, investment data, wallet balances or commissions are exposed.</p></section>
</main></body></html>