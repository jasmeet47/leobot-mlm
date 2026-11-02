<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>LUMIFLOWON Member Staking</title>
</head>
<body style="font:16px system-ui;max-width:760px;margin:35px auto;padding:20px">
<h1>Member Staking</h1>
<p><a href="{{ route('member.dashboard') }}">Back to Dashboard</a></p>
<p>Current status: <strong>{{ $enabled ? 'Enabled' : 'Disabled' }}</strong></p>
@if(!$enabled)
<p>New staking is currently unavailable.</p>
@else
<p>Staking is enabled by administration. Financial staking submission is not available from this page until final production safety approval.</p>
@endif
</body>
</html>