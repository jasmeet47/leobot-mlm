<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>LUMIFLOWON Member Staking</title>
<style>
body{font:16px system-ui;max-width:760px;margin:35px auto;padding:20px;background:#101d30;color:#f1f7ff}
section{border:1px solid #38516b;border-radius:14px;padding:22px}
input,button{padding:12px;margin:8px 0;border-radius:8px}
button{cursor:pointer}
button:disabled{opacity:.5;cursor:not-allowed}
a{color:#8ccaff}
</style>
</head>
<body>
<h1>Member Staking</h1>
<p><a href="{{ route('member.dashboard') }}">Back to Dashboard</a></p>
<section>
<p>Status: <strong>{{ $enabled ? 'ON' : 'OFF' }}</strong></p>
@if(!$enabled)
<p>New staking is disabled by administration.</p>
@endif
<form method="post" action="{{ route('member.staking.store') }}">
@csrf
<label>Stake Amount (USDT)</label>
<input type="number" name="amount" min="0.00000001" step="0.00000001"
       value="{{ old('amount') }}" required @disabled(!$enabled)>
<input type="hidden" name="request_key" id="staking_request_key">
<p>Available balance and staking eligibility are verified by the server.</p>
<button type="submit" disabled title="Pending financial safety approval">
Submit Stake — Pending Approval
</button>
</form>
<p id="staking-notice">Financial staking submission is not yet activated.</p>
</section>
</body>
</html>