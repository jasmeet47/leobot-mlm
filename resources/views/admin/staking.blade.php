<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>LUMIFLOWON Staking Control</title>
</head>
<body style="font:16px system-ui;max-width:760px;margin:35px auto;padding:20px">
<h1>Admin Staking Control</h1>
<p><a href="{{ route('admin.dashboard') }}">Back to Admin Dashboard</a></p>
<p>Database schema: <strong>{{ $ready ? 'Available' : 'Not Ready' }}</strong></p>
<p>Staking status: <strong>{{ $enabled ? 'ON' : 'OFF' }}</strong></p>
<p>Real-money staking activation is withheld pending production financial verification.</p>
<p>The ON/OFF action is intentionally unavailable on this page until financial safety approval.</p>
</body>
</html>