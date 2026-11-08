<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>LumiFlowOn — My Withdrawals</title>@include('partials.lumiflowon-financial-theme')</head>
<body style="font:15px system-ui,sans-serif;max-width:760px;margin:auto;padding:2rem">@include('partials.lumiflowon-financial-nav')<h1>Withdrawal Requests</h1><p>Minimum withdrawal: <strong>{{\App\Services\WithdrawalMinimumPolicy::current()}} USDT</strong>. Enter your amount manually.</p><p>Available earnings: <strong>{{ auth()->user()->available_balance }} USDT</strong>.</p>
<p><a href="{{ route('member.staking.index') }}">Request flexible staking principal withdrawals</a>. This form uses your available earnings wallet. Requests hold the gross amount pending admin approval; no external transfer is performed automatically.</p>
@if(session('status'))<p role="status" style="background:#123d30;color:#dcfce7;padding:16px;border-radius:10px">✓ {{session('status')}}</p>@endif
@foreach($errors->all() as $error)<p style="color:#a11">{{$error}}</p>@endforeach
<form method="post" action="{{route('member.withdrawals.store')}}">@csrf
<p><label>Amount (USDT) <input name="amount" required placeholder="10.00000000" value="{{ is_string(old('amount'))?old('amount'):'' }}"></label></p>
<p><label>Unique request reference <input name="request_key" required value="{{ is_string(old('request_key'))?old('request_key'):(string) \Illuminate\Support\Str::uuid() }}"></label></p>
<p><label>Profile payout wallet <select name="usdt_wallet_id" required><option value="">Select verified wallet</option>@foreach($verifiedWallets as $wallet)<option value="{{$wallet->id}}" @selected(old("usdt_wallet_id") == $wallet->id)>{{$wallet->network}} - {{$wallet->address}}</option>@endforeach</select></label></p>@if($verifiedWallets->isEmpty())<p>No verified USDT wallet available. Withdrawal requests are disabled.</p>@endif
@include('partials.withdrawal-email-otp',['otpPosition'=>0])
<button @disabled(!$enabled || $verifiedWallets->isEmpty())>Request hold</button></form>
<h2>My History</h2><table style="width:100%"><tr><th>ID</th><th>Gross</th><th>Fee</th><th>Net</th><th>Type</th><th>Status</th></tr>
@foreach($history as $row)<tr><td>{{$row->id}}</td><td>{{$row->gross_amount}}</td><td>{{$row->fee_amount}}</td><td>{{$row->net_amount}}</td><td>{{$row->wallet_type === 'staking_principal' ? 'Principal' : 'Earnings'}}</td><td>{{$row->status}}</td></tr>@endforeach</table>{{$history->links()}}
<p><a href="{{ route('member.usdt-wallets.page') }}">Connect and Verify USDT Wallet</a></p>
</body></html>
