<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Member Dashboard | LeoBot</title>
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #090d16; color: #e2e8f0; font: 15px/1.55 Arial, sans-serif; }
        .container { width: min(1160px, 94%); margin: 0 auto; padding: 28px 0 55px; }
        header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 25px; }
        .brand { font-size: 25px; font-weight: 800; color: #38bdf8; letter-spacing: .05em; }
        .brand small { font-size: 13px; display: block; font-weight: 400; color: #94a3b8; letter-spacing: 0; }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        .action { border-radius: 9px; border: 1px solid #334155; background: #192335; color: #e2e8f0; padding: 10px 15px; text-decoration: none; font: inherit; cursor: pointer; }
        .action:hover, .action:focus-visible { border-color: #38bdf8; }
        h1 { font-size: clamp(25px, 4vw, 34px); margin: 0 0 5px; }
        .subheading, .muted { color: #94a3b8; }
        .subheading { margin: 0 0 24px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 15px; }
        .card { border: 1px solid #263244; background: #111827; border-radius: 14px; padding: 19px; min-width: 0; }
        .label { color: #94a3b8; font-size: 13px; }
        .value { font-size: clamp(19px, 2.6vw, 25px); font-weight: 700; margin-top: 8px; overflow-wrap: anywhere; }
        .unit { font-size: 13px; color: #94a3b8; }
        section { margin-top: 27px; }
        h2 { font-size: 20px; margin: 0 0 12px; }
        .table-wrap { overflow-x: auto; border: 1px solid #263244; border-radius: 14px; }
        table { width: 100%; border-collapse: collapse; background: #111827; }
        th, td { padding: 12px 16px; border-bottom: 1px solid #263244; text-align: left; white-space: nowrap; }
        th { background: #172235; color: #cbd5e1; font-size: 13px; }
        tr:last-child td { border-bottom: 0; }
        .notice { margin-top: 25px; border: 1px solid #334155; background: #111827; padding: 15px; border-radius: 10px; color: #94a3b8; font-size: 13px; }
        .success { color: #86efac; }
    </style>
<!-- LEOBOT_PREMIUM_UI_V1: presentation only; no market feed or invented prices -->
<style>
:root{--lb-bg:#070d19;--lb-panel:#101d30;--lb-panel2:#15243a;--lb-border:#293a53;--lb-text:#e8eff9;--lb-muted:#9caec4;--lb-green:#21c997;--lb-blue:#50a7ff}
body{background:radial-gradient(ellipse at 90% 0%,#153153 0%,#080f1e 43%,#060c16 100%) fixed!important;color:var(--lb-text)!important}
.container,main.container{max-width:1440px!important}
.lb-terminal{font-family:Inter,ui-sans-serif,system-ui,-apple-system,'Segoe UI',sans-serif;background:linear-gradient(125deg,#13233b,#0b1627 68%,#0b1e2d);border:1px solid #30445e;border-radius:22px;padding:clamp(18px,3vw,32px);margin:22px 0 28px;box-shadow:0 24px 65px #02081477;position:relative;overflow:hidden;color:#eef6ff}
.lb-terminal:before{content:'';position:absolute;width:260px;height:260px;right:-100px;top:-140px;background:#1875c82a;filter:blur(35px);border-radius:50%;pointer-events:none}
.lb-terminal *{box-sizing:border-box}.lb-terminal-head{display:flex;justify-content:space-between;align-items:center;gap:18px;flex-wrap:wrap}.lb-terminal-eyebrow{font-size:11px;letter-spacing:.21em;text-transform:uppercase;color:#6dbbff;font-weight:800}.lb-terminal h2{font-size:clamp(22px,2.5vw,32px);line-height:1.2;color:#f5faff;margin:9px 0}.lb-terminal p{color:#a8bbd2;margin:8px 0 0;line-height:1.6}.lb-terminal-status{display:inline-flex;align-items:center;gap:9px;border:1px solid #36516c;border-radius:999px;padding:9px 13px;font-size:12px;color:#d4e5f8;background:#142840;white-space:nowrap}.lb-terminal-dot{width:8px;height:8px;border-radius:50%;background:#21c997;box-shadow:0 0 0 4px #21c99725}.lb-terminal-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:24px}.lb-terminal-tile{border:1px solid #2a4059;background:#0a1729b8;border-radius:14px;padding:16px;min-height:105px}.lb-terminal-tile small{display:block;color:#9bb2ca;font-size:12px;margin-bottom:12px}.lb-terminal-tile strong{font-size:clamp(15px,1.6vw,22px);color:#f5faff}.lb-terminal-tile .accent{color:#40ddb0}.lb-terminal-foot{margin-top:16px!important;font-size:12px}.lb-terminal-note{color:#7fa1c3!important}.lb-terminal a{color:#87c8ff!important}
main section,main .card,main .stat-card,main .panel,main .notice{border-radius:14px}main table{width:100%}main th{letter-spacing:.025em}main a,main button{transition:filter .18s ease,transform .18s ease}main button:hover{filter:brightness(1.1)}
@media(max-width:720px){.lb-terminal-grid{grid-template-columns:1fr}.lb-terminal{padding:18px;border-radius:16px}.lb-terminal-tile{min-height:auto}.lb-terminal-status{white-space:normal}}
</style>
</head>
<body>
<main class="container">
<section class="lb-terminal" aria-label="LeoBot member financial dashboard">
  <div class="lb-terminal-head">
    <div><div class="lb-terminal-eyebrow">LEOBOT / MEMBER PORTAL</div><h2>Portfolio Overview</h2><p>Welcome, {{ $member->name }}. Your LeoBot account information is shown below.</p></div>
    <div class="lb-terminal-status"><span class="lb-terminal-dot"></span> Secure member session</div>
  </div>
  <div class="lb-terminal-grid">
    <div class="lb-terminal-tile"><small>Account</small><strong>Member Portal</strong></div>
    <div class="lb-terminal-tile"><small>Financial records</small><strong class="accent">Verified records only</strong></div>
    <div class="lb-terminal-tile"><small>Trading feed</small><strong>Not connected</strong></div>
  </div>
  <p class="lb-terminal-foot lb-terminal-note">International trading-inspired interface only. Income, wallet balances and history below are from LeoBot records, not MT5.</p>
</section>
    <header>
        <div class="brand">LEOBOT <small>Member Dashboard</small></div>
        <nav class="actions" aria-label="Member actions">
            <a class="action" href="{{ route('security-pin.form') }}">Change Security PIN</a>
            <form method="POST" action="{{ route('logout') }}" style="margin:0">
                @csrf
                <button class="action" type="submit">Logout</button>
            </form>
        </nav>
    </header>

    <h1>Welcome, {{ $member->name }}</h1>
    <p class="subheading">Member ID: {{ $member->username }} &middot; Account status: {{ ucfirst($member->status) }}</p>

    @if (session('success'))
        <p class="success" role="status">{{ session('success') }}</p>
    @endif<section aria-label="Member financial summary">
        <h2>Your Account Summary</h2>
        <div class="grid">
            <div class="card"><div class="label">Available Balance</div><div class="value">{{ bcadd((string) ($member->available_balance ?? '0'), '0', 8) }}</div><div class="unit">USDT</div></div>
            <div class="card"><div class="label">Activation Balance</div><div class="value">{{ bcadd((string) ($member->activation_balance ?? '0'), '0', 8) }}</div><div class="unit">USDT</div></div>
            <div class="card"><div class="label">Total Investment</div><div class="value">{{ bcadd((string) ($member->total_investment ?? '0'), '0', 8) }}</div><div class="unit">USDT</div></div>
            <div class="card"><div class="label">Lifetime Income</div><div class="value">{{ bcadd((string) ($member->lifetime_income ?? '0'), '0', 8) }}</div><div class="unit">USDT</div></div>
        </div>
    </section>

    <section aria-label="Member income summary">
        <h2>My Income Summary</h2>
        <p class="muted">
            Reserved and held amounts are not paid income and are not
            included in your available wallet balance.
        </p>

        <div class="grid">
            <div class="card">
                <div class="label">Self Income Reserved</div>
                <div class="value">{{ bcadd($incomeSummary['self_reserved'], '0', 8) }}</div>
                <div class="unit">USDT - Reserved, not paid</div>
            </div>

            <div class="card">
                <div class="label">Generation Qualified Reserve</div>
                <div class="value">{{ bcadd($incomeSummary['generation_qualified'], '0', 8) }}</div>
                <div class="unit">USDT - Qualified, not paid</div>
            </div>

            <div class="card">
                <div class="label">Generation Held</div>
                <div class="value">{{ bcadd($incomeSummary['generation_held'], '0', 8) }}</div>
                <div class="unit">USDT - Unqualified, not payable</div>
            </div>

            <div class="card">
                <div class="label">Magic Income</div>
                <div class="value">Not allocated</div>
                <div class="unit">Company pool is not member income</div>
            </div>
        </div>
    </section>

    <section aria-label="Member wallet transaction history">
        <h2>My Wallet Transaction History</h2>
        <p class="muted">
            Only completed transactions recorded against your member
            wallet are listed. Reserve movements are excluded.
        </p>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Entry Type</th>
                        <th>Wallet</th>
                        <th>Amount (USDT)</th>
                        <th>Balance After (USDT)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($walletHistory as $transaction)
                        <tr>
                            <td>{{ $transaction->created_at ? \Illuminate\Support\Carbon::parse($transaction->created_at)->format('d M Y H:i') : '-' }}</td>
                            <td>{{ $transaction->entry_type }}</td>
                            <td>{{ $transaction->wallet_type }}</td>
                            <td>{{ bcadd((string) $transaction->amount, '0', 8) }}</td>
                            <td>{{ bcadd((string) $transaction->balance_after, '0', 8) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">No completed wallet transactions recorded.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:15px">
            {{ $walletHistory->links() }}
        </div>
    </section>
    <section aria-label="Member referral link">
        <h2>Your Referral Link</h2>
        <div class="card">
            <label class="label" for="member-referral-link">Share your personal registration link</label>
            <div style="display:flex; flex-wrap:wrap; gap:10px; margin-top:10px; align-items:center">
                <input id="member-referral-link" type="text" readonly
                       value="{{ url('/join?ref=' . $member->username) }}"
                       style="flex:1 1 280px; width:100%; min-width:0; padding:10px; border:1px solid #334155; border-radius:9px; background:#090d16; color:#e2e8f0; font:inherit"
                       aria-label="Your referral URL">
                <button id="copy-referral-button" type="button" class="action">Copy Referral Link</button>
            </div>
            <p id="referral-copy-status" class="muted" role="status" aria-live="polite" style="margin:10px 0 0"></p>
        </div>
    </section>
    <section aria-label="Team summary">
        <h2>Your Team Overview</h2>
        <div class="grid">
            <div class="card"><div class="label">Active Team</div><div class="value">{{ (int) ($teamSummary->total_active_team ?? 0) }}</div></div>
            <div class="card"><div class="label">Inactive Team</div><div class="value">{{ (int) ($teamSummary->total_inactive_team ?? 0) }}</div></div>
            <div class="card"><div class="label">Total Team Business</div><div class="value">{{ bcadd((string) ($teamSummary->total_team_business ?? '0'), '0', 8) }}</div><div class="unit">USDT</div></div>
        </div>
    </section>

    <section aria-label="Referral Contact Privacy">
        <h2>Referral Contact Privacy</h2>
        <div class="card">
            <p class="muted">
                Choose whether your direct sponsor may see your full
                phone number or email address. Full details are shared
                only when you give permission AND the admin enables
                full visibility. You can withdraw permission anytime. Your direct sponsor can see masked or full contact details only with your consent and when the admin enables the corresponding
            </p>

            <form method="POST"
                  action="{{ route('member.referral-contact-consent.update') }}">
                @csrf

                <input type="hidden" name="phone_consent" value="0">
                <input type="hidden" name="email_consent" value="0">

                <label style="display:block; margin:14px 0">
                    <input type="checkbox"
                           name="phone_consent"
                           value="1"
                           @checked(old('phone_consent', $phoneConsent))>
                    Allow my direct sponsor to see my full phone number
                </label>

                <label style="display:block; margin:14px 0">
                    <input type="checkbox"
                           name="email_consent"
                           value="1"
                           @checked(old('email_consent', $emailConsent))>
                    Allow my direct sponsor to see my full email address
                </label>

                @error('phone_consent')
                    <p role="alert" style="color:#fca5a5">{{ $message }}</p>
                @enderror

                @error('email_consent')
                    <p role="alert" style="color:#fca5a5">{{ $message }}</p>
                @enderror

                <button class="action" type="submit">
                    Save Privacy Choices
                </button>
            </form>
        </div>
    </section>
    <section aria-label="My Direct Referrals">
        <h2>My Direct Referrals (Level 1)</h2>
        <p class="muted">Only members directly referred by you.</p>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Member ID</th>
                        <th>Name</th>
                        <th>Account Status</th>
                        <th>Joining Date</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Investment</th>
                        <th>Package Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($referrals as $referral)
                        <tr>
                            <td>{{ $referral->username }}</td>
                            <td>{{ $referral->name }}</td>
                            <td>{{ ucfirst($referral->status) }}</td>
                            <td>{{ $referral->created_at ? \Illuminate\Support\Carbon::parse($referral->created_at)->format('d M Y') : '-' }}</td>
                            <td>{{ $referral->display_phone }}</td>
                            <td>{{ $referral->display_email }}</td>
                            <td>{{ $referral->display_investment }}</td>
                            <td>{{ $referral->display_package_status }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">No direct referrals found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:15px">
            {{ $referrals->links() }}
        </div>
    </section>
    <section aria-label="51-level team breakdown">
        <h2>51-Level Team Breakdown</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th scope="col">Level</th><th scope="col">Active</th><th scope="col">Inactive</th><th scope="col">Team Business (USDT)</th></tr></thead>
                <tbody>
                @for ($level = 1; $level <= 51; $level++)
                    @php($entry = $levelRows->get($level))
                    <tr>
                        <td>Level {{ $level }}</td>
                        <td>{{ (int) ($entry->active_count ?? 0) }}</td>
                        <td>{{ (int) ($entry->inactive_count ?? 0) }}</td>
                        <td>{{ bcadd((string) ($entry->total_business ?? '0'), '0', 8) }}</td>
                    </tr>
                @endfor
                </tbody>
            </table>
        </div>
    </section>
    <p class="notice">Read-only dashboard. Values reflect currently recorded data; no transfer, activation, trading funding or income payout is performed by this page. Security PIN setup is an access step, not financial transaction authorization.</p>
</main>
<script>
(function () {
    const button = document.getElementById('copy-referral-button');
    const input = document.getElementById('member-referral-link');
    const status = document.getElementById('referral-copy-status');
    if (!button || !input || !status) return;
    button.addEventListener('click', async function () {
        try {
            if (!navigator.clipboard || !navigator.clipboard.writeText) {
                throw new Error('Clipboard API unavailable');
            }
            await navigator.clipboard.writeText(input.value);
            status.textContent = 'Referral link copied.';
        } catch (error) {
            input.focus();
            input.select();
            status.textContent = 'Link selected. Press Ctrl+C to copy.';
        }
    });
})();
</script>
</body>
</html>
