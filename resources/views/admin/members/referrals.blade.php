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