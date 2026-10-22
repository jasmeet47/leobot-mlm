<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>LumiFlowOn Registration Email OTP</title></head><body style="font:16px Arial;background:#101b32;color:#fff;padding:2rem">
<h1>Registration Email OTP</h1>
<p>ON: verified email OTP is mandatory for both web and API signup. OFF: normal signup without OTP.</p>
@if(session('status'))<p role="status">{{ session('status') }}</p>@endif
@if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
<form method="POST" action="{{ route('admin.registration-otp.update') }}">@csrf
<input type="hidden" name="enabled" value="0"><label><input type="checkbox" name="enabled" value="1" {{ $enabled ? 'checked' : '' }}> Require email OTP for signup</label>
<button type="submit">Save setting</button></form>
<p><a href="{{ route('admin.dashboard') }}">Back to Admin Dashboard</a></p>
</body></html>
