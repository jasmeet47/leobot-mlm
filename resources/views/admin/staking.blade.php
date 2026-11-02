<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>LUMIFLOWON Admin Staking Control</title>
<style>
body{font:16px system-ui;max-width:760px;margin:35px auto;padding:20px;background:#101d30;color:#f1f7ff}
section{border:1px solid #38516b;border-radius:14px;padding:22px}
button{padding:12px;margin:8px;border-radius:8px}
button:disabled{opacity:.5;cursor:not-allowed}
a{color:#8ccaff}
</style>
</head>
<body>
<h1>Admin Staking Control</h1>
<p><a href="{{ route('admin.dashboard') }}">Back to Dashboard</a></p>
<section>
<p>Database schema: <strong>{{ $ready ? 'Available' : 'Not Ready' }}</strong></p>
<p>Current Staking Status: <strong>{{ $enabled ? 'ON' : 'OFF' }}</strong></p>
<form method="post" action="{{ route('admin.staking.toggle') }}">
@csrf
<input type="hidden" name="enabled" value="1">
<button type="submit" disabled>Turn Staking ON — Pending Approval</button>
</form>
<form method="post" action="{{ route('admin.staking.toggle') }}">
@csrf
<input type="hidden" name="enabled" value="0">
<button type="submit" @disabled(!$ready)>Turn Staking OFF</button>
</form>
<p>ON remains unavailable until production financial readiness is verified.</p>
</section>
</body>
</html>