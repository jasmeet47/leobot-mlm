<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>LumiFlowOn — Independent Profit Review</title>@include('partials.lumiflowon-financial-theme')</head>
<body style="font:16px/1.6 system-ui;max-width:1000px;margin:auto;padding:24px">@include('partials.lumiflowon-page-brand')
<h1>Independent Trading-Profit Review</h1><p><a href="{{ route('member.dashboard') }}">Member dashboard</a></p>
<p>Review only actual realized trading statements and independently confirm funds received. Review records do not pay a wallet or authorize future returns. Statements that include your own profit are excluded.</p>
@if(session('status'))<p role="status" style="color:#86efac">{{ session('status') }}</p>@endif
@foreach($errors->all() as $error)<p role="alert">{{ $error }}</p>@endforeach
@forelse($statements as $statement)
<section style="border:1px solid #45556e;border-radius:12px;padding:20px;margin:20px 0">
<h2>Statement #{{ $statement->id }}</h2><p>Reference: {{ $statement->evidence_reference }}<br>Realized profit: {{ $statement->amount }} USDT</p>
<ul>@foreach($allocations->get($statement->id, collect()) as $allocation)<li>Member ID {{ $allocation->user_id }}: {{ $allocation->amount }} USDT gross attributable profit</li>@endforeach</ul>
@if((int)$statement->verified_by === (int)auth()->id() && $statement->status === 'independently_verified')<p>Independent approval recorded. The admin must reserve this verified profit before any separate settlement.</p>
@else<form method="post" action="{{ route('reviewer.trading-profit.attest', $statement->id) }}">@csrf
<input type="hidden" name="evidence_digest" value="{{ $digests->get($statement->id) }}">
<label><input type="checkbox" name="verified_statement_and_received_funds" value="1" required>I independently checked the external realized-profit statement, allocation amounts and received funds. These funds are not customer principal.</label><p><button>Approve verified statement</button></p></form>@endif
</section>
@empty<p>No eligible pending statements for independent review.</p>@endforelse
{{ $statements->links() }}</body></html>
