<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MemberDashboardController extends Controller
{
    public function index(Request $request)
    {
        $member = $request->user();

        abort_unless($member !== null, 401);

        // Only the authenticated member's team.
        $teamSummary = DB::table('member_teams')
            ->where('user_id', $member->id)
            ->selectRaw(
                'COALESCE(SUM(active_count), 0) AS total_active_team,
                 COALESCE(SUM(inactive_count), 0) AS total_inactive_team,
                 COALESCE(SUM(total_business), 0) AS total_team_business'
            )
            ->first();

        $levelRows = DB::table('member_teams')
            ->where('user_id', $member->id)
            ->whereBetween('level', [1, 51])
            ->select(
                'level',
                'active_count',
                'inactive_count',
                'total_business'
            )
            ->orderBy('level')
            ->get()
            ->keyBy('level');

        // Missing or invalid privacy settings hide details.
        $privacy = DB::table('referral_privacy_settings')
            ->where('id', 1)
            ->first();

        $phoneVisibility = in_array(
            $privacy->phone_visibility ?? 'off',
            ['off', 'masked', 'full'],
            true
        ) ? ($privacy->phone_visibility ?? 'off') : 'off';

        $emailVisibility = in_array(
            $privacy->email_visibility ?? 'off',
            ['off', 'masked', 'full'],
            true
        ) ? ($privacy->email_visibility ?? 'off') : 'off';

        $showInvestment = self::enabled(
            $privacy->show_investment_amount ?? false
        );

        $showPackage = self::enabled(
            $privacy->show_package_status ?? false
        );

        // Only Level 1 direct referrals.
        $query = DB::table('users')
            ->leftJoin(
                'referral_contact_consents as rcc',
                'rcc.user_id',
                '=',
                'users.id'
            )
            ->where('users.sponsor_user_id', $member->id)
            ->select(
                'users.id',
                'users.username',
                'users.name',
                'users.status',
                'users.created_at',
                'rcc.phone_consent',
                'rcc.email_consent'
            );

        // Consent required for BOTH masked and full phone.
        if (in_array($phoneVisibility, ['masked', 'full'], true)) {
            $query->selectRaw(
                'CASE WHEN rcc.phone_consent = ? THEN users.phone ELSE NULL END AS phone',
                [true]
            );
        }

        // Consent required for BOTH masked and full email.
        if (in_array($emailVisibility, ['masked', 'full'], true)) {
            $query->selectRaw(
                'CASE WHEN rcc.email_consent = ? THEN users.email ELSE NULL END AS email',
                [true]
            );
        }

        if ($showInvestment) {
            $query->addSelect('users.total_investment');
        }

        $referrals = $query
            ->orderByDesc('users.id')
            ->paginate(10, ['*'], 'referrals_page');

        // Fetch package statuses for the current page only.
        $packagesByUser = collect();

        if ($showPackage && $referrals->isNotEmpty()) {
            $referralIds = $referrals
                ->getCollection()
                ->pluck('id')
                ->all();

            $packagesByUser = DB::table('activation_packages')
                ->whereIn('user_id', $referralIds)
                ->select('user_id', 'status')
                ->get()
                ->groupBy('user_id');
        }

        // Convert to safe display-only records.
        $referrals->getCollection()->transform(
            function ($referral) use (
                $phoneVisibility,
                $emailVisibility,
                $showInvestment,
                $showPackage,
                $packagesByUser
            ) {
                $phoneAllowed = self::enabled(
                    $referral->phone_consent ?? false
                );

                $emailAllowed = self::enabled(
                    $referral->email_consent ?? false
                );

                $referral->display_phone = match ($phoneVisibility) {
                    'masked' => $phoneAllowed
                        ? self::maskPhone($referral->phone ?? '')
                        : 'Hidden',

                    'full' => $phoneAllowed
                        && !empty($referral->phone)
                            ? $referral->phone
                            : 'Hidden',

                    default => 'Hidden',
                };

                $referral->display_email = match ($emailVisibility) {
                    'masked' => $emailAllowed
                        ? self::maskEmail($referral->email ?? '')
                        : 'Hidden',

                    'full' => $emailAllowed
                        && !empty($referral->email)
                            ? $referral->email
                            : 'Hidden',

                    default => 'Hidden',
                };

                $referral->display_investment = $showInvestment
                    ? bcadd(
                        (string) ($referral->total_investment ?? '0'),
                        '0',
                        8
                    ) . ' USDT'
                    : 'Hidden';

                // Package status is independent of users.status.
                $referral->display_package_status = 'Hidden';

                if ($showPackage) {
                    $packages = $packagesByUser->get(
                        $referral->id,
                        collect()
                    );

                    if ($packages->isEmpty()) {
                        $referral->display_package_status = 'No Package';
                    } elseif ($packages->contains('status', 'active')) {
                        $referral->display_package_status = 'Active';
                    } elseif ($packages->contains('status', 'pending')) {
                        $referral->display_package_status = 'Pending';
                    } elseif ($packages->contains('status', 'closed')) {
                        $referral->display_package_status = 'Closed';
                    } else {
                        $referral->display_package_status = 'Not available';
                    }
                }

                // Do not pass raw contact or consent fields to view.
                unset(
                    $referral->phone,
                    $referral->email,
                    $referral->phone_consent,
                    $referral->email_consent,
                    $referral->total_investment
                );

                return $referral;
            }
        );

        // Read only this member's own contact-sharing consent.
        $contactConsent = DB::table('referral_contact_consents')
            ->where('user_id', $member->id)
            ->first();

        $phoneConsent = self::enabled(
            $contactConsent->phone_consent ?? false
        );

        $emailConsent = self::enabled(
            $contactConsent->email_consent ?? false
        );

        // Member-specific reserves only. None of these amounts are paid income.
        $selfReserved = DB::table('profit_sharing_self_reserves')
            ->where('user_id', $member->id)
            ->where('status', 'reserved_no_payout')
            ->selectRaw('COALESCE(SUM(self_share_reserved), 0) AS total')
            ->first();

        // Combine two generation reserve reads into one database round trip.
        $generationTotals = DB::table('generation_member_allocations')
            ->where('user_id', $member->id)
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN classification = ? AND status = ? THEN share_amount ELSE 0 END), 0) AS qualified_total,
                 COALESCE(SUM(CASE WHEN classification = ? AND status = ? THEN share_amount ELSE 0 END), 0) AS held_total",
                [
                    'payable',
                    'qualified_reserved_no_payout',
                    'held',
                    'held_unqualified',
                ]
            )
            ->first();

        $incomeSummary = [
            'self_reserved' => (string) ($selfReserved->total ?? '0'),
            'generation_qualified' => (string) ($generationTotals->qualified_total ?? '0'),
            'generation_held' => (string) ($generationTotals->held_total ?? '0'),
        ];

        // Completed member wallet entries only; never show company pool journals.
        $walletHistory = DB::table('financial_ledger')
            ->where('user_id', $member->id)
            ->whereIn('wallet_type', [
                'available_balance',
                'activation_balance',
            ])
            ->where('status', 'completed')
            ->select([
                'id',
                'entry_type',
                'wallet_type',
                'amount',
                'balance_after',
                'created_at',
            ])
            ->orderByDesc('id')
            ->paginate(10, ['*'], 'wallet_page');
        return view('member.dashboard', [
            'magicIncomeSummary' => null,
            'member' => $member,
            'teamSummary' => $teamSummary,
            'levelRows' => $levelRows,
            'referrals' => $referrals,
            'phoneConsent' => $phoneConsent,
            'emailConsent' => $emailConsent,
            'incomeSummary' => $incomeSummary,
            'walletHistory' => $walletHistory,
        ]);
    }

    private static function enabled(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(
            strtolower((string) $value),
            ['1', 't', 'true', 'yes', 'on'],
            true
        );
    }

    private static function maskPhone(string $phone): string
    {
        $length = strlen($phone);

        if ($length < 5) {
            return '****';
        }

        return substr($phone, 0, 2)
            . str_repeat('X', $length - 4)
            . substr($phone, -2);
    }

    private static function maskEmail(string $email): string
    {
        if (!str_contains($email, '@')) {
            return '****';
        }

        [$name, $domain] = explode('@', $email, 2);

        if ($name === '' || $domain === '') {
            return '****';
        }

        return substr($name, 0, 1)
            . '***@'
            . $domain;
    }
}
