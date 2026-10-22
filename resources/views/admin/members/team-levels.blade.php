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