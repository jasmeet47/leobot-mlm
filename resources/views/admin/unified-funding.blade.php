<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>LeoBot | Unified Funding Audit</title>
<style>
body{font:16px Arial,sans-serif;max-width:1100px;margin:30px auto;padding:0 18px;color:#172b4d;background:#f5f7fb}
main,section{background:white;border:1px solid #dce2ed;border-radius:10px;padding:22px;margin:18px 0}
h1,h2{margin-top:0}table{border-collapse:collapse;width:100%;font-size:14px}td,th{border-bottom:1px solid #ddd;padding:10px;text-align:left;word-break:break-word}
.notice{border-left:5px solid #c07800;background:#fff7e6;padding:14px}input,textarea{display:block;width:100%;box-sizing:border-box;padding:10px;margin:8px 0 18px}
button{padding:11px 18px}a{color:#185abc}.error{color:#b00020}label{display:block;font-weight:bold}label.checkbox{font-weight:normal}
</style>
</head>
<body><main>
<a href="{{ route('admin.dashboard') }}">&larr; Admin Dashboard</a>
<h1>Unified Trading Profit — Audit &amp; Reserves</h1>
<p class="notice"><strong>Production HOLD.</strong> Member activation, withdrawals, payouts and generation distribution are not enabled by this page. Funding entry is disabled unless separately approved. Source-of-funds verification must be independently completed before approval.</p>
<p><strong>Configured rates:</strong> Self {{ $settings->self_profit_percent ?? 'N/A' }}%, Generation {{ $generation->trading_profit_pool_percent ?? 'N/A' }}%, Magic {{ $magic->trading_profit_pool_percent ?? 'N/A' }}%.</p>
<p><strong>Combined funding switch:</strong> {{ in_array(strtolower((string) ($settings->combined_funding_enabled ?? '')), ['1','t','true','yes','on'], true) ? 'ON' : 'OFF' }}. <strong>Admin entry gate:</strong> {{ $entryEnabled ? 'Approved for this environment' : 'OFF' }}.</p>
@if(session('status'))<p role="status">{{ session('status') }}</p>@endif
@if($errors->any())<div class="error" role="alert"><strong>Request not processed:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
</main>
@if($entryEnabled)
<section><h2>Reserve funding entry (no payout)</h2>
<p>Only use independently verified, unencumbered profit that is already available in the company wallet. Enter each member's attributable profit; the total must equal company profit. This action records immutable ledger and reserve entries.</p>
<form method="post" action="{{ route('admin.unified-funding.store') }}">
@csrf
<label for="profit_reference">Unique verified profit statement reference</label>
<input id="profit_reference" name="profit_reference" maxlength="100" required value="{{ old('profit_reference') }}" autocomplete="off">
<label for="company_profit">Company profit (USDT)</label>
<input id="company_profit" name="company_profit" required value="{{ old('company_profit') }}" placeholder="1000.00000000" autocomplete="off">
<label for="member_breakdown">Member profit breakdown — one user_id,amount per line</label>
<textarea id="member_breakdown" name="member_breakdown" rows="8" maxlength="200000" required placeholder="101,600.00000000&#10;102,400.00000000">{{ old('member_breakdown') }}</textarea>
<label class="checkbox"><input type="checkbox" name="source_verified" value="1" required style="display:inline;width:auto"> I confirm independent source verification and that these funds are not previously allocated.</label>
<button type="submit">Record internal reserves (NO PAYOUT)</button>
</form></section>
@endif
<section><h2>Immutable funding history</h2>
<div style="overflow-x:auto"><table><thead><tr><th>ID</th><th>Reference</th><th>Verified profit</th><th>Self</th><th>Generation</th><th>Magic</th><th>Status</th><th>Created</th></tr></thead><tbody>
@forelse($fundings as $row)
<tr><td>{{ $row->id }}</td><td>{{ $row->profit_reference }}</td><td>{{ $row->verified_profit_amount }}</td><td>{{ $row->self_amount }}</td><td>{{ $row->generation_amount }}</td><td>{{ $row->magic_amount }}</td><td>{{ $row->status }}</td><td>{{ $row->created_at }}</td></tr>
@empty<tr><td colspan="8">No unified funding entries yet.</td></tr>@endforelse
</tbody></table></div>{{ $fundings->links() }}
</section></body></html>
