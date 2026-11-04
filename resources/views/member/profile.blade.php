<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>LumiFlowOn | Profile</title>
<style>
*{box-sizing:border-box}body{font:16px system-ui,sans-serif;background:#090d16;color:#e2e8f0;margin:0;padding:28px}
main{max-width:660px;margin:auto;background:#101e31;border:1px solid #b68b41;border-radius:16px;padding:25px}
h1,h2{color:#f4c66b}a{color:#f4c66b}dt{font-weight:700;color:#f4c66b;margin-top:15px}dd{margin:5px 0 10px}
label{display:block;margin:14px 0 5px}input{width:100%;padding:12px;border:1px solid #826c48;background:#091827;color:white;border-radius:8px}
button{margin-top:18px;padding:12px 20px;background:#b68b41;color:#091827;border:0;border-radius:8px;font-weight:bold;cursor:pointer}
.error{color:#ffb6b6}
</style></head><body><main>
<p><a href="{{ route('member.dashboard') }}">Back to Dashboard</a></p>
<dl><dt>User ID</dt><dd>{{ $member->username }}</dd><dt>Name</dt><dd>{{ $member->name }}</dd>
<dt>Phone</dt><dd>{{ $member->phone }}</dd><dt>Email</dt><dd>{{ $member->email }}</dd></dl>
@if(session('success'))<p role="status">{{ session('success') }}</p>@endif
@if($errors->any())<div class="error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<h2>Change Password</h2>
<form method="POST" action="{{ route('member.profile.password') }}">@csrf
<label for="current_password">Current Password</label><input id="current_password" type="password" name="current_password" autocomplete="current-password" required>
<label for="new_password">New Password (minimum 12 characters)</label><input id="new_password" type="password" name="password" autocomplete="new-password" minlength="12" required>
<label for="confirm_password">Confirm New Password</label><input id="confirm_password" type="password" name="password_confirmation" autocomplete="new-password" minlength="12" required>
<label><input style="width:auto" type="checkbox" id="show-password"> Show passwords</label>
<button type="submit">Change Password</button></form></main>
<script>document.getElementById('show-password').addEventListener('change',function(){document.querySelectorAll('input[type="password"],input[data-password]').forEach(function(x){x.dataset.password='1';x.type=document.getElementById('show-password').checked?'text':'password';});});</script>
</body></html>