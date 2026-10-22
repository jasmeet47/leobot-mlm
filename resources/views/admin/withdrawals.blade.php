<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>LumiFlowOn ΓÇö Withdrawal Review</title></head>
<body style="font:15px system-ui,sans-serif;max-width:940px;margin:auto;padding:2rem"><h1>Withdrawal Review</h1><p><a href="{{route('admin.dashboard')}}">ΓåÉ Admin</a></p>
<p><strong>External USDT payment is NOT executed by this panel.</strong> Approval only sets status <code>approved_awaiting_payment</code>.</p>
@if(session('status'))<p>{{session('status')}}</p>@endif
@foreach($errors->all() as $error)<p style="color:#a11">{{$error}}</p>@endforeach
<h2>Policy</h2><p>Principal withdrawal fee: {{$settings?->principal_withdrawal_fee_percent}}% (default 15%). Principal withdrawals remain disabled pending verified principal liability.</p>
<form method="post" action="{{route('admin.withdrawals.fee')}}">@csrf
<label>New principal fee (%) <input name="principal_fee" value="{{$settings?->principal_withdrawal_fee_percent}}" required></label>
<button>Update audited policy (separate approval required)</button></form>
<h2>Requests</h2><table style="width:100%;border-collapse:collapse"><tr><th>ID</th><th>Member</th><th>Gross</th><th>Status</th><th>Review</th></tr>
@foreach($withdrawals as $w)<tr><td>{{$w->id}}</td><td>{{$w->user_id}}</td><td>{{$w->gross_amount}} USDT</td><td>{{$w->status}}</td><td>
@if(in_array($w->status,['requested','approved_awaiting_payment'],true))
@if($w->status==='requested')<form method="post" action="{{route('admin.withdrawals.approve',$w->id)}}" style="display:inline">@csrf<button @disabled(!$writeEnabled)>Approve</button></form>@endif
<form method="post" action="{{route('admin.withdrawals.reject',$w->id)}}" style="display:inline">@csrf<button @disabled(!$writeEnabled)>Reject/refund</button></form>
@endif</td></tr>@endforeach</table>{{$withdrawals->links()}}</body></html>