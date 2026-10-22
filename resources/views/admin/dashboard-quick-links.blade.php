<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>LumiFlowOn Dashboard Links</title>
<style>
body{font-family:Arial,sans-serif;background:#0f172a;color:#f8fafc;padding:24px}
main{max-width:850px;margin:auto}
article{background:#1e293b;padding:20px;border-radius:12px;margin:15px 0}
input{width:100%;padding:12px;box-sizing:border-box;margin:8px 0}
button{padding:12px 20px;cursor:pointer}
a{color:#93c5fd}
</style>
</head>
<body>
<main>
<h1>LumiFlowOn — Dashboard Quick Links</h1>
<p>Manage Telegram, WhatsApp, YouTube, Arattai and PDF links.</p>
<p><a href="{{ route('admin.dashboard') }}">Back to Admin Dashboard</a></p>

@if(session('success'))
<p role="status">{{ session('success') }}</p>
@endif

@if($errors->any())
<div role="alert">
@foreach($errors->all() as $error)
<p>{{ $error }}</p>
@endforeach
</div>
@endif

@foreach(['telegram','whatsapp','youtube','arattai','pdf'] as $platform)
@php
$item = $links->get($platform);
@endphp
<article>
<h2>{{ ucfirst($platform) }}</h2>
<form method="POST" action="{{ route('admin.quick-links.update') }}">
@csrf
<input type="hidden" name="platform" value="{{ $platform }}">
<label>HTTPS Link</label>
<input type="url" name="url" value="{{ $item->url ?? '' }}" placeholder="https://..." maxlength="2048">
<label>
<input type="hidden" name="enabled" value="0">
<label><input type="checkbox" name="enabled" value="1" style="width:auto" @checked((bool)($item->enabled ?? false))>
Show on Member Dashboard</label>
<button type="submit">Save {{ ucfirst($platform) }}</button>
</form>
</article>
@endforeach
</main>
</body>
</html>