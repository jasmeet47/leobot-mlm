@extends('layouts.auth')
@section('title','Reset Password')
@section('content')
@php($verified=session('password_reset_verified'))
<div class="auth-kicker">ACCOUNT RECOVERY</div><h1>{{$verified?'Set new password':'Verify your email'}}</h1><p class="auth-intro">{{$verified?'Choose a new password and enter it again to confirm.':'Enter the six-digit OTP sent to your account email. It expires in 5 minutes.'}}</p><div class="auth-steps"><span>1 · Email</span><span class="{{$verified?'':'current'}}">2 · Verify OTP</span><span class="{{$verified?'current':''}}">3 · New password</span></div>
@if(session('status'))<div class="auth-feedback" role="status">{{session('status')}}</div>@endif
@if($errors->any())<div class="auth-feedback error" role="alert">{{$errors->first()}}</div>@endif
@if($verified)
<form method="post" action="{{route('password.otp.reset')}}">@csrf<input type="hidden" name="login" value="{{$verified['login']}}"><div class="form-group"><label>New password</label><input name="password" type="password" autocomplete="new-password" placeholder="At least 8 characters" minlength="8" required></div><div class="form-group"><label>Repeat password</label><input name="password_confirmation" type="password" autocomplete="new-password" placeholder="Enter your new password again" minlength="8" required></div><button type="submit">Change password</button></form>
@else
<form method="post" action="{{route('password.otp.verify')}}">@csrf<div class="form-group"><label>Email or User ID</label><input name="login" value="{{old('login',session('reset_login'))}}" autocomplete="username" required maxlength="255"></div><div class="form-group"><label>Email OTP</label><input name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="Six-digit code" required></div><button type="submit">Verify OTP</button></form>
@endif
<div class="auth-divider"></div><p class="auth-bottom"><a href="{{route('login.form')}}">Back to Login</a> · <a href="{{route('password.otp.request')}}">Send new OTP</a></p>
@endsection
