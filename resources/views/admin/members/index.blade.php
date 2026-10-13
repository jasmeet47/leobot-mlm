<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Member Management - LeoBot</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 30px; background: #f5f6fa; color: #222; }
        .container { max-width: 1100px; margin: auto; }
        .panel { background: white; padding: 22px; border-radius: 10px; }
        h1 { margin-top: 0; }
        .search { display: flex; gap: 10px; margin: 20px 0; flex-wrap: wrap; }
        input { padding: 10px; min-width: 220px; }
        button { padding: 10px 18px; cursor: pointer; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; border-bottom: 1px solid #ddd; text-align: left; }
        th { background: #f0f2f5; }
        .active { color: green; font-weight: bold; }
        .inactive { color: #b45309; font-weight: bold; }
        a { color: #1557a0; }
        .pagination { margin-top: 20px; }
    </style>
</head>
<body>
<div class="container">
    <div class="panel">
        <p><a href="{{ route('admin.dashboard') }}">← Back to Admin Dashboard</a></p>

        <h1>Member Management</h1>
        <p>Read-only member directory</p>

        <form method="GET" action="{{ route('admin.members.index') }}" class="search">
            <input
                type="text"
                name="search"
                value="{{ $search }}"
                placeholder="Search username"
                maxlength="100"
            >
            <button type="submit">Search</button>
            <a href="{{ route('admin.members.index') }}">Clear</a>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Name</th>
                        <th>Sponsor</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($members as $member)
                        <tr>
                            <td>{{ $member->username }}</td>
                            <td>{{ $member->name }}</td>
                            <td>{{ $member->sponsor?->username ?? $member->sponsor_id ?? '—' }}</td>
                            <td>
                                <span class="{{ $member->status === 'active' ? 'active' : 'inactive' }}">
                                    {{ ucfirst($member->status) }}
                                </span>
                            </td>
                            <td>{{ $member->created_at?->format('d M Y') ?? '—' }}</td>
                            <td>
                                <a href="{{ route('admin.members.show', $member->id) }}">
                                    View Details
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">No members found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination">
            {{ $members->links() }}
        </div>
    </div>
</div>
</body>
</html>