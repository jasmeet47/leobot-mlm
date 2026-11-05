<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>LumiFlowOn — Staking Control</title>@include('partials.lumiflowon-financial-theme')</head>
<body style="font:16px system-ui;max-width:1100px;margin:auto;padding:24px">@include('partials.lumiflowon-financial-nav')
<h1>Admin Staking Control</h1>


<section style="padding:20px"><h2>Staking: <strong>{{ $enabled ? 'ON' : 'OFF' }}</strong></h2>
<p>Switch OFF to stop new stakes. Existing positions, recorded profit and eligible principal withdrawal requests are preserved.</p>
<form method="post" action="{{ route('admin.staking.toggle') }}">@csrf<input type="hidden" name="enabled" value="{{ $enabled ? 0 : 1 }}"><button type="submit" @disabled(!$ready || (!$enabled && !$canEnable))>{{ $enabled ? 'Turn Staking OFF' : 'Turn Staking ON' }}</button></form>
@if(!$ready || (!$enabled && !$canEnable))<p>Activation requires an operational principal payout policy and financial approval. The server checks these requirements on every request.</p>@endif
<p>No lock period or fixed return. Variable rewards require evidenced realized trading profit and independent administrator review.</p></section>
<h2>Staking positions</h2><div class="lumi-table-scroll"><table><thead><tr><th>Stake</th><th>Member</th><th>Principal</th><th>Remaining principal</th><th>Status</th></tr></thead><tbody>
@forelse($positions as $position)<tr><td>{{ $position->id }}</td><td>{{ $position->user_id }}</td><td>{{ $position->amount }} USDT</td><td>{{ bcsub((string) $position->amount, (string) $position->withdrawn_amount, 8) }} USDT</td><td>{{ $position->status }}</td></tr>@empty<tr><td colspan="5">No staking positions recorded.</td></tr>@endforelse
</tbody></table></div>{{ $positions->links() }}</body></html>
