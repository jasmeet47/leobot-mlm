<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>LumiFlowOn ΓÇö My Withdrawals</title><style id="LUMIFLOWON_MEMBER_GOLD_THEME_V1">
body{background:#090d16!important;color:#e2e8f0!important}
section,.card,table{background:#101e31!important;color:#e2e8f0!important;border-color:#b68b41!important}
h1,h2,h3{color:#f4c66b!important}
a{color:#f4c66b!important}
input,select,textarea{background:#0a1626!important;color:#fff!important;border-color:#826c48!important}
button{background:#b68b41!important;color:#08111e!important;border-radius:8px}
th,td{border-color:#41546c!important}
</style></head>
<body style="font:15px system-ui,sans-serif;max-width:760px;margin:auto;padding:2rem"><h1>Withdrawal Requests</h1><p><a href="{{route('member.dashboard')}}">ΓåÉ Member Dashboard</a></p>
<p>Available earnings wallet only. Requests hold the gross amount pending admin approval; no external transfer is performed automatically.</p>
@if(session('status'))<p>{{session('status')}}</p>@endif
@foreach($errors->all() as $error)<p style="color:#a11">{{$error}}</p>@endforeach
<form method="post" action="{{route('member.withdrawals.store')}}">@csrf
<p><label>Amount (USDT) <input name="amount" required placeholder="10.00000000"></label></p>
<p><label>Unique request reference <input name="request_key" required placeholder="WITHDRAW-2026-ABC"></label></p>
<p><label>Verified USDT wallet <select name="usdt_wallet_id" required><option value="">Select verified wallet</option>@foreach($verifiedWallets as $wallet)<option value="{{$wallet->id}}" @selected(old("usdt_wallet_id") == $wallet->id)>{{$wallet->network}} - {{$wallet->address}}</option>@endforeach</select></label></p>@if($verifiedWallets->isEmpty())<p>No verified USDT wallet available. Withdrawal requests are disabled.</p>@endif<button @disabled(!$enabled || $verifiedWallets->isEmpty())>Request hold</button></form>
<h2>My History</h2><table style="width:100%"><tr><th>ID</th><th>Amount</th><th>Status</th></tr>
@foreach($history as $row)<tr><td>{{$row->id}}</td><td>{{$row->gross_amount}}</td><td>{{$row->status}}</td></tr>@endforeach</table>{{$history->links()}}
<p><a href="{{ route('member.usdt-wallets.page') }}">Connect and Verify USDT Wallet</a></p>
</body></html>