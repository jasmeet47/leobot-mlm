<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>LumiFlowOn ΓÇö Magic Matrix</title>
<style>body{font:15px system-ui,sans-serif;max-width:1000px;margin:2rem auto;padding:0 1rem;color:#1b2835}section{border:1px solid #d1d5db;border-radius:8px;padding:18px;margin:16px 0}label{display:block;margin:9px 0}input,select{padding:8px;max-width:220px}table{border-collapse:collapse;width:100%}td,th{border-bottom:1px solid #ddd;padding:7px;text-align:left}.err{color:#991b1b}button{padding:9px 15px}small{color:#576172}</style></head><body>
<h1>Magic Matrix ΓÇö 20 Levels</h1>
<p><a href="{{ route('admin.dashboard') }}">ΓåÉ Admin dashboard</a></p>
<p><strong>Default financial switches remain OFF.</strong> 20-level percentage preset is only a proposal and requires explicit administrator review.</p>
@if(session('status'))<p role="status">{{session('status')}}</p>@endif
@if($errors->any())<div class="err">@foreach($errors->all() as $error)<p>{{$error}}</p>@endforeach</div>@endif
<section aria-label="Magic Team display levels">
<h2>Member Magic Team — Visible Levels</h2>
<p>Controls display only; does not change matrix depth, enrollment, settlement, level rates or payouts.</p>
<form method="post" action="{{ route('admin.magic.display-levels') }}">@csrf
<label>Show levels 1 through
<select name="visible_levels" required>
@for($n = 0; $n <= 20; $n++)
<option value="{{ $n }}" @selected((int) old('visible_levels', $settings?->visible_levels ?? 20) === $n)>{{ $n === 0 ? '0 — Hide Magic Team' : $n . ' level(s)' }}</option>
@endfor
</select></label>
<button type="submit">Save visible levels</button>
</form></section>
<section><h2>Magic Settings</h2>
<p>Global Magic: <strong>{{ ($global?->magic_enabled ?? false) ? 'ON' : 'OFF' }}</strong> (controlled separately in Income Settings).</p>
<p>Matrix Enrollment: {{($settings?->enrollment_enabled??false)?'ON':'OFF'}}; Re-Topup: {{($settings?->topup_enabled??false)?'ON':'OFF'}}; Settlement: {{($settings?->settlement_enabled??false)?'ON':'OFF'}}</p>
<form method="post" action="{{route('admin.magic.policy')}}">@csrf
<label>Minimum qualifying investment (USDT) <input name="minimum_usdt" value="{{old('minimum_usdt',$settings?->minimum_tier_usdt??'50')}}" required></label>
<label>Magic earning cap <select name="earning_multiplier">@foreach([3,4,5] as $m)<option value="{{$m}}" @selected((int)($settings?->earning_cap_multiplier??3)===$m)>{{$m}}├ù</option>@endforeach</select></label>
<label>Distribution frequency (manual only until scheduler reviewed) <select name="frequency">@foreach(['manual','daily','weekly','monthly'] as $f)<option value="{{$f}}" @selected(($settings?->frequency??'manual')===$f)>{{$f}}</option>@endforeach</select></label>
<button @disabled(!$canEdit)>Save policy</button></form>
<form method="post" action="{{route('admin.magic.switches')}}">@csrf
@foreach(['enrollment_enabled'=>'Enrollment','topup_enabled'=>'Re-Topup','settlement_enabled'=>'Settlement'] as $key=>$label)<label>{{$label}} <select name="{{$key}}"><option value="0" @selected(!($settings?->$key??false))>OFF</option><option value="1" @selected((bool)($settings?->$key??false))>ON</option></select></label>@endforeach
<button @disabled(!$canEdit)>Update database switches</button><small>Code and production approval gates still apply.</small></form></section>
<section><h2>20-Level Magic Pool Rate Table</h2><p>Proposed: equal <strong>5% on each level</strong> = 100%. Not a guarantee of member earnings. Empty/ineligible positions remain held.</p>
<form method="post" action="{{route('admin.magic.rates')}}">@csrf
<table><thead><tr><th>Level</th><th>Percentage of source ID's Magic allocation</th></tr></thead><tbody>
@foreach($rates as $rate)<tr><td>{{(int)$rate->level}}</td><td><input type="text" name="rates[{{(int)$rate->level}}]" value="{{old('rates.'.(int)$rate->level,(string)$rate->pool_percent)}}" required></td></tr>@endforeach</tbody></table>
<button type="button" onclick="document.querySelectorAll('input[name^=\"rates[\"]').forEach(x=>x.value='5.000000000')" @disabled(!$canEditRates)>Fill proposed 5% ├ù 20</button>
<button @disabled(!$canEditRates)>Save audited rate table</button></form></section>
<section><h2>Manual verified settlement (one source ID)</h2>
<p>No automatic payout is triggered by funding. Requires verified existing funding, live eligibility, caps, 100% rate table and separate settlement approval.</p>
<form method="post" action="{{route('admin.magic.settle')}}">@csrf
<label>Unified funding ID <input type="number" min="1" name="funding_id" required></label>
<label>Profit source member ID <input type="number" min="1" name="source_user_id" required></label>
<button @disabled(!$canSettle)>Settle checked source</button></form></section>
<section><h2>Recent Magic Entitlements</h2><table><tr><th>Funding</th><th>Level</th><th>Beneficiary</th><th>Paid</th><th>Held</th><th>Status</th></tr>
@foreach($recent as $entry)<tr><td>{{$entry->funding_id}}</td><td>{{$entry->level}}</td><td>{{$entry->beneficiary_user_id??'ΓÇö'}}</td><td>{{$entry->paid_amount}}</td><td>{{$entry->held_amount}}</td><td>{{$entry->status}}</td></tr>@endforeach</table></section>
</body></html>