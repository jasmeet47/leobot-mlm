<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReferralContactConsentController extends Controller
{
    public function update(Request $request)
    {
        $member = $request->user();

        // Only authenticated, active members may update consent.
        // Admin access is determined by role, never username.
        abort_unless(
            $member !== null &&
            (string) $member->role === 'member' &&
            (string) $member->status === 'active',
            403,
            'Member access required.'
        );

        $validated = $request->validate([
            'phone_consent' => ['required', 'boolean'],
            'email_consent' => ['required', 'boolean'],
        ]);

        $phoneConsent = filter_var(
            $validated['phone_consent'],
            FILTER_VALIDATE_BOOLEAN
        );

        $emailConsent = filter_var(
            $validated['email_consent'],
            FILTER_VALIDATE_BOOLEAN
        );

        DB::transaction(function () use (
            $member,
            $request,
            $phoneConsent,
            $emailConsent
        ) {
            DB::table('referral_contact_consents')
                ->insertOrIgnore([
                    'user_id' => $member->id,
                    'phone_consent' => false,
                    'email_consent' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            $current = DB::table('referral_contact_consents')
                ->where('user_id', $member->id)
                ->lockForUpdate()
                ->first();

            abort_unless($current !== null, 503);

            $enabled = static fn ($value): bool => in_array(
                strtolower((string) $value),
                ['1', 't', 'true'],
                true
            );

            $oldPhone = $enabled($current->phone_consent);
            $oldEmail = $enabled($current->email_consent);

            if (
                $oldPhone === $phoneConsent &&
                $oldEmail === $emailConsent
            ) {
                return;
            }

            $now = now();

            DB::table('referral_contact_consents')
                ->where('user_id', $member->id)
                ->update([
                    'phone_consent' => $phoneConsent,
                    'email_consent' => $emailConsent,
                    'phone_consented_at' => $phoneConsent
                        ? ($oldPhone ? $current->phone_consented_at : $now)
                        : null,
                    'email_consented_at' => $emailConsent
                        ? ($oldEmail ? $current->email_consented_at : $now)
                        : null,
                    'updated_at' => $now,
                ]);

            DB::table('referral_contact_consent_audits')->insert([
                'user_id' => $member->id,
                'old_consents' => json_encode([
                    'phone_consent' => $oldPhone,
                    'email_consent' => $oldEmail,
                ], JSON_THROW_ON_ERROR),
                'new_consents' => json_encode([
                    'phone_consent' => $phoneConsent,
                    'email_consent' => $emailConsent,
                ], JSON_THROW_ON_ERROR),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        return back()->with(
            'success',
            'Your referral contact sharing preferences have been saved.'
        );
    }
}
