<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReferralPrivacyController extends Controller
{
    private function checkAdmin(Request $request): void
    {
        $admin = $request->user();

        abort_unless(
            $admin &&
            in_array(
                (string) $admin->role,
                ['admin', 'super_admin'],
                true
            ) &&
            $admin->status === 'active',
            403,
            'Unauthorized.'
        );
    }

    public function index(Request $request)
    {
        $this->checkAdmin($request);

        $settings = DB::table('referral_privacy_settings')
            ->where('id', 1)
            ->first();

        abort_unless(
            $settings !== null,
            503,
            'Privacy settings unavailable.'
        );

        return view('admin.settings.referral-privacy', [
            'settings' => $settings,
        ]);
    }

    public function update(Request $request)
    {
        $this->checkAdmin($request);

        $validated = $request->validate([
            'phone_visibility' => [
                'required',
                Rule::in(['off', 'masked', 'full']),
            ],
            'email_visibility' => [
                'required',
                Rule::in(['off', 'masked', 'full']),
            ],
            'show_investment_amount' => [
                'required',
                Rule::in(['0', '1']),
            ],
            'show_package_status' => [
                'required',
                Rule::in(['0', '1']),
            ],
        ]);

        DB::transaction(function () use ($validated, $request) {
            $settings = DB::table('referral_privacy_settings')
                ->where('id', 1)
                ->lockForUpdate()
                ->first();

            abort_unless(
                $settings !== null,
                503,
                'Privacy settings unavailable.'
            );

            $oldSettings = [
                'phone_visibility' =>
                    $settings->phone_visibility,

                'email_visibility' =>
                    $settings->email_visibility,

                'show_investment_amount' => in_array(
                    strtolower(
                        (string) $settings->show_investment_amount
                    ),
                    ['1', 't', 'true'],
                    true
                ),

                'show_package_status' => in_array(
                    strtolower(
                        (string) $settings->show_package_status
                    ),
                    ['1', 't', 'true'],
                    true
                ),
            ];

            $newSettings = [
                'phone_visibility' =>
                    $validated['phone_visibility'],

                'email_visibility' =>
                    $validated['email_visibility'],

                'show_investment_amount' =>
                    $validated['show_investment_amount'] === '1',

                'show_package_status' =>
                    $validated['show_package_status'] === '1',
            ];

            DB::table('referral_privacy_setting_audits')->insert([
                'admin_user_id' =>
                    $request->user()->id,

                'admin_username' =>
                    $request->user()->username,

                'old_settings' => json_encode(
                    $oldSettings,
                    JSON_THROW_ON_ERROR
                ),

                'new_settings' => json_encode(
                    $newSettings,
                    JSON_THROW_ON_ERROR
                ),

                'ip_address' => $request->ip(),

                'user_agent' => $request->userAgent(),

                'created_at' => now(),

                'updated_at' => now(),
            ]);

            DB::table('referral_privacy_settings')
                ->where('id', 1)
                ->update([
                    'phone_visibility' =>
                        $validated['phone_visibility'],

                    'email_visibility' =>
                        $validated['email_visibility'],

                    'show_investment_amount' =>
                        $validated['show_investment_amount'] === '1',

                    'show_package_status' =>
                        $validated['show_package_status'] === '1',

                    'updated_at' => now(),
                ]);
        });

        return redirect()
            ->route('admin.referral-privacy.index')
            ->with(
                'success',
                'Referral privacy settings saved.'
            );
    }
}
