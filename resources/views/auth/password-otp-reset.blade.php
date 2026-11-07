<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>LUMIFLOWON · Reset password</title><body style="font:16px Arial;background:#101b32;color:#fff;padding:3rem;max-width:520px;margin:auto"><h1>Enter your reset code</h1><p>The code expires in 5 minutes. Maximum 5 attempts.</p>@if(session('status'))<p role="status" style="background:#123c2d;color:#b8ffd4;border:1px solid #37b777;border-radius:10px;padding:14px 16px;font-weight:700;line-height:1.5;margin:16px 0">{{ session('status') }}</p>@endif @if($errors->any())<p>{{ $errors->first() }}</p>@endif
@php($verified=session('password_reset_verified'))
@if($verified)<h2>Set new password</h2><form method="post" action="{{route('password.otp.reset')}}">@csrf<input type="hidden" name="login" value="{{$verified['login']}}"><label>New password<input name="password" type="password" autocomplete="new-password" minlength="8" required></label><label>Repeat password<input name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required></label><button>Change password</button></form>
@else<form method="post" action="{{route('password.otp.verify')}}">@csrf<label>Email or Member ID<input name="login" value="{{old('login',session('reset_login'))}}" required maxlength="255"></label><label>Email OTP<input name="otp" inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code" required></label><button>Verify OTP</button></form>@endif<p><a href="{{route('login.form')}}">Back to Login</a> · <a href="{{route('password.otp.request')}}">Send new OTP</a></p>
<style id="LEOBOT_SHARED_DARK_GOLD_V2">
/* Local UI theme only. No application logic or event handlers are modified. */
:root { color-scheme: dark; --lb-bg:#091729; --lb-surface:#10243a; --lb-surface-2:#17314b; --lb-gold:#e6ba62; --lb-text:#eaf1f9; --lb-muted:#b7c7d9; --lb-border:#3b526b; }
html, body { background-color:var(--lb-bg) !important; color:var(--lb-text); }
body, main, .container, .wrapper, .page, .page-wrap, .content, .content-wrapper { color:var(--lb-text); }
h1,h2,h3,h4,h5,h6 { color:var(--lb-gold); }
a { color:var(--lb-gold); }
a:hover { color:#ffe0a1; }
/* Explicitly theme common card/table/form containers while preserving layout. */
.card, .panel, .box, .stat-card, .metric-card, .table-responsive, fieldset, .form-card, .summary-card { background-color:var(--lb-surface) !important; color:var(--lb-text) !important; border-color:var(--lb-border) !important; }
input:not([type="checkbox"]):not([type="radio"]):not([type="range"]), select, textarea { background-color:#0d2136 !important; color:var(--lb-text) !important; border-color:var(--lb-border) !important; }
input::placeholder,textarea::placeholder { color:#a9bdd0 !important; opacity:1; }
label,legend,small { color:var(--lb-muted); }
table { color:var(--lb-text); border-color:var(--lb-border); }
th { color:var(--lb-gold); border-color:var(--lb-border) !important; }
td { border-color:var(--lb-border) !important; }
thead { background-color:var(--lb-surface-2); }
tr:hover td { background-color:rgba(230,186,98,.06); }
button, .btn { border-color:var(--lb-border); }
button:disabled, .btn:disabled, [aria-disabled="true"] { opacity:.55; cursor:not-allowed; }
.alert, .notice, .message { border-color:var(--lb-border); }
:focus-visible { outline:2px solid var(--lb-gold); outline-offset:2px; }
@media (max-width: 640px) { table { max-width:100%; } input, select, textarea { max-width:100%; box-sizing:border-box; } }
</style>
</body></html>