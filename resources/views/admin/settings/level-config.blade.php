
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
