<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Deposits &amp; Transfers | LumiFlowOn</title>
<style>
:root{color-scheme:dark;--bg:#080f1e;--panel:#101e32;--tile:#172a43;--border:#30445f;--text:#edf4ff;--muted:#a6bad0;--gold:#f2bf61;--green:#4ddbb1}
*{box-sizing:border-box}body{margin:0;background:radial-gradient(ellipse at 80% 0%,#173455 0%,#0a1526 45%,#070d18 100%) fixed;color:var(--text);font:15px/1.55 Inter,system-ui,-apple-system,'Segoe UI',sans-serif}
main{width:min(1100px,94%);margin:0 auto;padding:24px 0 70px}.top,.panel{background:var(--panel);border:1px solid var(--border);border-radius:16px;padding:clamp(18px,3vw,26px)}.top{display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap}.brand{color:var(--gold);font-size:21px;font-weight:850;letter-spacing:.1em}.eyebrow{font-size:12px;color:var(--muted)}a{color:#b6d7ff}.back{display:inline-flex;text-decoration:none;border:1px solid var(--border);background:var(--tile);padding:10px 14px;border-radius:10px;color:var(--text)}h1{font-size:clamp(24px,4vw,34px);margin:26px 0 8px}h2{font-size:19px;margin:0 0 10px}.lead,.hint{color:var(--muted)}.notice{padding:13px 16px;border:1px solid #40546e;background:#152740;border-radius:12px;margin:16px 0}.notice strong{color:var(--gold)}.success{border-color:#22664e;color:#a4f0d5}.errors{border-color:#8e4754;color:#ffd0d5}.layout{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;margin:22px 0}.panel{min-width:0}.panel h2:before{content:'';display:inline-block;width:3px;height:22px;background:var(--gold);vertical-align:middle;margin-right:11px;border-radius:2px}.field{display:block;margin-top:15px;font-weight:600;font-size:13px}.field span{display:block;margin-bottom:7px}input,select{width:100%;min-height:44px;border:1px solid #405773;border-radius:9px;background:#0b192c;color:var(--text);font:inherit;padding:10px 12px}input::placeholder{color:#8195ae}input:focus,select:focus,a:focus-visible,button:focus-visible{outline:2px solid var(--gold);outline-offset:2px}button{margin-top:18px;min-height:44px;border:0;border-radius:9px;padding:10px 16px;background:var(--gold);color:#172238;font:inherit;font-weight:800;cursor:pointer}button:hover{filter:brightness(1.07)}.tag{display:inline-block;padding:4px 10px;border-radius:999px;background:#263b54;color:#cfe1f5;font-size:12px}.table-wrap{overflow:auto;border:1px solid var(--border);border-radius:12px;margin-top:15px}table{border-collapse:collapse;width:100%;min-width:420px}th,td{text-align:left;padding:12px 14px;border-bottom:1px solid var(--border)}th{background:#1a304b;color:#d6e5f5;font-size:13px}tr:last-child td{border-bottom:0}.empty{padding:18px;color:var(--muted)}.footer{margin-top:20px;color:var(--muted);font-size:12px}@media(max-width:750px){.layout{grid-template-columns:1fr}.top{align-items:flex-start}main{padding-top:12px}}
</style>
</head>
<body><main>
<header class="top"><div><div class="brand">LumiFlowOn</div><div class="eyebrow">Member Portal / Financial Requests</div></div><a class="back" href="{{ url('/member/dashboard') }}">ΓåÉ Member Dashboard</a></header>
<h1>Deposits &amp; Transfers</h1>
<p class="lead">Submit deposit evidence for independent review and manage member-to-member transfers when authorized.</p>
<div class="notice"><strong>Safety:</strong> External USDT payments require independent admin verification. Do not send funds without confirming the official destination. A submitted request is not an approved deposit.</div>
@if(session('status'))<div class="notice success" role="status">{{ session('status') }}</div>@endif
@if($errors->any())<div class="notice errors" role="alert"><strong>Request could not be completed:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="layout">
<section class="panel" aria-labelledby="deposit-title"><h2 id="deposit-title">Submit deposit evidence</h2><p class="hint">Evidence submission is subject to the deposit safety switch and independent admin verification.</p>
<form method="post" action="{{ route('member.deposit-request.store') }}">@csrf
<label class="field"><span>Amount (USDT)</span><input type="number" inputmode="decimal" step="0.00000001" min="10" name="amount" value="{{ old('amount') }}" placeholder="Minimum 10 USDT" required></label>
<label class="field"><span>Network</span><select name="network" required><option value="TRC20" @selected(old('network')==='TRC20')>TRC20</option><option value="BEP20" @selected(old('network')==='BEP20')>BEP20</option></select></label>
<label class="field"><span>Blockchain transaction hash</span><input name="tx_hash" maxlength="150" value="{{ old('tx_hash') }}" placeholder="Verified transaction hash" autocomplete="off" required></label>
<input type="hidden" name="request_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}"><button type="submit">Submit evidence for review</button></form></section>
<section class="panel" aria-labelledby="transfer-title"><h2 id="transfer-title">Transfer available funds</h2><p class="hint">Transfers are only permitted when enabled by an administrator. No balance is estimated or invented here.</p>
<form method="post" action="{{ route('member.fund-transfer.store') }}">@csrf
<label class="field"><span>Recipient member ID</span><input type="number" name="recipient_id" min="1" step="1" value="{{ old('recipient_id') }}" placeholder="Member ID" required></label>
<label class="field"><span>Amount (USDT)</span><input type="number" inputmode="decimal" step="0.00000001" min="0.00000001" name="amount" value="{{ old('amount') }}" placeholder="Transfer amount" required></label>
<input type="hidden" name="request_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}"><button type="submit">Request transfer</button></form></section>
</div>
<section class="panel" aria-labelledby="history-title"><h2 id="history-title">My deposit requests</h2><p class="hint">Latest 30 requests, based on LumiFlowOn database records.</p>
@if($deposits->isEmpty())<div class="empty">No deposit requests yet.</div>@else<div class="table-wrap"><table><thead><tr><th>ID</th><th>Amount (USDT)</th><th>Network</th><th>Status</th></tr></thead><tbody>@foreach($deposits as $d)<tr><td>{{ $d->id }}</td><td>{{ $d->amount }}</td><td>{{ $d->network }}</td><td><span class="tag">{{ ucfirst($d->status) }}</span></td></tr>@endforeach</tbody></table></div>@endif
</section><p class="footer">LumiFlowOn Demo ┬╖ Financial transactions remain subject to backend controls and administrator approval.</p>
</main></body></html>
