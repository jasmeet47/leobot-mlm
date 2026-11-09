<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>LumiFlowOn — Member Dashboard</title>
<style>
:root{color-scheme:dark;--bg:#080e1a;--surface:#101b2e;--line:#24324a;--muted:#97a7bd;--text:#edf2fa;--gold:#e9c583;--green:#64dfb6}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font:14px/1.6 ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif}a{color:inherit;text-decoration:none}button,input,textarea,select{font:inherit}button,a,input,summary{outline-offset:4px}a:focus-visible,button:focus-visible,summary:focus-visible{outline:2px solid var(--gold)}.portal-sidebar{position:fixed;inset:0 auto 0 0;width:245px;background:#0c1525;border-right:1px solid var(--line);padding:25px 17px;overflow:auto}.portal-brand{display:flex;align-items:center;gap:10px;padding:0 9px 25px;font-weight:800;letter-spacing:.09em;color:var(--gold)}.portal-brand img{width:38px;height:38px;object-fit:contain}.sidebar-links{display:flex;flex-direction:column;gap:3px}.lb-menu-group-title{color:#778aa5;font-size:10px;text-transform:uppercase;letter-spacing:.15em;padding:21px 12px 6px}.sidebar-links .action{display:block;padding:9px 12px;border:0;border-radius:7px;background:none;font-size:12px;text-align:left;width:100%;color:#bdcadc;cursor:pointer}.sidebar-links .action:hover{background:#192942;color:white}.portal-main{margin-left:245px;padding:26px clamp(18px,3.5vw,54px) 70px;max-width:1650px}.portal-topbar{display:flex;align-items:center;justify-content:space-between;gap:15px;padding-bottom:24px;border-bottom:1px solid var(--line)}.portal-eyebrow,.lb-eyebrow{font-size:10px;letter-spacing:.18em;text-transform:uppercase;color:var(--gold)}.portal-user{display:flex;gap:11px;align-items:center}.portal-avatar{width:36px;height:36px;display:grid;place-items:center;background:#273953;border:1px solid #405575;border-radius:50%;font-weight:700;color:var(--gold)}.portal-user small{display:block;color:var(--muted);font-size:11px}.portal-welcome{padding:26px 0 5px}.portal-welcome h1{margin:0;font-size:clamp(25px,3vw,36px);letter-spacing:-.04em;font-weight:600}.portal-welcome p{color:#a7f3d0;margin:7px 0}.portal-welcome span{color:var(--gold)}section{margin:24px 0}h2{font-size:17px;font-weight:600;margin:0 0 15px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px}.card{border:1px solid var(--line);background:var(--surface);border-radius:12px;padding:21px;min-width:0}.label,.muted,.unit{color:var(--muted)}.label{font-size:12px}.value{font-size:clamp(18px,2.1vw,26px);font-weight:600;letter-spacing:-.035em;overflow-wrap:anywhere;margin:9px 0 3px}.unit{font-size:11px}.lb-quick-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px}.lb-quick-link{background:linear-gradient(140deg,#1a2a42,#111d30);border:1px solid #30425f;border-radius:11px;padding:17px;display:flex;flex-direction:column;gap:4px;min-width:0}.lb-quick-link strong{font-weight:600;font-size:15px}.lb-quick-link small{font-size:11px;color:var(--muted)}a.lb-quick-link:hover{border-color:var(--gold)}.action{display:inline-block;background:#1c2b42;border:1px solid #374c69;border-radius:7px;padding:9px 14px;color:var(--text);cursor:pointer}.action:hover{border-color:var(--gold)}.table-wrap{overflow:auto;border:1px solid var(--line);border-radius:12px}table{width:100%;border-collapse:collapse;background:var(--surface)}th,td{padding:13px 17px;text-align:left;white-space:nowrap;border-bottom:1px solid var(--line);font-size:12px}th{color:var(--muted);font-size:10px;text-transform:uppercase;letter-spacing:.08em}tr:last-child td{border:0}.success{color:var(--green)}[role="status"]{color:var(--green)}.lb-profile-strip{display:flex;justify-content:space-between;gap:15px;align-items:center;border:1px solid var(--line);padding:19px;border-radius:12px}.lb-profile-strip strong,.lb-profile-strip small{display:block}.lb-profile-strip small{color:var(--muted)}.lb-account-status{border:1px solid #36564c;background:#15352e;padding:5px 10px;border-radius:20px;color:var(--green);font-size:11px}.lb-bottom-grid{display:grid;grid-template-columns:1fr 1fr;gap:15px}.lb-small-tag{font-size:11px;color:var(--muted)}.mobile-menu{display:none}.lb-quick-links{border-color:var(--line)!important;background:var(--surface)}.portal-footer{margin-top:35px;font-size:11px;color:#7588a2;border-top:1px solid var(--line);padding-top:18px}svg{max-width:100%}
@media(min-width:1000px){#account-summary .card:first-child{background:linear-gradient(125deg,#263957,#17263b);border-color:#516483}}
@media(max-width:1000px){.portal-sidebar{width:210px}.portal-main{margin-left:210px}.portal-user>div:last-child{display:none}.grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:700px){.portal-sidebar{display:none;position:relative;width:100%;border-right:0;border-bottom:1px solid var(--line)}body.menu-open .portal-sidebar{display:block}.portal-main{margin-left:0;padding:17px 16px 40px}.mobile-menu{display:inline-flex}.portal-topbar{padding-bottom:17px}.portal-welcome{padding-top:22px}.card{padding:16px}.value{font-size:19px}.lb-bottom-grid{grid-template-columns:1fr}.lb-quick-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.portal-brand-mobile{display:flex;align-items:center;gap:8px;font-weight:700;font-size:12px;letter-spacing:.06em}.portal-brand-mobile img{width:30px;height:30px;object-fit:contain}.lb-profile-strip{align-items:flex-start}.portal-avatar{width:30px;height:30px}.portal-topbar{flex-wrap:wrap}.portal-user{margin-left:auto}}
@media(min-width:701px){.portal-brand-mobile{display:none}}
#account-summary>.card{margin-bottom:14px}#account-summary .grid .card{display:flex;flex-direction:column}#account-summary .grid .card .value{margin-top:auto;padding-top:9px}.referral-status{display:inline-block;padding:4px 10px;border-radius:20px;font-size:11px;border:1px solid #40516b}.referral-status[data-status="active"]{background:#123d30;color:#86efac;border-color:#306750}.referral-status[data-status="inactive"]{background:#263449;color:#cbd5e1}#team-breakdown :is(th,td):nth-child(n+2){text-align:right}
</style>
@php
    $lbPanel = request()->route('panel');
@endphp
@if($lbPanel)
<style>.portal-main section{display:none!important}.portal-main #{{ $lbPanel }}{display:block!important}@if(in_array($lbPanel, ['account-settings', 'member-support'], true)).portal-main section.lb-bottom-grid{display:grid!important}.portal-main section.lb-bottom-grid>div:not(#{{ $lbPanel }}){display:none!important}@endif</style>
@else
<style>#team-breakdown,#direct-referrals,#referral-privacy,#income-summary,#financial-records,#wallet-history,.lb-bottom-grid{display:none}</style>
@endif
<style>.portal-main :is(strong,b,h1,h2,h3,h4,.value){color:var(--gold)!important}.menu-close,.menu-overlay{display:none}@media(max-width:700px){body.menu-open{overflow:hidden}.portal-sidebar{display:block;position:fixed;inset:0 auto 0 0;width:min(290px,85vw);height:100dvh;overflow-y:auto;z-index:102;visibility:hidden;pointer-events:none;transform:translateX(-110%);transition:transform .2s ease}body.menu-open .portal-sidebar{display:block;transform:translateX(0);visibility:visible;pointer-events:auto}.menu-close{display:block;margin:0 0 16px auto;background:transparent;border:1px solid #40516b;color:var(--gold);padding:8px 12px;border-radius:8px}body.menu-open .menu-overlay{display:block;position:fixed;inset:0;width:100%;height:100%;z-index:101;background:#0009;border:0}body.menu-open #portal-menu-toggle{visibility:hidden}}</style></head><body>
@include('partials.beta-notice')
<button type="button" class="menu-overlay" id="portal-menu-overlay" aria-label="Close navigation"></button><aside class="portal-sidebar" id="portal-navigation" aria-label="Member navigation"><a class="portal-brand" href="{{ route('member.dashboard') }}"><img src="{{ route('lumiflowon.brand.logo') }}" alt="LumiFlowOn">LumiFlowOn</a>
        <button type="button" class="menu-close" id="portal-menu-close" aria-label="Close menu">Close ×</button><div class="sidebar-links" aria-label="Member navigation">
            <!-- LUMIFLOWON_MEMBER_MENU_FOUR_GROUPS_V1 -->
            <div class="lb-menu-group-title" role="heading" aria-level="3">Dashboard</div>
            <a class="action" href="{{ route('member.dashboard') }}">Dashboard Overview</a>

            <div class="lb-menu-group-title" role="heading" aria-level="3">My Team</div>
            <a class="action" href="{{ route('member.detail', ['panel' => 'team-breakdown']) }}">51-Level Team Breakdown</a>
            <a class="action" href="{{ route('member.team-levels') }}">View My 51-Level Team</a>
            @if(isset($magicTeam) && is_array($magicTeam) && ($magicTeam['visible_levels'] ?? 0) > 0)
            <a class="action" href="{{ route('member.detail', ['panel' => 'magic-team']) }}">Magic Team</a>
            @endif

            <a class="action" href="{{ route('member.detail', ['panel' => 'direct-referrals']) }}">My Direct Referrals</a>

            <a class="action" href="{{ route('member.detail', ['panel' => 'referral-privacy']) }}">Referral Contact Privacy</a>

            <div class="lb-menu-group-title" role="heading" aria-level="3">My Finance</div>
            @if(($canReviewTradingProfit ?? false) && !config('leobot.admin_profit_verification_enabled',false))<a class="action" href="{{ route('reviewer.trading-profit.index') }}">Independent Profit Review</a>@endif
            <a class="action" href="{{ route('member.detail', ['panel' => 'income-summary']) }}">Income Details</a>
            <a class="action" href="{{ route('member.detail', ['panel' => 'wallet-history']) }}">My Wallet Transaction History</a>
            <a class="action" href="{{ route('member.detail', ['panel' => 'financial-records']) }}">Financial Records</a>
            <a class="action" href="{{ route('member.withdrawals.index') }}">Withdrawals</a>
            <a class="action" href="{{ route('member.deposit-transfer.index') }}">Deposits & Transfers</a>

            <div class="lb-menu-group-title" role="heading" aria-level="3">My Account</div>
            <a class="action" href="{{ route('member.profile') }}">Profile</a>
            <a class="action" href="{{ route('member.detail', ['panel' => 'account-settings']) }}">Account / Security PIN</a>
            <a class="action" href="{{ route('member.detail', ['panel' => 'member-support']) }}">Support</a>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="action" type="submit">Logout</button></form>
        </div>
</aside><main class="portal-main">
<header class="portal-topbar"><button id="portal-menu-toggle" class="action mobile-menu" aria-controls="portal-navigation" aria-expanded="false" type="button">☰ Menu</button><a class="portal-brand-mobile" href="{{ route('member.dashboard') }}"><img src="{{ route('lumiflowon.brand.logo') }}" alt="LumiFlowOn">LumiFlowOn</a><div class="portal-eyebrow">Member / {{ $lbPanel ? ucwords(str_replace('-', ' ', $lbPanel)) : 'Overview' }}</div><div class="portal-user"><div class="portal-avatar">{{ mb_substr($member->name, 0, 1) }}</div><div>{{ $member->name }}<small>{{ $member->username }}</small></div></div></header>
<div class="portal-welcome"><h1>{{ $lbPanel ? ucwords(str_replace('-', ' ', $lbPanel)) : 'Your financial overview' }}<span>.</span></h1>@include('partials.member-qualification',['qualificationMember'=>$member])<p>Welcome back, {{ $member->name }}.</p></div>
<section id="account-summary" aria-label="Member financial summary">
        <h2>Your Account Summary</h2><div class="card" id="verified-deposit-total"><div class="label">Total Deposits</div><div class="value">{{ $verifiedDepositTotal }} USDT</div></div>
        <div class="grid">
            <div class="card"><div class="label">Available Balance</div><div class="value">{{ bcadd((string) ($member->available_balance ?? '0'), '0', 8) }}</div><div class="unit">USDT</div></div>
            <div class="card"><div class="label">Activation Balance</div><div class="value">{{ bcadd((string) ($member->activation_balance ?? '0'), '0', 8) }}</div><div class="unit">USDT</div></div>
            <div class="card"><div class="label">Total Investment</div><div class="value">{{ $currentInvestment }}</div><div class="unit">USDT</div></div>
            <div class="card"><div class="label">Lifetime Income</div><div class="value">{{ bcadd((string) ($member->lifetime_income ?? '0'), '0', 8) }}</div><div class="unit">USDT</div></div>
        </div>

    </section>

<!-- LUMIFLOWON_MT5_UI_FIXED -->
@php
$dashboardLinks = \Illuminate\Support\Facades\Schema::hasTable('dashboard_quick_links')
    ? \Illuminate\Support\Facades\DB::table('dashboard_quick_links')
        ->where('enabled', true)
        ->whereNotNull('url')
        ->get()
    : collect();
@endphp




@if(isset($magicIncomeSummary) && is_array($magicIncomeSummary))
<section class="lb-magic-summary" aria-label="Magic Income Summary" style="margin:18px 0;padding:18px;border:1px solid #2b435e;border-radius:14px;background:#101d30">
  <h2>Magic Income</h2>
  <div class="grid">
    <div class="card"><div class="label">Weekly</div><div class="value">{{ $magicIncomeSummary['weekly_usdt'] ?? '0.00000000' }} USDT</div></div>
    <div class="card"><div class="label">Monthly</div><div class="value">{{ $magicIncomeSummary['monthly_usdt'] ?? '0.00000000' }} USDT</div></div>
    <div class="card"><div class="label">Lifetime</div><div class="value">{{ $magicIncomeSummary['lifetime_usdt'] ?? '0.00000000' }} USDT</div></div>
  </div>

</section>
@endif
<!-- LUMIFLOWON_QUICK_ACTIONS_SAFE_V1 -->

<section class="lb-quick-actions" aria-label="Member quick actions">
  <h2>Quick Actions</h2>
  <div class="lb-quick-grid">
    <a class="lb-quick-link" href="{{ route('member.withdrawals.index') }}"><strong>Withdraw</strong></a>
    <a class="lb-quick-link" href="{{ route('member.deposit-transfer.index') }}"><strong>Deposit</strong></a>
    <a class="lb-quick-link" href="{{ route('member.staking.index') }}"><strong>Staking</strong></a>
    <a class="lb-quick-link" href="{{ route('member.deposit-transfer.index') }}"><strong>Fund Transfer</strong></a>
  </div>
</section>
<!-- /LUMIFLOWON_QUICK_ACTIONS_SAFE_V1 -->
@if(isset($magicTeam) && is_array($magicTeam) && ($magicTeam['visible_levels'] ?? 0) > 0)
<section id="magic-team" class="lb-quick-actions" aria-label="{{ (int) ($magicTeam['visible_levels'] ?? 20) }}-Level Magic Team Overview">
  <h2>Magic Team</h2>
  @if($magicEnrollmentState??null)<p class="muted">{{$magicEnrollmentState['reason']}}</p>@endif
  @if(isset($magicTeam) && is_array($magicTeam) && $magicTeam['available'])
    <div class="lb-quick-grid">
      <div class="lb-quick-link"><strong>{{ $magicTeam['total'] }}</strong><small>Total unique Magic Team Members</small></div>
      <div class="lb-quick-link"><strong>{{ $magicTeam['direct'] }}</strong><small>Direct Magic Members</small></div>
      <div class="lb-quick-link"><strong>{{ $magicTeam['filled_levels'] }} / {{ $magicTeam['visible_levels'] }}</strong><small>Filled Levels</small></div>
      <div class="lb-quick-link"><strong>{{ $magicTeam['has_position'] ? 'Placed' : 'Not placed' }}</strong><small>My Matrix Position</small></div>
    </div>
    <details style="margin-top:14px"><summary>View {{ (int) ($magicTeam['visible_levels'] ?? 20) }}-Level Magic Team Breakdown</summary>
      <div class="lb-quick-grid" style="margin-top:12px">
        @foreach($magicTeam['levels'] as $level => $count)
          <div class="lb-quick-link"><strong>Level {{ $level }}</strong><small>{{ $count }} member(s)</small></div>
        @endforeach
      </div>
    </details>
  @else
    <p>Magic Matrix data unavailable; no team count is being assumed.</p>
  @endif
</section>
@endif


    @if (session('success'))
        <p class="success" role="status">{{ session('success') }}</p>
    @endif
    <section id="income-summary" aria-label="Member income summary">
        <h2>My Income Summary</h2>


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

    <section id="financial-records" class="lb-records-overview" aria-label="Financial records">
        <div class="lb-section-topline"><span class="lb-eyebrow">LEDGER / VERIFIED RECORDS</span><span class="lb-small-tag">Recorded values only</span></div>
        <h2>Financial Records</h2>


    </section>

    @if($lbPanel==='wallet-history')
<section id="wallet-history" aria-label="Member wallet transaction history">
        <h2>My Wallet Transaction History</h2>


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
@endif
    <section id="referral-link" aria-label="Member referral link">
        <h2>Your Referral Link</h2>
        <div class="card">
            <label class="label" for="member-referral-link">Share your personal registration link</label>
            <div style="display:flex; flex-wrap:wrap; gap:10px; margin-top:10px; align-items:center">
                <input id="member-referral-link" type="text" readonly
                       value="{{ rtrim(config('app.url'), '/') . '/join?ref=' . rawurlencode($member->username) }}"
                       style="flex:1 1 280px; width:100%; min-width:0; padding:10px; border:1px solid #334155; border-radius:9px; background:#090d16; color:#e2e8f0; font:inherit"
                       aria-label="Your referral URL">
                <button id="copy-referral-button" type="button" class="action">Copy Referral Link</button>
            </div>
            <p id="referral-copy-status" class="muted" role="status" aria-live="polite" style="margin:10px 0 0"></p>
        </div>
    </section>
    <section id="team-overview" aria-label="Team summary">
        <h2>Your Team Overview</h2>
        <div class="grid">
            <div class="card"><div class="label">Active Team</div><div class="value">{{ (int) ($teamSummary->total_active_team ?? 0) }}</div></div>
            <div class="card"><div class="label">Inactive Team</div><div class="value">{{ (int) ($teamSummary->total_inactive_team ?? 0) }}</div></div>
            <div class="card"><div class="label">Total Team Business</div><div class="value">{{ bcadd((string) ($teamSummary->total_team_business ?? '0'), '0', 8) }}</div><div class="unit">USDT</div></div>
        </div>
    </section>

    <section id="referral-privacy" aria-label="Referral Contact Privacy">
        <h2>Referral Contact Privacy</h2>
        <div class="card">


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
    <section id="direct-referrals" aria-label="My Direct Referrals">
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
                            <td><span class="referral-status" data-status="{{$referral->status}}">{{ ucfirst($referral->status) }}</span></td>
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
    <section id="team-breakdown" aria-label="51-level team breakdown">
        <h2>51-Level Team Breakdown</h2>
        <form method="GET" action="{{ route('member.detail', ['panel'=>'team-breakdown']) }}"><label>Member IDs &amp; Names — Level <select name="breakdown_level">@for($i=1;$i<=51;$i++)<option value="{{$i}}" @selected($breakdownLevel===$i)>{{$i}}</option>@endfor</select></label><button class="action">View Members</button></form>
        @if($breakdownMembers)
        <div class="table-wrap" style="margin:14px 0"><table><thead><tr><th>Member ID</th><th>Member Name</th><th>Status</th><th>Team View</th></tr></thead><tbody>
        @forelse($breakdownMembers as $person)<tr><td>{{$person->username}}</td><td>{{$person->name}}</td><td><span class="referral-status" data-status="{{$person->status}}">{{$person->status==='active'?'Active':'Inactive'}}</span></td><td><a href="{{route('member.team-levels',['team_member'=>$person->id])}}">View Team</a></td></tr>
        @empty<tr><td colspan="4">No members found on this level.</td></tr>@endforelse
        </tbody></table></div>{{$breakdownMembers->links()}}
        @endif
        <div class="table-wrap">
            <table>
                <thead><tr><th scope="col">Level</th><th scope="col">Active</th><th scope="col">Inactive</th><th scope="col">Team Business (USDT)</th><th scope="col">Level Income Paid (USDT)</th></tr></thead>
                <tbody>
                @for ($level = 1; $level <= 51; $level++)
                    @php
    $entry = $levelRows->get($level);
@endphp
                    <tr>
                        <td>Level {{ $level }}</td>
                        <td>{{ (int) ($entry->active_count ?? 0) }}</td>
                        <td>{{ (int) ($entry->inactive_count ?? 0) }}</td>
                        <td>{{ bcadd((string) ($entry->total_business ?? '0'), '0', 8) }}</td><td>{{ bcadd((string) ($levelIncome->get($level) ?? '0'), '0', 8) }}</td>
                    </tr>
                @endfor
                </tbody>
            </table>
        </div>
    </section>
    <section class="lb-bottom-grid" aria-label="Account and support">
        <div id="account-settings" class="card lb-info-card">
            <span class="lb-eyebrow">SECURITY CENTER</span>
            <h2>Account</h2>
            <p class="muted">Manage your security PIN and view your account status. Existing login and account controls remain unchanged.</p>
            <a class="action lb-inline-link" href="{{ route('security-pin.form') }}">Security PIN Settings →</a>
        </div>
        <div id="member-support" class="card lb-info-card">
            <span class="lb-eyebrow">MEMBER ASSISTANCE</span>
            <h2>Support</h2>
            <form method="post" action="{{ route('member.support.store') }}">@csrf<label for="support-message">Your message</label><textarea id="support-message" name="message" rows="5" minlength="5" maxlength="4000" required style="display:block;width:100%;padding:12px;background:#101b2e;color:white;border:1px solid #40516b;border-radius:8px"></textarea><button class="action" style="margin-top:12px">Send message</button></form>@if(session('support_status'))<p role="status">{{session('support_status')}}</p>@endif @error('message')<p role="alert">{{$message}}</p>@enderror
        </div>
    </section>


<!-- MT5 display intentionally last on Member Dashboard; read-only and admin-controlled -->
@php
    $mt5Visible = \Illuminate\Support\Facades\Schema::hasTable('mt5_display_settings')
            && (bool) \Illuminate\Support\Facades\DB::table('mt5_display_settings')->where('id', 1)->value('enabled');
@endphp
@if(\Illuminate\Support\Facades\Schema::hasTable('mt5_display_settings') && (bool) \Illuminate\Support\Facades\DB::table('mt5_display_settings')->where('id', 1)->value('enabled'))
<section style="margin:20px 0;padding:20px;border:1px solid #64748b;border-radius:12px">
    <h2>MT5 Live Trading</h2>
<p>MT5 Live Connection: Awaiting verified connection</p>
        <p>Balance, Equity, Profit/Loss and Trades will appear only after a verified read-only feed is connected.</p>
        <p><a href="{{ route('member.mt5.view') }}">View MT5 Status</a></p>
</section>
@endif
<section class="lb-quick-links" style="margin:20px 0;padding:20px;border:1px solid #64748b;border-radius:12px">
<h2>LumiFlowOn Quick Links</h2>

<div style="display:flex;gap:12px;flex-wrap:wrap">
@foreach(['whatsapp','youtube','arattai','telegram','pdf'] as $platform)
<span class="lb-quick-link" style="min-width:150px"><span aria-hidden="true" style="font-size:28px;color:var(--gold)">@include('partials.social-icon', ['platform'=>$platform])</span><strong>{{ $platform==='pdf'?'PDF':ucfirst($platform) }}</strong><small>Coming soon</small></span>
@endforeach
</div>
</section>
<footer class="portal-footer">LumiFlowOn</footer></main>
<script>
const toggle = document.getElementById('portal-menu-toggle');
toggle.addEventListener('click', () => { const open = document.body.classList.toggle('menu-open'); toggle.setAttribute('aria-expanded', String(open)); if(open)document.getElementById('portal-menu-close').focus(); });
const closeMenu=()=>{document.body.classList.remove('menu-open');toggle.setAttribute('aria-expanded','false');toggle.focus();};document.getElementById('portal-menu-close').addEventListener('click',closeMenu);document.getElementById('portal-menu-overlay').addEventListener('click',closeMenu);document.addEventListener('keydown',event=>{if(event.key==='Escape'&&document.body.classList.contains('menu-open'))closeMenu();});
const copyButton = document.getElementById('copy-referral-button');
if (copyButton) copyButton.addEventListener('click', async () => {
    const input = document.getElementById('member-referral-link');
    const status = document.getElementById('referral-copy-status');
    try { await navigator.clipboard.writeText(input.value); status.textContent = 'Your referral link copied successfully!'; }
    catch (error) { input.focus(); input.select(); status.textContent = 'Link selected. Press Ctrl+C to copy.'; }
});
</script></body></html>
