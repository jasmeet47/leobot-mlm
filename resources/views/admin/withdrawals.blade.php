<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>LUMIFLOWON — Withdrawal Review</title>@include('partials.lumiflowon-financial-theme')</head>
<body style="font:15px system-ui;max-width:1100px;margin:auto;padding:24px">@include('partials.lumiflowon-financial-nav')<h1>Withdrawal Review</h1><p><a href="{{ route('admin.dashboard') }}">Admin dashboard</a></p>
<p>This panel never sends USDT. Review the request, lock it for payment, send the exact net amount once using the company wallet on the selected network, record the hash, and verify the confirmed transfer. Payment-processing requests cannot be rejected/refunded automatically.</p>


<h2>Principal policy</h2><p>No lock period. Current principal fee: {{ $settings?->principal_withdrawal_fee_percent }}%. @unless($settings?->principal_fee_configured_at)An administrator must explicitly set the fee before principal release can be enabled.@endunless</p>
<form method="post" action="{{ route('admin.withdrawals.fee') }}">@csrf<label>Principal fee (%) <input name="principal_fee" value="{{ $settings?->principal_withdrawal_fee_percent }}" required></label><button>Save audited fee</button></form>
<form method="post" action="{{ route('admin.withdrawals.controls') }}">@csrf
@foreach(['member_requests' => ['member_requests_enabled', 'Member withdrawal requests'], 'admin_review' => ['admin_review_enabled', 'Administrator review'], 'principal_release' => ['principal_release_enabled', 'Flexible principal release']] as $name => [$field, $label])
<input type="hidden" name="{{ $name }}" value="0"><label><input type="checkbox" name="{{ $name }}" value="1" @checked($settings?->{$field})>{{ $label }}</label>
@endforeach<button>Save audited controls</button></form>
<h2>Requests</h2>@foreach($withdrawals as $w)<section style="border:1px solid #bbb;padding:16px;margin:16px 0">
<h3>#{{ $w->id }} — member {{ $w->user_id }} — {{ $w->wallet_type === 'staking_principal' ? 'Principal' : 'Earnings' }}</h3>
<p>Gross: {{ $w->gross_amount }} · Fee: {{ $w->fee_amount }} · Net payout: {{ $w->net_amount }} USDT</p><p>{{ $w->payment_network }} — {{ $w->locked_payment_address }} — {{ str_replace('_', ' ', $w->status) }}</p>
@if($w->status === 'requested')<form method="post" action="{{ route('admin.withdrawals.approve', $w->id) }}">@csrf<button @disabled(!$writeEnabled)>Approve for external payment</button></form>@endif
@if(in_array($w->status, ['requested','approved_awaiting_payment'], true))<form method="post" action="{{ route('admin.withdrawals.reject', $w->id) }}">@csrf<button @disabled(!$writeEnabled)>Reject and restore reserved funds</button></form>@endif
@if(in_array($w->status, ['approved_awaiting_payment', 'payment_processing', 'payment_submitted'], true))
<form method="post" action="{{ route('admin.withdrawals.payment', $w->id) }}">@csrf
<input type="hidden" name="action" value="{{ $w->status === 'approved_awaiting_payment' ? 'begin' : ($w->status === 'payment_processing' ? 'submit' : 'confirm') }}">
@if($w->status !== 'approved_awaiting_payment')<label>Transaction hash <input name="tx_hash" required maxlength="66"></label>@endif
<label><input type="checkbox" name="external_payment_acknowledged" value="1" required>I checked the locked recipient, network and exact net amount. I understand this panel does not send funds.</label>
<button @disabled(!$writeEnabled)>{{ $w->status === 'approved_awaiting_payment' ? 'Start payment — lock refunds' : ($w->status === 'payment_processing' ? 'Record sent transaction' : 'Verify confirmed on-chain payment') }}</button></form>
@endif</section>@endforeach{{ $withdrawals->links() }}</body></html>
