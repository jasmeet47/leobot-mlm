<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Income Settings Audit History - LeoBot</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 24px; background: #f3f5f9;
            color: #1f2937; font-family: Arial, sans-serif;
        }
        .container { max-width: 1100px; margin: 0 auto; }
        h1 { margin-bottom: 8px; }
        .subtitle { color: #64748b; margin-bottom: 24px; line-height: 1.5; }
        .back {
            display: inline-block; color: #2563eb;
            text-decoration: none; margin-bottom: 20px;
        }
        .card {
            background: #ffffff; border-radius: 12px; padding: 22px;
            margin-bottom: 20px; box-shadow: 0 3px 12px rgba(0,0,0,0.06);
        }
        .record-title { margin: 0 0 16px; font-size: 19px; }
        .meta { margin-bottom: 15px; font-size: 14px; line-height: 1.8; }
        .meta strong { color: #334155; }
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 580px; }
        th, td {
            padding: 12px; border-bottom: 1px solid #e5e7eb;
            text-align: left; font-size: 14px; overflow-wrap: anywhere;
        }
        th { background: #eff6ff; color: #1e40af; }
        .changed { background: #fff7ed; }
        .new-value { font-weight: bold; color: #166534; }
        .status {
            display: inline-block; padding: 5px 10px; background: #e0f2fe;
            color: #075985; border-radius: 6px; font-size: 12px;
        }
        .empty { text-align: center; color: #64748b; padding: 35px; }
        .pagination { margin: 24px 0; }
        .pagination nav { max-width: 100%; }
        .note { color: #64748b; font-size: 13px; line-height: 1.5; }
    </style>
</head>
<body>
<div class="container">
    <a class="back" href="{{ route('admin.income.index') }}">
        &larr; Back to Income Settings
    </a>

    <h1>Income Settings Audit History</h1>
    <p class="subtitle">
        ROI, Magic, SELF, 51-Level Generation and Investment Distribution - Admin Change Records
    </p>

    @php
        // Use the exact nested keys written by IncomeSettingsController.
        // Old records may contain ONLY ROI and Magic. Never invent historical values.
        $auditFields = [
            'roi.roi_enabled' => 'ROI System',
            'roi.distribution_mode' => 'ROI Distribution Mode',
            'roi.default_roi_percentage' => 'Default ROI Percentage (%)',
            'magic.magic_enabled' => 'Magic Income System',
            'magic.minimum_activation_usdt' => 'Minimum Activation (USDT)',
            'magic.trading_profit_pool_percent' => 'Magic Pool Percentage (%)',
            'generation.generation_enabled' => 'Generation Income System',
            'generation.trading_profit_pool_percent' => 'Generation Pool Percentage (%)',
            'profit_sharing.self_profit_percent' => 'SELF Profit Percentage (%)',
            'profit_sharing.combined_funding_enabled' => 'Combined Funding',
            'investment_distribution.is_enabled' => 'Investment Distribution System',
            'investment_distribution.distribution_percent' => 'Investment Distribution Budget (%)',
            'investment_distribution.max_distribution_level' => 'Investment Maximum Level',
        ];

        $booleanFields = [
            'roi.roi_enabled', 'magic.magic_enabled',
            'generation.generation_enabled',
            'profit_sharing.combined_funding_enabled',
            'investment_distribution.is_enabled',
        ];

        $numberFields = [
            'roi.default_roi_percentage',
            'magic.minimum_activation_usdt',
            'magic.trading_profit_pool_percent',
            'generation.trading_profit_pool_percent',
            'profit_sharing.self_profit_percent',
            'investment_distribution.distribution_percent',
            'investment_distribution.max_distribution_level',
        ];

        // PostgreSQL may supply booleans as t/f; PHP (bool) 'f' is incorrect.
        $parseBoolean = function ($value): ?bool {
            if (is_bool($value)) {
                return $value;
            }
            if (!is_string($value) && !is_int($value)) {
                return null;
            }
            $normalized = strtolower(trim((string) $value));
            if (in_array($normalized, ['1', 't', 'true', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'f', 'false', 'no', 'off'], true)) {
                return false;
            }
            return null;
        };

        $formatAuditValue = function (string $key, $value, bool $present) use ($booleanFields, $parseBoolean): string {
            if (!$present) {
                return 'Not recorded';
            }
            if ($value === null) {
                return 'NULL';
            }
            if (in_array($key, $booleanFields, true)) {
                $parsed = $parseBoolean($value);
                return $parsed === null ? 'Unknown value' : ($parsed ? 'ON' : 'OFF');
            }
            return is_scalar($value) ? (string) $value : '[non-scalar value]';
        };

        $valueChanged = function (string $key, $oldValue, $newValue) use ($booleanFields, $numberFields, $parseBoolean): bool {
            if (in_array($key, $booleanFields, true)) {
                $a = $parseBoolean($oldValue);
                $b = $parseBoolean($newValue);
                if ($a !== null && $b !== null) {
                    return $a !== $b;
                }
            }
            if (in_array($key, $numberFields, true)
                && is_scalar($oldValue) && is_scalar($newValue)
                && preg_match('/\A\d+(?:\.\d+)?\z/D', (string) $oldValue)
                && preg_match('/\A\d+(?:\.\d+)?\z/D', (string) $newValue)) {
                return bccomp((string) $oldValue, (string) $newValue, 9) !== 0;
            }
            return $oldValue !== $newValue;
        };
    @endphp

    @forelse($audits as $audit)
        @php
            $oldSettings = json_decode($audit->old_settings, true);
            $newSettings = json_decode($audit->new_settings, true);
            $oldSettings = is_array($oldSettings) ? $oldSettings : [];
            $newSettings = is_array($newSettings) ? $newSettings : [];

            $displayRows = [];
            $changedCount = 0;
            foreach ($auditFields as $key => $label) {
                $hasOld = \Illuminate\Support\Arr::has($oldSettings, $key);
                $hasNew = \Illuminate\Support\Arr::has($newSettings, $key);
                if (!$hasOld && !$hasNew) {
                    // A legacy record should not pretend it saved newer fields.
                    continue;
                }

                $old = $hasOld ? data_get($oldSettings, $key) : null;
                $new = $hasNew ? data_get($newSettings, $key) : null;
                // A missing field is not proof of a change; it was not audited.
                $changed = $hasOld && $hasNew && $valueChanged($key, $old, $new);
                if ($changed) {
                    $changedCount++;
                }
                $displayRows[] = [
                    'key' => $key,
                    'label' => $label,
                    'old' => $formatAuditValue($key, $old, $hasOld),
                    'new' => $formatAuditValue($key, $new, $hasNew),
                    'changed' => $changed,
                ];
            }
        @endphp

        <div class="card">
            <h2 class="record-title">Audit Record #{{ $audit->id }}</h2>
            <div class="meta">
                <div><strong>Admin:</strong> {{ $audit->admin_username }}</div>
                <div><strong>Admin User ID:</strong> {{ $audit->admin_user_id ?? 'N/A' }}</div>
                <div><strong>Date / Time:</strong> {{ $audit->created_at }}</div>
                <div><strong>IP Address:</strong> {{ $audit->ip_address ?? 'N/A' }}</div>
                <div><strong>Settings Changed:</strong> <span class="status">{{ $changedCount }}</span></div>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr><th>Setting</th><th>Old Value</th><th>New Value</th></tr>
                    </thead>
                    <tbody>
                        @forelse($displayRows as $row)
                            <tr class="{{ $row['changed'] ? 'changed' : '' }}">
                                <td>{{ $row['label'] }}</td>
                                <td>{{ $row['old'] }}</td>
                                <td class="{{ $row['changed'] ? 'new-value' : '' }}">{{ $row['new'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="empty">No recognized settings in this historical record.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="note">
                Only settings actually saved in this audit record are shown.
                Historical records cannot be retroactively filled with later settings.
            </p>
        </div>
    @empty
        <div class="card empty">No income settings audit records found.</div>
    @endforelse

    <div class="pagination">{{ $audits->links() }}</div>
</div>
</body>
</html>
