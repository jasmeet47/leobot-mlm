<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class MemberActionOtpService
{
    private static function actor(User $user, array $data, bool $transfer): object
    {
        if ($transfer && ((int) $data['recipient_id'] === $user->id || (int) $data['recipient_id'] < 1)) {
            throw new RuntimeException('Choose another valid recipient.');
        }
        $ids = [$user->id];
        if ($transfer) {
            $ids[] = (int) $data['recipient_id'];
        }sort($ids, SORT_NUMERIC);
        $people = DB::table('users')->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        foreach ($ids as $id) {
            $p = $people->get($id);
            if (! $p || $p->role !== 'member' || ! in_array($p->status, ['active', 'inactive'], true) || $p->is_blocked || $p->deleted_at) {
                throw new RuntimeException('Registered, unblocked member accounts are required.');
            }
        }

        return $people->get($user->id);
    }

    private static function digest(string $action, array $data): string
    {
        ksort($data);

        return hash('sha256', $action.'|'.json_encode($data, JSON_THROW_ON_ERROR));
    }

    public static function issue(User $user, string $action, array $data): int
    {
        if (! in_array($action, ['profile_wallet', 'fund_transfer'], true)) {
            throw new RuntimeException('Invalid verification action.');
        }
        $code = (string) random_int(100000, 999999);
        $email = '';
        $id = DB::transaction(function () use ($user, $action, $data, $code, &$email): int {
            if ($action === 'profile_wallet') {
                MemberPaymentSecurity::assertChangesEnabled();
            } else {
                MemberTransferPolicy::assertAmount($data['amount']);
            }
            $actor = self::actor($user, $data, $action === 'fund_transfer');
            $email = $actor->email;
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('A valid account email is required.');
            }
            $last = DB::table('member_action_challenges')->where('user_id', $user->id)->where('action', $action)->latest('id')->first();
            if ($last && now()->diffInSeconds($last->created_at, true) < 60) {
                throw new RuntimeException('Wait 60 seconds before sending another OTP.');
            }
            DB::table('member_action_challenges')->where('user_id', $user->id)->where('action', $action)->whereNull('consumed_at')->update(['consumed_at' => now()]);

            return DB::table('member_action_challenges')->insertGetId(['user_id' => $user->id, 'action' => $action, 'context_digest' => self::digest($action, $data), 'email_digest' => hash('sha256', $email), 'otp_hash' => Hash::make($code), 'expires_at' => now()->addMinutes(5), 'created_at' => now(), 'updated_at' => now()]);
        });
        try {
            Mail::raw('Your LUMIFLOWON '.$action.' OTP is '.$code.'. Details: '.json_encode($data, JSON_UNESCAPED_SLASHES).'. Valid for 5 minutes. Do not share this code.', function ($message) use ($email): void {
                $message->to($email)->subject('LUMIFLOWON account action verification');
            });
        } catch (\Throwable $error) {
            DB::table('member_action_challenges')->where('id', $id)->update(['consumed_at' => now()]);
            report($error);
            throw new RuntimeException('Email could not be sent. Please try again.');
        }

        return $id;
    }

    public static function execute(User $user, string $action, array $data, int $id, string $code, callable $operation): mixed
    {
        $valid = DB::transaction(function () use ($user, $action, $data, $id, $code): bool {
            $actor = self::actor($user, $data, $action === 'fund_transfer');
            $row = DB::table('member_action_challenges')->where('id', $id)->where('user_id', $user->id)->where('action', $action)->lockForUpdate()->first();
            if (! $row || $row->consumed_at || $row->attempts >= 5 || now()->gte($row->expires_at) || ! hash_equals($row->context_digest, self::digest($action, $data)) || ! hash_equals($row->email_digest, hash('sha256', $actor->email))) {
                return false;
            }
            DB::table('member_action_challenges')->where('id', $id)->increment('attempts');
            if (! preg_match('/\A\d{6}\z/D', $code) || ! Hash::check($code, $row->otp_hash)) {
                return false;
            }
            DB::table('member_action_challenges')->where('id', $id)->update(['verified_at' => now()]);

            return true;
        });
        if (! $valid) {
            throw ValidationException::withMessages(['email_otp' => 'Invalid, expired or changed OTP. Request a new code if the details changed.']);
        }

        return DB::transaction(function () use ($user, $action, $data, $id, $operation): mixed {
            if ($action === 'profile_wallet') {
                MemberPaymentSecurity::assertChangesEnabled();
            } else {
                MemberTransferPolicy::assertAmount($data['amount']);
            }
            $actor = self::actor($user, $data, $action === 'fund_transfer');
            $row = DB::table('member_action_challenges')->where('id', $id)->where('user_id', $user->id)->where('action', $action)->lockForUpdate()->first();
            if (! $row || ! $row->verified_at || $row->consumed_at || now()->gte($row->expires_at) || ! hash_equals($row->context_digest, self::digest($action, $data)) || ! hash_equals($row->email_digest, hash('sha256', $actor->email))) {
                throw new RuntimeException('OTP already used or details changed.');
            }
            $result = $operation();
            DB::table('member_action_challenges')->where('id', $id)->update(['consumed_at' => now(), 'updated_at' => now()]);

            return $result;
        });
    }
}
