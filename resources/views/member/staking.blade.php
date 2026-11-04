<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>LUMIFLOWON — Flexible Staking</title>@include('partials.lumiflowon-financial-theme')</head>
<body style="font:16px system-ui;max-width:960px;margin:auto;padding:24px">@include('partials.lumiflowon-financial-nav')<h1>Flexible USDT Staking</h1><p><a href="{{ route('member.dashboard') }}">Dashboard</a> · <a href="{{ route('member.deposit-transfer.index') }}">Deposit USDT</a> · <a href="{{ route('member.withdrawals.index') }}">Withdrawal history</a></p>
<p>No fixed return and no lock period. Rewards depend on realized trading profit entered and independently reviewed by administrators. Trading can lose money; future profit is not guaranteed.</p>
@if(session('status'))<p role="status">{{ session('status') }}</p>@endif
@foreach($errors->all() as $error)<p role="alert">{{ $error }}</p>@endforeach
<p>Deposit balance: {{ $member->activation_balance }} USDT · Available rewards: {{ $member->available_balance }} USDT</p>
<form method="post" action="{{ route('member.staking.store') }}">@csrf
<input type="hidden" name="request_key" value="{{ old('request_key', (string) \Illuminate\Support\Str::uuid()) }}">
<label>Amount (USDT) <input name="amount" inputmode="decimal" required></label>
<label>Fund from <select name="funding_source"><option value="deposit">Verified deposit balance</option><option value="rewards">Available rewards</option></select></label>
<button @disabled(!$stakingEnabled)>Create stake</button></form>
@if(!$stakingEnabled)<p>New stakes are currently disabled. Existing principal withdrawal requests remain available when withdrawal controls permit.</p>@endif
<h2>Principal withdrawals</h2><p>Request any available principal at any time. Current administrator-set fee: {{ $policy?->principal_withdrawal_fee_percent }}%. Fees are fixed for each request. TRC20 payouts round down to 6 decimals; any rounding difference is included in the displayed fee. Payouts require administrator review and a verified BEP20 or TRC20 wallet.</p>
@unless($policy?->principal_fee_configured_at)<p>The administrator must explicitly configure the principal fee before requests can be accepted.</p>@endunless
@foreach($positions as $position)
<section style="border:1px solid #bbb;padding:16px;margin:16px 0"><h3>Stake #{{ $position->id }}</h3><p>Original principal: {{ $position->amount }} USDT · Available principal: {{ bcsub((string) $position->amount, (string) $position->withdrawn_amount, 8) }} USDT</p>
<form method="post" action="{{ route('member.staking.withdraw', $position->id) }}">@csrf
<input type="hidden" name="request_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
<input type="hidden" name="expected_fee_percent" value="{{ $policy?->principal_withdrawal_fee_percent }}">
<label>Principal amount <input name="amount" inputmode="decimal" required></label>
<label>Receive USDT on <select name="usdt_wallet_id" required><option value="">Choose verified wallet</option>@foreach($wallets as $wallet)<option value="{{ $wallet->id }}">{{ $wallet->network }} — {{ $wallet->address }}</option>@endforeach</select></label>
<button @disabled(!$policy?->principal_fee_configured_at || !$policy?->principal_release_enabled || !$policy?->member_requests_enabled || $wallets->isEmpty())>Request principal payout</button></form></section>
@endforeach
{{ $positions->links() }}</body></html>
