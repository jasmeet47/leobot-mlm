@php
$messages=[];
$feedbackErrors=session('errors',new \Illuminate\Support\ViewErrorBag);
if(isset($failureMessage)) {$feedbackErrors=new \Illuminate\Support\ViewErrorBag; $feedbackErrors->put('default',new \Illuminate\Support\MessageBag(['save'=>$failureMessage]));}
if($feedbackErrors->any()) {$messages[]=['error','Error! Your changes could not be saved. Please try again.','त्रुटि! आपके बदलाव सेव नहीं हो सके। कृपया दोबारा प्रयास करें।',implode(' ',array_unique($feedbackErrors->all()))];}
else {foreach(['success','status'] as $key){if(session($key)){$messages[]=['success','Success! Your changes have been saved successfully.','सफल! आपके बदलाव सफलतापूर्वक सेव हो गए हैं।',session($key)];break;}}}
if(session('warning')){$messages[]=['warning','Warning','कृपया ध्यान दें',session('warning')];}
@endphp
<style>.lf-toast-stack{position:fixed;top:18px;right:18px;z-index:11000;width:min(420px,calc(100vw - 30px));display:grid;gap:10px;font:14px/1.5 system-ui}.lf-toast{position:relative;border-radius:14px;padding:17px 42px 17px 17px;box-shadow:0 14px 40px #0004;border:1px solid;animation:lfToastIn .2s ease}.lf-toast-success{background:#123d30;color:#dcfce7;border-color:#4ade80}.lf-toast-error{background:#4c1823;color:#ffe4e6;border-color:#fb7185}.lf-toast-warning{background:#48310c;color:#fef3c7;border-color:#fbbf24}.lf-toast small,.lf-toast p{color:inherit!important;margin:5px 0}.lf-toast-close{position:absolute;right:9px;top:8px;background:transparent!important;color:inherit!important;border:0!important;padding:5px!important;cursor:pointer;font-size:20px}@keyframes lfToastIn{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:none}}@media(prefers-reduced-motion:reduce){.lf-toast{animation:none}}</style>
<div class="lf-toast-stack" aria-label="Save notifications">
@foreach($messages as [$kind,$title,$hindi,$detail])
<div class="lf-toast lf-toast-{{ $kind }}" role="{{ $kind==='error'?'alert':'status' }}" data-lf-toast="{{ $kind }}"><strong>{{ $kind==='success'?'✓':($kind==='error'?'✕':'⚠') }} {{ $title }}</strong><p lang="hi">{{ $hindi }}</p><small>{{ $detail }}</small><button class="lf-toast-close" type="button" aria-label="Dismiss notification">×</button></div>
@endforeach
</div>
<script>document.querySelectorAll('[data-lf-toast]').forEach(el=>{el.querySelector('button').addEventListener('click',()=>el.remove());if(el.dataset.lfToast==='success'){let timer;const start=()=>timer=setTimeout(()=>el.remove(),5000);start();el.addEventListener('mouseenter',()=>clearTimeout(timer));el.addEventListener('focusin',()=>clearTimeout(timer));el.addEventListener('mouseleave',start);}});</script>
