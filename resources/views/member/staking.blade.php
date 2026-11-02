<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>LUMIFLOWON Member Staking</title>
<style>
body{font:16px system-ui;max-width:760px;margin:35px auto;padding:20px;background:#101d30;color:#f1f7ff}
section{border:1px solid #38516b;border-radius:14px;padding:22px}
input,button{padding:12px;margin:8px;border-radius:8px}
button:disabled{opacity:.5;cursor:not-allowed}
a{color:#8ccaff}
</style>
</head>
<body>
<h1>Member Staking</h1>
<p><a href="{{ route('member.dashboard') }}">Back to Dashboard</a></p>
<section>
<p>Status: <strong>{{ $enabled ? 'ON' : 'OFF' }}</strong></p>
<form method="post" action="{{ route('member.staking.store') }}">
@csrf
<label for="amount">Stake Amount (USDT)</label>
<input id="amount" name="amount" type="number"
 min="0.00000001" step="0.00000001" required disabled>
<label for="security_pin">Security PIN</label>
<input id="security_pin" name="security_pin"
 type="password" autocomplete="off"
 inputmode="numeric" required disabled>
<input type="hidden" name="request_key" id="request_key">
<button type="submit" disabled>
Submit Stake - Financial Certification Pending
</button>
</form>
<p>Real staking is unavailable until financial safety approval.</p>
</section>
<script>
(function(){
 const key=document.getElementById('request_key');
 if(!window.crypto || !crypto.getRandomValues)return;
 const bytes=new Uint8Array(24);
 crypto.getRandomValues(bytes);
 key.value=Array.from(bytes,b=>b.toString(16).padStart(2,'0')).join('');
})();
</script>
</body>
</html>