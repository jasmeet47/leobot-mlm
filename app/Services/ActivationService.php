<?php

namespace App\Services;

use App\Models\User;
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
    public static function activateSelf(
        User $authenticatedUser,
        string $amount,
        string $activationKey
    ): int {
        /*
         * Validate the package amount as a decimal string.
         * Never use floating-point calculations for money.
         */
        if (
            !preg_match(
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
        if (!Str::isUuid($activationKey)) {
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
                !in_array(
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

                    'activation_key' =>
                        $activationKey,

                    'investment_amount' =>
                        $amount,

                    'roi_enabled' => false,

                    'roi_percentage' => '0',

                    'roi_distribution_mode' =>
                        'manual',

                    'roi_cap_multiplier' =>
                        '3',

                    'magic_cap_multiplier' =>
                        '3',

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
                'activation:debit:' . $activationKey,
                [
                    'reference_user_id' => $userId,

                    'reference_type' =>
                        'activation_package',

                    'reference_id' => $packageId,

                    'description' =>
                        'Package-wise activation wallet debit.',
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
                    'total_investment' =>
                        $newInvestment,

                    'status' => 'active',

                    'updated_at' => now(),
                ]);

            /*
             * No commission or income payout here.
             */
            return (int) $packageId;
        }, 3);
    }
}
