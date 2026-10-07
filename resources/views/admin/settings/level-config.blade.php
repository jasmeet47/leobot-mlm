<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>51 Level Configuration</title>

    <style>
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

        .success {
            background: #d1fae5;
            color: #065f46;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
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
            width: 120px;
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
    </style>
</head>

<body>

<div class="container">

    <h1>51 Level Generation Commission</h1>

    @if(session('success'))
        <div class="success">
            {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.levels.update') }}">
        @csrf

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
                        $setting = $levels->firstWhere('level_number', $level);
                    @endphp

                    <tr>
                        <td>
                            Level {{ $level }}
                        </td>

                        <td>
                            <input
                                type="number"
                                name="commission[{{ $level }}]"
                                value="{{ $setting ? $setting->commission_percentage : 0 }}"
                                min="0"
                                max="100"
                                step="0.0001"
                            >
                        </td>

                        <td>
                            <input
                                type="checkbox"
                                name="active_levels[{{ $level }}]"
                                value="1"
                                {{ $setting && $setting->is_active ? 'checked' : '' }}
                            >
                        </td>
                    </tr>

                @endfor
            </tbody>
        </table>

        <button type="submit" class="save-button">
            Save 51 Level Settings
        </button>

    </form>

</div>

</body>
</html>