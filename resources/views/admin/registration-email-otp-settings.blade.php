<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>LumiFlowOn Registration Email OTP</title><style id="lumiflowon-admin-sidebar-v1">
:root{--lf-gold:#d8ae5b;--lf-navy:#06152b;--lf-text:#f1f5ff}
body{background:#06142a!important;color:var(--lf-text)!important;font-family:Inter,system-ui,Arial,sans-serif!important}
.lf-shell{display:flex;min-height:100vh}.lf-sidebar{width:256px;flex:0 0 256px;background:linear-gradient(180deg,#061327,#071c39);border-right:1px solid #34425c;padding:20px 12px;position:sticky;top:0;height:100vh;overflow-y:auto;z-index:20}.lf-side-brand{display:flex;align-items:center;gap:10px;color:#f7d887;font-weight:800;font-size:20px;text-decoration:none;margin:3px 6px 28px}.lf-side-brand img{width:40px;height:40px;object-fit:contain;border-radius:6px}.lf-nav-title{font-size:11px;text-transform:uppercase;letter-spacing:.14em;color:#9baec8;margin:20px 13px 8px}.lf-nav a{display:block;color:#e6edfa!important;text-decoration:none;padding:12px 13px;border-radius:9px;margin:3px 0;font-size:14px}.lf-nav a:hover,.lf-nav a:focus-visible{background:#173252;outline:1px solid #bd9652}.lf-nav a.lf-current{background:linear-gradient(95deg,#ffdf91,#d7a64d);color:#091427!important;font-weight:800}.lf-nav .lf-sub{padding-left:29px;font-size:13px}.lf-main{min-width:0;flex:1}.lf-main .header{background:#07172d!important;border-bottom:1px solid #3d455a!important}.lf-main .header-inner{max-width:100%!important}.lf-main .container{max-width:1400px!important;margin:24px auto!important}.lf-sidebar-toggle{display:none;border:1px solid #c49c54;background:#0a1c35;color:#ffe2a0;padding:9px 12px;border-radius:8px;cursor:pointer}.lf-overlay{display:none}.lf-main .card a{background:linear-gradient(95deg,#ffe2a0,#cfa053)!important;color:#111d2d!important;font-weight:700}.lf-main .footer{color:#bac7d9!important}.lf-main .notice{color:#f3e5c2!important}
@media(max-width:850px){.lf-sidebar{position:fixed;transform:translateX(-105%);transition:transform .2s;width:256px;box-shadow:0 0 40px #0007}.lf-sidebar.lf-open{transform:translateX(0)}.lf-sidebar-toggle{display:inline-block}.lf-overlay.lf-open{display:block;position:fixed;inset:0;background:#0009;z-index:19}.lf-main .header-inner{justify-content:flex-start!important}.lf-main .container{padding:14px!important}}
</style><style id="lf-otp-settings-theme">.lf-settings-panel{max-width:900px;margin:38px auto;padding:32px;border:1px solid #c49b55;border-radius:18px;background:#081b34;color:#f6f6fc;box-shadow:0 14px 45px #0005}.lf-settings-panel h1{color:#fff;font-size:30px}.lf-settings-panel p,.lf-settings-panel label{color:#f1f5ff;line-height:1.6}.lf-settings-panel input[type=checkbox]{width:19px;height:19px;accent-color:#d5a64d;vertical-align:middle}.lf-settings-panel button{padding:12px 18px;border:0;border-radius:8px;background:linear-gradient(95deg,#ffe39c,#cda052);color:#0c1930;font-weight:800;cursor:pointer}.lf-settings-panel a{color:#ffe0a1!important}.lf-settings-panel [role=alert]{color:#ffbaba!important}.lf-settings-panel [role=status]{color:#aaf0bd!important}@media(max-width:850px){.lf-settings-panel{margin:15px;padding:20px}}</style></head><body><div class="lf-shell"><div class="lf-overlay" id="lfOverlay" onclick="lfCloseNav()"></div>
<aside class="lf-sidebar" id="lfSidebar" aria-label="Admin navigation">
 <a class="lf-side-brand" href="{{ route('admin.dashboard') }}"><img src="{{ route('lumiflowon.brand.logo') }}" alt="LumiFlowOn"><span>LumiFlowOn</span></a>
 <nav class="lf-nav">
  <a href="{{ route('admin.dashboard') }}" class="">▦ &nbsp; Dashboard</a>
  <a href="{{ route('admin.members.index') }}">♙ &nbsp; Members</a>
  <a href="{{ route('admin.magic.index') }}">◇ &nbsp; Magic Matrix</a>
  <a href="{{ route('admin.withdrawals.index') }}">▣ &nbsp; Withdrawals</a>
  <a href="{{ route('admin.income.index') }}">▤ &nbsp; Income Settings</a>
  <a href="{{ route('admin.unified-funding.index') }}">▥ &nbsp; Funding Audit</a>
  <div class="lf-nav-title">Settings</div>
  <a href="{{ route('admin.registration-otp.index') }}" class="lf-sub lf-current">✉ &nbsp; Registration Email OTP</a>
  <a href="{{ route('admin.lumiflowon.logo.index') }}" class="lf-sub ">▧ &nbsp; Logo Management</a>
  <a href="{{ route('admin.referral-privacy.index') }}" class="lf-sub">◈ &nbsp; Referral Privacy</a>
  <a href="{{ route('admin.quick-links.index') }}" class="lf-sub">↗ &nbsp; Quick Links</a>
 </nav>
</aside><div class="lf-main"><header class="header" style="padding:16px 25px"><button type="button" class="lf-sidebar-toggle" onclick="lfToggleNav()">☰ Menu</button><strong>LumiFlowOn · Admin Settings</strong></header><main class="lf-settings-panel">
<h1>Registration Email OTP</h1>
<p>ON: verified email OTP is mandatory for both web and API signup. OFF: normal signup without OTP.</p>
@if(session('status'))<p role="status">{{ session('status') }}</p>@endif
@if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
<form method="POST" action="{{ route('admin.registration-otp.update') }}">@csrf
<input type="hidden" name="enabled" value="0"><label><input type="checkbox" name="enabled" value="1" {{ $enabled ? 'checked' : '' }}> Require email OTP for signup</label>
<button type="submit">Save setting</button></form>
<p><a href="{{ route('admin.dashboard') }}">Back to Admin Dashboard</a></p>
</main></div></div><script>function lfToggleNav(){document.getElementById('lfSidebar')?.classList.toggle('lf-open');document.getElementById('lfOverlay')?.classList.toggle('lf-open')}function lfCloseNav(){document.getElementById('lfSidebar')?.classList.remove('lf-open');document.getElementById('lfOverlay')?.classList.remove('lf-open')}</script></body></html>
