<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>LUMIFLOWON Admin Staking</title>
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
<p>Database: <strong>{{ $ready ? 'Available' : 'Not Ready' }}</strong></p>
<p>Staking: <strong>{{ $enabled ? 'ON' : 'OFF' }}</strong></p>
<form id="offForm" method="post" action="{{ route('admin.staking.toggle') }}">
@csrf
<input type="hidden" name="enabled" value="0">
<button id="offButton" type="submit" @disabled(!$ready || !$enabled)>
Turn Staking OFF
</button>
</form>
<button type="button" disabled>
Turn Staking ON - Financial Certification Pending
</button>
<p id="result" role="status"></p>
</section>
<script>
document.getElementById('offForm').addEventListener('submit',async function(e){
 e.preventDefault();
 const button=document.getElementById('offButton');
 const result=document.getElementById('result');
 if(button.disabled)return;
 button.disabled=true;
 try {
   const response=await fetch(this.action,{
     method:'POST',
     credentials:'same-origin',
     headers:{'Accept':'application/json'},
     body:new FormData(this)
   });
   if(!response.ok)throw new Error('Rejected');
   result.textContent='Staking disabled successfully';
   location.reload();
 } catch(e) {
   result.textContent='Unable to disable staking';
   button.disabled=false;
 }
});
</script>
<!-- LUMIFLOWON_FLEX_UI_V1: PREVIEW ONLY. No approval actions enabled. -->
<section aria-label="Flexible unstake administration">
<h2>Flexible Unstake Approval - Pending Financial Certification</h2>
<p>Approval must atomically credit principal, reduce locked balance, and audit the reviewer.</p>
<button type="button" disabled>Approve Principal Return - Certification Pending</button>
<button type="button" disabled>Reject Unstake - Certification Pending</button>
</section>
</body>
</html>