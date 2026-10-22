<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Magic Income Details | LumiFlowOn</title>
<style>body{font-family:system-ui,sans-serif;background:#101d30;color:#f2f6ff;margin:0;padding:24px}main{max-width:1100px;margin:auto}.cards{display:flex;flex-wrap:wrap;gap:12px}.card{background:#1b2c44;padding:18px;border-radius:12px;flex:1;min-width:180px}table{border-collapse:collapse;width:100%;margin-top:20px}td,th{padding:12px;border-bottom:1px solid #445;text-align:left}a{color:#9dc9ff}small{color:#b9c9d9}</style>
</head><body><main>
<h1>Magic Income Details</h1>
<p><a href="{{ route('member.dashboard') }}">Back to Dashboard</a></p>
<div class="cards">
<div class="card">Weekly<br><strong>{{ $summary['weekly_usdt'] }} USDT</strong></div>
<div class="card">Monthly<br><strong>{{ $summary['monthly_usdt'] }} USDT</strong></div>
<div class="card">Lifetime<br><strong>{{ $summary['lifetime_usdt'] }} USDT</strong></div>
</div>
<h2>Magic Entitlement History</h2>
<small>Amounts and statuses are read directly from your entitlement records. Pending and held amounts are not included in the totals.</small>
<table><thead><tr><th>Record</th><th>Level</th><th>Paid USDT</th><th>Held USDT</th><th>Status</th><th>Created</th></tr></thead><tbody>
@forelse($history as $entry)
<tr><td>{{ $entry->id }}</td><td>{{ $entry->level ?? '—' }}</td><td>{{ $entry->paid_amount ?? '0' }}</td><td>{{ $entry->held_amount ?? '0' }}</td><td>{{ $entry->status ?? '—' }}</td><td>{{ $entry->created_at ?? '—' }}</td></tr>
@empty
<tr><td colspan="6">No Magic income records found.</td></tr>
@endforelse
</tbody></table>
{{ $history->links() }}
</main></body></html>
