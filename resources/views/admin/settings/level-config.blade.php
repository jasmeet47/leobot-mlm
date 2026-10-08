
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>51 Level Configuration</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f5f7fb;
            margin: 0;
            padding: 30px;
        }

        .container {
            max-width: 1000px;
            margin: auto;
            background: #ffffff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }

        h1 {
            margin-top: 0;
            text-align: center;
        }

        .admin-actions {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 20px;
        }

        .history-button {
            display: inline-block;
            padding: 11px 18px;
            background: #374151;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
        }

        .history-button:hover {
            background: #1f2937;
        }

        .success {
            background: #d1fae5;
            color: #065f46;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .error-box {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .error-box ul {
            margin: 8px 0 0;
            padding-left: 20px;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
        }

        th {
            background: #f0f2f5;
        }

        input[type="number"] {
            width: 140px;
            max-width: 100%;
            padding: 7px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        .save-button {
            margin-top: 20px;
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 6px;
            background: #2563eb;
            color: white;
            font-size: 16px;
            cursor: pointer;
        }

        .save-button:hover {
            background: #1d4ed8;
        }

        @media (max-width: 600px) {
            body {
                padding: 12px;
            }

            .container {
                padding: 15px;
            }

            input[type="number"] {
                width: 110px;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <h1>51 Level Generation Commission</h1>

    <!-- Admin Audit History Button -->
    <div class="admin-actions">
        <a
            href="{{ route('admin.levels.history') }}"
            class="history-button"
        >
            View Audit History
        </a>
    </div>

    <!-- Success Message -->
    @if(session('success'))
        <div class="success">
            {{ session('success') }}
        </div>
    @endif

    <!-- Validation Errors -->
    @if($errors->any())
        <div class="error-box">
            <strong>Unable to save settings:</strong>

            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- 51-Level Settings Form -->
    <form
        method="POST"
        action="{{ route('admin.levels.update') }}"
    >
        @csrf

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Level</th>
                        <th>Commission %</th>
                        <th>Active</th>
                    </tr>
                </thead>

                <tbody>
                    @for($level = 1; $level <= 51; $level++)

                        @php
                            $setting = $levels->firstWhere(
                                'level_number',
                                $level
                            );

                            $commissionValue = $setting
                                ? $setting->commission_percentage
                                : 0;

                            $isActive = $setting
                                ? (bool) $setting->is_active
                                : false;
                        @endphp

                        <tr>
                            <td>
                                Level {{ $level }}
                            </td>

                            <td>
                                <input
                                    type="number"
                                    name="commission[{{ $level }}]"
                                    value="{{ $commissionValue }}"
                                    min="0"
                                    max="100"
                                    step="0.000000001"
                                >
                            </td>

                            <td>
                                <input
                                    type="checkbox"
                                    name="active_levels[{{ $level }}]"
                                    value="1"
                                    {{ $isActive ? 'checked' : '' }}
                                >
                            </td>
                        </tr>

                    @endfor
                </tbody>
            </table>
        </div>

        <button
            type="submit"
            class="save-button"
        >
            Save 51 Level Settings
        </button>

    </form>

</div>

</body>
</html>
