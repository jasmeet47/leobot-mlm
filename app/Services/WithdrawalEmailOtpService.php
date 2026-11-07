<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class WithdrawalEmailOtpService
{
    public static function context(User $member, array $data): string
    {
        if (! preg_match('/\A\d+(?:\.\d{1,8})?\z/D', (string) $data['amount']) || bccomp((string) $data['amount'], '0', 8) <= 0) {
            throw new RuntimeException('Enter a positive withdrawal amount.');
        }
        $wallet = UsdtVerifiedWallet::forWithdrawal($member->id, (int) $data['usdt_wallet_id']);
        $position = (int) ($data['position_id'] ?? 0);
        if ($position && ! DB::table('staking_positions')->where('id', $position)->where('user_id', $member->id)->where('status', 'active')->exists()) {
            throw new RuntimeException('Member-owned active stake required.');
        }

        return hash('sha256', json_encode([$member->id, strtoupper($data['request_key']), bcadd($data['amount'], '0', 8), $wallet->id, $wallet->network, $wallet->address, $position, $data['expected_fee_percent'] ?? null], JSON_THROW_ON_ERROR));
    }

    private static function member(User $member, bool $principal = false): object
    {
        $row = DB::table('users')->where('id', $member->id)->lockForUpdate()->first();
        if (! $row || $row->role !== 'member' || ! in_array($row->status, $principal ? ['active', 'inactive'] : ['active'], true) || $row->is_blocked || $row->deleted_at || ! filter_var($row->email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Active member with a valid account email required.');
        }

        return $row;
    }

    public static function issue(User $member, array $data): int
    {
        if (! config('leobot.withdrawals_enabled', false) || ! config('leobot.production_financial_approval', false)) {
            throw new RuntimeException('Withdrawals are disabled.');
        }
        $code = (string) random_int(100000, 999999);
        $email = '';
        $destination = '';
        $id = DB::transaction(function () use ($member, $data, $code, &$email, &$destination): int {
            $settings = DB::table('withdrawal_settings')->where('id', 1)->first();
            if (! $settings || ! $settings->member_requests_enabled || (! empty($data['position_id']) && ! $settings->principal_release_enabled)) {
                throw new RuntimeException('Withdrawal requests are disabled by the administrator.');
            }
            $user = self::member($member, ! empty($data['position_id']));
            $email = $user->email;
            $last = DB::table('withdrawal_email_challenges')->where('user_id', $member->id)->latest('id')->first();
            if ($last && now()->diffInSeconds($last->created_at, true) < 60) {
                throw new RuntimeException('Wait 60 seconds before requesting another email OTP.');
            }
            $digest = self::context($member, $data);
            $wallet = UsdtVerifiedWallet::forWithdrawal($member->id, (int) $data['usdt_wallet_id']);
            $destination = $wallet->network.' '.$wallet->address;
            DB::table('withdrawal_email_challenges')->where('user_id', $member->id)->whereNull('consumed_at')->update(['consumed_at' => now()]);

            return DB::table('withdrawal_email_challenges')->insertGetId(['user_id' => $member->id, 'context_digest' => $digest, 'email_digest' => hash('sha256', $email), 'otp_hash' => Hash::make($code), 'expires_at' => now()->addMinutes(5), 'created_at' => now(), 'updated_at' => now()]);
        });
        try {
            Mail::raw('Your LUMIFLOWON withdrawal OTP is '.$code.'. Amount: '.$data['amount'].' USDT. Destination: '.$destination.'. Expires in 5 minutes. Do not share this code. Changing amount, wallet or fee requires a new code.', function ($m) use ($email): void {
                $m->to($email)->subject('LUMIFLOWON withdrawal verification');
            });
        } catch (\Throwable $e) {
            DB::table('withdrawal_email_challenges')->where('id', $id)->update(['consumed_at' => now()]);
            report($e);
            throw new RuntimeException('Unable to send withdrawal email. Please try again.');
        }

        return $id;
    }

    public static function execute(User $member, array $data, int $challengeId, string $code, callable $operation): int
    {
        $valid = DB::transaction(function () use ($member, $data, $challengeId, $code): bool {
            $user = self::member($member, ! empty($data['position_id']));
            $row = DB::table('withdrawal_email_challenges')->where('id', $challengeId)->where('user_id', $member->id)->lockForUpdate()->first();
            if (! $row || $row->consumed_at || now()->gte($row->expires_at) || $row->attempts >= 5 || ! hash_equals($row->email_digest, hash('sha256', $user->email)) || ! hash_equals($row->context_digest, self::context($member, $data))) {
                return false;
            }
            DB::table('withdrawal_email_challenges')->where('id', $row->id)->increment('attempts');
            if (! preg_match('/\A\d{6}\z/D', $code) || ! Hash::check($code, $row->otp_hash)) {
                return false;
            }
            DB::table('withdrawal_email_challenges')->where('id', $row->id)->update(['verified_at' => now()]);

            return true;
        });
        if (! $valid) {
            throw ValidationException::withMessages(['withdrawal_otp' => 'Invalid, expired or changed withdrawal OTP. Request a fresh code.']);
        }

        return DB::transaction(function () use ($member, $data, $challengeId, $operation): int {
            if (! empty($data['position_id'])) {
                DB::table('withdrawal_settings')->where('id', 1)->lockForUpdate()->first();
            }
            $user = self::member($member, ! empty($data['position_id']));
            $row = DB::table('withdrawal_email_challenges')->where('id', $challengeId)->where('user_id', $member->id)->lockForUpdate()->first();
            if (! $row || ! $row->verified_at || $row->consumed_at || now()->gte($row->expires_at) || ! hash_equals($row->email_digest, hash('sha256', $user->email)) || ! hash_equals($row->context_digest, self::context($member, $data))) {
                throw new RuntimeException('Withdrawal OTP already used or details changed.');
            }
            $id = $operation();
            DB::table('withdrawal_email_challenges')->where('id', $row->id)->update(['consumed_at' => now(), 'updated_at' => now()]);

            return $id;
        });
    }
}
