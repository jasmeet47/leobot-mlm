<nav class="lumi-finance-nav" aria-label="LumiFlowOn navigation">
<a href="{{ url('/') }}"><img src="{{ route('lumiflowon.brand.logo') }}" alt="LumiFlowOn"></a>
@if(in_array(auth()->user()?->role, ['admin','super_admin'], true))
<a href="{{ route('admin.dashboard') }}">Dashboard</a><a href="{{ route('admin.staking.index') }}">Staking Control</a><a href="{{ route('admin.staking.profit.index') }}">Trading Profit</a><a href="{{ route('admin.withdrawals.index') }}">Withdrawals</a><a href="{{ route('admin.deposit-review.index') }}">Deposits</a>
@else
<a href="{{ route('member.dashboard') }}">Dashboard</a><a href="{{ route('member.staking.index') }}">Staking</a><a href="{{ route('member.deposit-transfer.index') }}">Deposit USDT</a><a href="{{ route('member.withdrawals.index') }}">Withdrawals</a><a href="{{ route('member.profile') }}">Profile</a>
@endif
</nav>
