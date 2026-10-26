<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Logo Settings | LumiFlowOn</title><style>body{margin:0;background:linear-gradient(135deg,#06172f,#020814);color:#f7edce;font:16px system-ui,sans-serif}main{max-width:680px;margin:5vh auto;padding:28px;background:#07172b;border:1px solid #d6af61;border-radius:18px}h1{color:#f8d88d}a{color:#f6d78d}img{display:block;max-width:100%;max-height:250px;object-fit:contain;margin:24px auto}input,button{font:inherit;padding:12px;border-radius:8px}input{max-width:100%;border:1px solid #d6af61}button{background:linear-gradient(90deg,#ffe9a6,#c39a50);border:0;color:#111;font-weight:700;cursor:pointer}.message{padding:12px;background:#183c2d;border-radius:8px}small{display:block;color:#c3c9d3;margin:12px 0}</style><style id="lumiflowon-admin-sidebar-v1">
:root{--lf-gold:#d8ae5b;--lf-navy:#06152b;--lf-text:#f1f5ff}
body{background:#06142a!important;color:var(--lf-text)!important;font-family:Inter,system-ui,Arial,sans-serif!important}
.lf-shell{display:flex;min-height:100vh}.lf-sidebar{width:256px;flex:0 0 256px;background:linear-gradient(180deg,#061327,#071c39);border-right:1px solid #34425c;padding:20px 12px;position:sticky;top:0;height:100vh;overflow-y:auto;z-index:20}.lf-side-brand{display:flex;align-items:center;gap:10px;color:#f7d887;font-weight:800;font-size:20px;text-decoration:none;margin:3px 6px 28px}.lf-side-brand img{width:40px;height:40px;object-fit:contain;border-radius:6px}.lf-nav-title{font-size:11px;text-transform:uppercase;letter-spacing:.14em;color:#9baec8;margin:20px 13px 8px}.lf-nav a{display:block;color:#e6edfa!important;text-decoration:none;padding:12px 13px;border-radius:9px;margin:3px 0;font-size:14px}.lf-nav a:hover,.lf-nav a:focus-visible{background:#173252;outline:1px solid #bd9652}.lf-nav a.lf-current{background:linear-gradient(95deg,#ffdf91,#d7a64d);color:#091427!important;font-weight:800}.lf-nav .lf-sub{padding-left:29px;font-size:13px}.lf-main{min-width:0;flex:1}.lf-main .header{background:#07172d!important;border-bottom:1px solid #3d455a!important}.lf-main .header-inner{max-width:100%!important}.lf-main .container{max-width:1400px!important;margin:24px auto!important}.lf-sidebar-toggle{display:none;border:1px solid #c49c54;background:#0a1c35;color:#ffe2a0;padding:9px 12px;border-radius:8px;cursor:pointer}.lf-overlay{display:none}.lf-main .card a{background:linear-gradient(95deg,#ffe2a0,#cfa053)!important;color:#111d2d!important;font-weight:700}.lf-main .footer{color:#bac7d9!important}.lf-main .notice{color:#f3e5c2!important}
@media(max-width:850px){.lf-sidebar{position:fixed;transform:translateX(-105%);transition:transform .2s;width:256px;box-shadow:0 0 40px #0007}.lf-sidebar.lf-open{transform:translateX(0)}.lf-sidebar-toggle{display:inline-block}.lf-overlay.lf-open{display:block;position:fixed;inset:0;background:#0009;z-index:19}.lf-main .header-inner{justify-content:flex-start!important}.lf-main .container{padding:14px!important}}
</style></head><body><div class="lf-shell"><div class="lf-overlay" id="lfOverlay" onclick="lfCloseNav()"></div>
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
  <a href="{{ route('admin.registration-otp.index') }}" class="lf-sub ">✉ &nbsp; Registration Email OTP</a>
  <a href="{{ route('admin.lumiflowon.logo.index') }}" class="lf-sub lf-current">▧ &nbsp; Logo Management</a>
  <a href="{{ route('admin.referral-privacy.index') }}" class="lf-sub">◈ &nbsp; Referral Privacy</a>
  <a href="{{ route('admin.quick-links.index') }}" class="lf-sub">↗ &nbsp; Quick Links</a>
 </nav>
</aside><div class="lf-main"><header class="header" style="padding:16px 25px"><button type="button" class="lf-sidebar-toggle" onclick="lfToggleNav()">☰ Menu</button><strong>LumiFlowOn · Logo Management</strong></header><main>
<style id="LUMIFLOWON_LOGO_FEEDBACK_FIX_V1">
.logo-feedback{margin:16px 0;padding:14px;border:1px solid #d6af61;border-radius:10px;background:#192f42;color:#fff}
.logo-feedback.error{background:#4a2020;border-color:#fca5a5}
.logo-feedback.success{background:#173d30;border-color:#86efac}
.logo-feedback strong{display:block;margin-bottom:5px}
</style>
@if(session('logo_status'))<div role="status" class="logo-feedback success"><strong>Logo Saved</strong>{{ session('logo_status') }}</div>@endif
@if($errors->any())<div role="alert" class="logo-feedback error"><strong>Logo upload failed</strong>@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
@if(session('status'))<div role="status" class="logo-feedback">{{ session('status') }}</div>@endif
<script>
document.addEventListener('DOMContentLoaded', function(){
 var form=document.querySelector('form[action*="logo-settings"]');
 if(!form)return;
 var input=form.querySelector('input[type="file"][name="logo"]');
 if(!input)return;
 var info=document.createElement('p'); info.setAttribute('aria-live','polite');
 input.insertAdjacentElement('afterend',info);
 input.addEventListener('change',function(){
   var f=input.files&&input.files[0]; if(!f){info.textContent='';return;}
   info.textContent='Selected: '+f.name+' ('+Math.ceil(f.size/1024)+' KB)';
   if(f.size>2*1024*1024)info.textContent+=' â€” exceeds 2 MB limit';
 });
 form.addEventListener('submit',function(){
   if(!input.files||!input.files.length)return;
   var button=form.querySelector('button[type="submit"]');
   if(button){button.textContent='Uploading logo...';button.disabled=true;}
 });
});
</script><p><a href="{{ route('admin.dashboard') }}">Back to Admin Dashboard</a></p><h1>LumiFlowOn Logo Settings</h1><p>Only administrators can change the logo. Updates appear on Login, Admin and Member dashboards.</p><img src="{{ route('lumiflowon.brand.logo') }}?v={{ time() }}" alt="Current LumiFlowOn logo">@if(session('logo_status'))<p class="message">{{ session('logo_status') }}</p>@endif @if($errors->any())<div role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif<form action="{{ route('admin.lumiflowon.logo.save') }}" method="post" enctype="multipart/form-data">@csrf<label for="logo">Choose PNG, JPG or WebP logo (maximum 2 MB)</label><p><input id="logo" type="file" name="logo" accept="image/png,image/jpeg,image/webp" required></p><button type="submit">Save New Logo</button></form><small>Use a trusted image. Previous logo is backed up before replacement. No financial settings are affected.</small></main></div></div><script>function lfToggleNav(){document.getElementById('lfSidebar')?.classList.toggle('lf-open');document.getElementById('lfOverlay')?.classList.toggle('lf-open')}function lfCloseNav(){document.getElementById('lfSidebar')?.classList.remove('lf-open');document.getElementById('lfOverlay')?.classList.remove('lf-open')}</script></body></html>
