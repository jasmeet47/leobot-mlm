<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>LumiFlowOn — Deposit Control</title>@include('partials.lumiflowon-financial-theme')@include('partials.deposit-qr-style')</head><body style="font:16px system-ui;max-width:1100px;margin:auto;padding:24px">@include('partials.lumiflowon-financial-nav')<h1>Deposit Control</h1>
<p>Save the official USDT receiving address and matching QR separately for each network.</p>


@unless($ready)<p>Receiving address settings are unavailable until setup completes.</p>@endunless
@foreach($destinations as $network => $destination)<section style="padding:20px;margin:20px 0"><h2>{{ $network }} USDT</h2>
@if($destination['has_qr'])<div class="deposit-qr"><div class="deposit-qr-frame"><img src="{{ route('admin.deposit-control.qr', $destination['id']) }}" alt="{{ $network }} receiving QR"></div></div>@endif
<form method="post" enctype="multipart/form-data" action="{{ route('admin.deposit-control.update', $network) }}">@csrf
<input type="hidden" name="expected_revision" value="{{ $destination['id'] ?? 0 }}">
<label style="display:block">Receiving address <input style="width:100%" name="address" value="{{ $destination['address'] }}" required maxlength="100" spellcheck="false" autocomplete="off"></label>
<label>Upload matching QR (PNG/JPG, up to 2 MB, 1024 × 1024 pixels)<input type="file" name="qr" accept="image/png,image/jpeg"></label>
<p>The QR must encode this address. Changing the address clears the previous QR unless a matching replacement is uploaded. Pending deposit requests retain their original receiving address.</p><button @disabled(!$ready)>Save {{ $network }}</button></form></section>@endforeach
<h2>Address revision history</h2><div class="lumi-table-scroll"><table><thead><tr><th>Revision</th><th>Network</th><th>Address</th><th>Admin</th><th>Saved</th></tr></thead><tbody>@foreach($history as $revision)<tr><td>{{ $revision->id }}</td><td>{{ $revision->network }}</td><td>{{ $revision->address }}</td><td>{{ $revision->changed_by }}</td><td>{{ $revision->created_at }}</td></tr>@endforeach</tbody></table></div></body></html>
