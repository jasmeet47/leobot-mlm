
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Income Settings Audit History - LeoBot</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 24px;
            background: #f3f5f9;
            color: #1f2937;
            font-family: Arial, sans-serif;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
        }

        h1 {
            margin-bottom: 8px;
        }

        .subtitle {
            color: #64748b;
            margin-bottom: 24px;
        }

        .back {
            display: inline-block;
            color: #2563eb;
            text-decoration: none;
            margin-bottom: 20px;
        }

        .card {
            background: #ffffff;
            border-radius: 12px;
            padding: 22px;
            margin-bottom: 20px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.06);
        }

        .record-title {
            margin: 0 0 16px;
            font-size: 19px;
        }

        .meta {
            margin-bottom: 15px;
            font-size: 14px;
            line-height: 1.8;
        }

        .meta strong {
            color: #334155;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 580px;
        }

        th,
        td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            font-size: 14px;
            overflow-wrap: anywhere;
        }

        th {
            background: #eff6ff;
            color: #1e40af;
        }

        .changed {
            background: #fff7ed;
        }

        .new-value {
            font-weight: bold;
            color: #166534;
        }

        .status {
            display: inline-block;
            padding: 5px 10px;
            background: #e0f2fe;
            color: #075985;
            border-radius: 6px;
            font-size: 12px;
        }

        .empty {
            text-align: center;
            color: #64748b;
            padding: 35px;
        }

        .pagination {
            margin: 24px 0;
        }

        .pagination nav {
            max-width: 100%;
        }
    </style>
</head>

<body>

<div class="container">

    <a class="back"
       href="{{ route('admin.income.index') }}">
        &larr; Back to Income Settings
    </a>

    <h1>Income Settings Audit History</h1>

    <p class="subtitle">
        ROI and Magic Income - Admin Change Records
    </p>

    @php
        /*
         * Fields recorded by IncomeSettingsController.
         */
        $auditFields = [
            'roi.roi_enabled' =>
                'ROI System',

            'roi.distribution_mode' =>
                'ROI Distribution Mode',

            'roi.default_roi_percentage' =>
                'Default ROI Percentage (%)',

            'magic.magic_enabled' =>
                'Magic Income System',

            'magic.minimum_activation_usdt' =>
                'Minimum Activation (USDT)',

            'magic.trading_profit_pool_percent' =>
                'Magic Pool Percentage (%)',
        ];

        /*
         * Format displayed values.
         * Only use these values as display text.
         */
        $formatAuditValue = function ($key, $value) {
            if ($value === null) {
                return 'Not recorded';
            }

            if (in_array($key, [
                'roi.roi_enabled',
                'magic.magic_enabled',
            ], true)) {
                return $value ? 'ON' : 'OFF';
            }

            return (string) $value;
        };
    @endphp

    @forelse($audits as $audit)

        @php
            $oldSettings = json_decode(
                $audit->old_settings,
                true
            );

            $newSettings = json_decode(
                $audit->new_settings,
                true
            );

            $oldSettings = is_array($oldSettings)
                ? $oldSettings
                : [];

            $newSettings = is_array($newSettings)
                ? $newSettings
                : [];

            $changedCount = 0;

            foreach ($auditFields as $key => $label) {
                $oldValue = data_get(
                    $oldSettings,
                    $key
                );

                $newValue = data_get(
                    $newSettings,
                    $key
                );

                if ((string) $oldValue !== (string) $newValue) {
                    $changedCount++;
                }
            }
        @endphp

        <div class="card">

            <h2 class="record-title">
                Audit Record #{{ $audit->id }}
            </h2>

            <div class="meta">

                <div>
                    <strong>Admin:</strong>
                    {{ $audit->admin_username }}
                </div>

                <div>
                    <strong>Admin User ID:</strong>
                    {{ $audit->admin_user_id ?? 'N/A' }}
                </div>

                <div>
                    <strong>Date / Time:</strong>
                    {{ $audit->created_at }}
                </div>

                <div>
                    <strong>IP Address:</strong>
                    {{ $audit->ip_address ?? 'N/A' }}
                </div>

                <div>
                    <strong>Settings Changed:</strong>
                    <span class="status">
                        {{ $changedCount }}
                    </span>
                </div>

            </div>

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>Setting</th>
                            <th>Old Value</th>
                            <th>New Value</th>
                        </tr>
                    </thead>

                    <tbody>

                        @foreach($auditFields as $key => $label)

                            @php
                                $oldValue = data_get(
                                    $oldSettings,
                                    $key
                                );

                                $newValue = data_get(
                                    $newSettings,
                                    $key
                                );

                                $hasChanged =
                                    (string) $oldValue !==
                                    (string) $newValue;
                            @endphp

                            <tr class="{{ $hasChanged ? 'changed' : '' }}">

                                <td>
                                    {{ $label }}
                                </td>

                                <td>
                                    {{ $formatAuditValue(
                                        $key,
                                        $oldValue
                                    ) }}
                                </td>

                                <td class="{{ $hasChanged ? 'new-value' : '' }}">
                                    {{ $formatAuditValue(
                                        $key,
                                        $newValue
                                    ) }}
                                </td>

                            </tr>

                        @endforeach

                    </tbody>

                </table>

            </div>

        </div>

    @empty

        <div class="card empty">
            No income settings audit records found.
        </div>

    @endforelse

    <div class="pagination">
        {{ $audits->links() }}
    </div>

</div>

</body>
</html>
