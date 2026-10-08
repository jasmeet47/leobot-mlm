
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
</head>

<body>

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

</body>
</html>
