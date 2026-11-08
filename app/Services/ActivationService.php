<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class ActivationService
{
    private const SCALE = 8;

    private const MAX_AMOUNT = '999999999999.99999999';

    /**
     * Activate a new package for the authenticated member.
     *
     * IMPORTANT:
     * - The controller must pass $request->user().
     * - Never use User::first() as the paying member.
     * - The activation key must be a UUID.
     * - The same key must be reused when retrying
     *   the same activation request.
     *
     * This method does NOT distribute ROI,
     * Magic Income or Generation Income.
     */
    public static function activateSelf(User $authenticatedUser, string $amount, string $activationKey): int
    {
        if (config('leobot.member_activation_enabled', false) !== true) {
            throw new RuntimeException('Member activation is disabled pending security approval.');
        }

        return self::activate($authenticatedUser, $amount, $activationKey);
    }

    public static function activateByAdmin(User $admin, User $member, string $amount, string $activationKey): int
    {
        if (config('leobot.admin_member_activation_enabled', false) !== true || config('leobot.production_financial_approval', false) !== true) {
            throw new RuntimeException('Admin activation financial approval is required.');
        }
        if (! preg_match('/\A\d+(?:\.\d{1,8})?\z/D', $amount) || bccomp($amount, '1', 8) < 0 || bccomp($amount, self::MAX_AMOUNT, 8) > 0 || ! Str::isUuid($activationKey)) {
            throw new InvalidArgumentException('Enter a valid activation amount of at least 1 USDT.');
        }

        return DB::transaction(function () use ($admin, $member, $amount, $activationKey): int {
            $actor = DB::table('users')->where('id', $admin->id)->lockForUpdate()->first();
            if (! $actor || ! in_array($actor->role, ['admin', 'super_admin'], true) || $actor->status !== 'active' || $actor->is_blocked || $actor->deleted_at) {
                throw new AuthorizationException('Active administrator required.');
            }
            $target = User::whereKey($member->id)->lockForUpdate()->firstOrFail();
            if ($target->role !== 'member' || $target->deleted_at || $target->is_blocked) {
                throw new RuntimeException('An unblocked, current member is required.');
            }
            $old = DB::table('activation_packages')->where('activation_key', $activationKey)->first();
            if ($old && (int) $old->user_id === $target->id && $target->status !== 'active') {
                throw new RuntimeException('This activation already completed before the account status changed. Find the member again for a new request.');
            }
            if (! $old && bccomp(StakingMemberEligibility::verifiedActivationBalance($target->id), $amount, 8) < 0) {
                throw new RuntimeException('Insufficient unspent verified deposit balance for activation.');
            }
            $id = self::activate($target, $amount, $activationKey);
            if (! $old) {
                DB::table('member_security_audits')->insert(['actor_id' => $admin->id, 'action' => 'admin_verified_activation', 'details' => json_encode(['user_id' => $target->id, 'package_id' => $id, 'amount' => $amount, 'source' => 'verified_deposit_balance', 'activation_key' => $activationKey], JSON_THROW_ON_ERROR), 'created_at' => now()]);

            }

            return $id;
        }, 3);
    }

    private static function activate(User $authenticatedUser, string $amount, string $activationKey): int
    {
        /*
         * Validate the package amount as a decimal string.
         * Never use floating-point calculations for money.
         */
        if (
            ! preg_match(
                '/\A\d+(?:\.\d{1,8})?\z/',
                $amount
            )
        ) {
            throw new InvalidArgumentException(
                'Invalid package amount format.'
            );
        }

        if (
            bccomp($amount, '1', self::SCALE) < 0 ||
            bccomp(
                $amount,
                self::MAX_AMOUNT,
                self::SCALE
            ) > 0
        ) {
            throw new InvalidArgumentException(
                'Invalid activation amount.'
            );
        }

        /*
         * Use a stable UUID to prevent
         * duplicate activation requests.
         */
        if (! Str::isUuid($activationKey)) {
            throw new InvalidArgumentException(
                'A valid activation UUID is required.'
            );
        }

        $userId = (int) $authenticatedUser->getKey();

        if ($userId <= 0) {
            throw new InvalidArgumentException(
                'Invalid authenticated member.'
            );
        }

        /*
         * Wallet debit, package creation and member
         * investment update are one atomic transaction.
         */
        return DB::transaction(function () use (
            $userId,
            $amount,
            $activationKey
        ): int {
            /*
             * Lock the member record to serialize
             * activation requests for this member.
             */
            $member = User::whereKey($userId)
                ->lockForUpdate()
                ->firstOrFail();

            /*
             * Check whether this activation
             * has already been completed.
             */
            $existing = DB::table('activation_packages')
                ->where('activation_key', $activationKey)
                ->first();

            if ($existing) {
                if (
                    (int) $existing->user_id !== $userId ||
                    bccomp(
                        (string) $existing->investment_amount,
                        $amount,
                        self::SCALE
                    ) !== 0
                ) {
                    throw new RuntimeException(
                        'Activation key already used for another request.'
                    );
                }

                if ($existing->status !== 'active') {
                    throw new RuntimeException(
                        'Existing activation is not active.'
                    );
                }

                /*
                 * Idempotent retry:
                 * do not debit the wallet again.
                 */
                return (int) $existing->id;
            }

            if (
                $member->is_blocked ||
                ! in_array(
                    $member->status,
                    ['active', 'inactive'],
                    true
                )
            ) {
                throw new RuntimeException(
                    'Member status does not allow activation.'
                );
            }

            /*
             * Check the total investment limit.
             */
            $currentInvestment =
                (string) $member->total_investment;

            if (
                bccomp(
                    $currentInvestment,
                    '0',
                    self::SCALE
                ) < 0
            ) {
                throw new RuntimeException(
                    'Invalid existing investment amount.'
                );
            }

            $newInvestment = bcadd(
                $currentInvestment,
                $amount,
                self::SCALE
            );

            if (
                bccomp(
                    $newInvestment,
                    self::MAX_AMOUNT,
                    self::SCALE
                ) > 0
            ) {
                throw new RuntimeException(
                    'Total investment limit exceeded.'
                );
            }

            /*
             * Create an individual package.
             *
             * ROI starts OFF for each new package.
             * ROI and Magic caps are independent.
             */
            $packageId = DB::table('activation_packages')
                ->insertGetId([
                    'user_id' => $member->id,

                    'activation_key' => $activationKey,

                    'investment_amount' => $amount,

                    'roi_enabled' => false,

                    'roi_percentage' => '0',

                    'roi_distribution_mode' => 'manual',

                    'roi_cap_multiplier' => '3',

                    'magic_cap_multiplier' => '3',

                    'roi_income_paid' => '0',

                    'magic_income_paid' => '0',

                    'status' => 'active',

                    'activated_at' => now(),

                    'created_at' => now(),

                    'updated_at' => now(),
                ]);

            /*
             * Debit activation wallet safely.
             *
             * WalletService:
             * - checks available balance
             * - locks the wallet row
             * - inserts a financial ledger record
             * - rejects duplicate transaction keys
             *
             * Its transaction participates in
             * this enclosing database transaction.
             */
            WalletService::debit(
                $userId,
                'activation_balance',
                $amount,
                'package_activation',
                'activation:debit:'.$activationKey,
                [
                    'reference_user_id' => $userId,

                    'reference_type' => 'activation_package',

                    'reference_id' => $packageId,

                    'description' => 'Package-wise activation wallet debit.',
                ]
            );

            /*
             * Update investment and member status.
             *
             * Do not overwrite wallet fields here.
             */
            DB::table('users')
                ->where('id', $userId)
                ->update([
                    'total_investment' => $newInvestment,

                    'status' => 'active',

                    'updated_at' => now(),
                ]);

            /*
             * No commission or income payout here.
             */
            TeamCounterService::synchronize($userId);
            MagicMatrixEnrollmentService::synchronize($userId);

            return (int) $packageId;
        }, 3);
    }
}
