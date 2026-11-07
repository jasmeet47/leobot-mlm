@extends('layouts.auth')
@section('title','Forgot Password')
@section('content')
<div class="auth-kicker">ACCOUNT RECOVERY</div><h1>Forgot your password?</h1><p class="auth-intro">Enter your registered email or User ID. We'll send a verification code to your account email.</p><div class="auth-steps"><span class="current">1 · Email</span><span>2 · Verify OTP</span><span>3 · New password</span></div>
@if(session('status'))<div class="auth-feedback" role="status">{{session('status')}}</div>@endif
@if($errors->any())<div class="auth-feedback error" role="alert">{{$errors->first()}}</div>@endif
<form method="post" action="{{route('password.otp.send')}}">@csrf<div class="form-group"><label for="reset-account">Email or User ID</label><input id="reset-account" name="login" value="{{old('login')}}" autocomplete="username" placeholder="you@example.com or TX123456" required maxlength="255"></div><button type="submit">Send code</button></form><p class="auth-bottom"><a href="{{route('password.otp.form')}}">I already have an OTP</a></p><div class="auth-divider"></div><p class="auth-bottom"><a href="{{route('login.form')}}">Back to Login</a></p>
@endsection
