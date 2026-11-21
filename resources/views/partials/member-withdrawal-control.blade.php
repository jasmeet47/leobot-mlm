@php($withdrawalsOn = $controlEnabled ?? \App\Services\MemberWithdrawalControl::enabled((int) $controlMember->id))
<div class="member-withdrawal-controls">
    <span>Withdrawal: <strong>{{ $withdrawalsOn ? 'ON' : 'OFF' }}</strong></span>
    @foreach([1 => 'ON', 0 => 'OFF'] as $nextState => $label)
    <form method="post" action="{{ route('admin.member-access.withdrawals') }}" style="display:inline-block;margin:0 4px">
        @csrf
        <input type="hidden" name="member_id" value="{{ $controlMember->id }}">
        <input type="hidden" name="enabled" value="{{ $nextState }}">
        <input type="hidden" name="expected_enabled" value="{{ $withdrawalsOn ? 1 : 0 }}">
        <button type="submit" @disabled($withdrawalsOn === (bool) $nextState) style="width:auto;padding:8px 14px">{{ $label }}</button>
    </form>
    @endforeach
</div>
