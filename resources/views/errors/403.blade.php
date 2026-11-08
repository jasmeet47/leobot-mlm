@extends('layouts.auth')
@section('title','Account Access')
@section('content')
<div class="auth-kicker">LumiFlowOn · Protected access</div>
<h1>Choose the correct account</h1>
@php($account=auth()->user())
@if(!$account)
<p class="auth-intro">Please sign in again to continue. Your previous session may have expired or been revoked.</p>
@elseif($account->is_blocked||$account->deleted_at!==null)
<p class="auth-intro">This account is blocked or unavailable. Contact your administrator.</p>
@elseif(request()->is('admin/*')&&$account->role==='member')
<p class="auth-intro">You are signed in as <strong>{{$account->username}}</strong>, a Member account. Admin pages require an active administrator account.</p>
<a class="auth-button" href="{{\App\Http\Middleware\EnsureMemberPin::hasConfiguredPin($account)?route('member.dashboard'):route('security-pin.form')}}">Open Member Dashboard</a>
@else
<p class="auth-intro">This action is unavailable for your account. Administrator access requires an active, authorized administrator account.</p>
@endif
<p class="auth-bottom"><a href="{{route('admin.login')}}">Admin Login</a> · <a href="{{route('login.form')}}">Member Login</a></p>
@auth<form method="post" action="{{route('logout')}}">@csrf<button type="submit">Logout / Switch Account</button></form>@endauth
@endsection
