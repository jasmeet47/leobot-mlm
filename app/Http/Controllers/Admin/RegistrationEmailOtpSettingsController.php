<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\RegistrationEmailOtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class RegistrationEmailOtpSettingsController extends Controller
{
    public function index(RegistrationEmailOtpService $otp)
    {
        return view('admin.registration-email-otp-settings', ['enabled' => $otp->enabled()]);
    }

    public function update(Request $request, RegistrationEmailOtpService $otp)
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        $old = $otp->enabled();
        $new = (bool) $data['enabled'];
        if ($new && in_array(config('mail.default'), ['log', 'array'], true)) {
            return back()->withErrors(['enabled' => 'Configure a real email transport before enabling registration OTP.']);
        }
        if ($old !== $new) {
            DB::transaction(function () use ($request, $old, $new) {
                DB::table('registration_email_otp_settings')->where('id', 1)->update([
                    'is_enabled' => $new, 'updated_at' => now(),
                ]);
                DB::table('registration_email_otp_setting_audits')->insert([
                    'admin_user_id' => $request->user()->getKey(),
                    'old_value' => $old, 'new_value' => $new,
                    'ip_address' => $request->ip(),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            });
        }
        return back()->with('status', 'Registration email OTP setting saved.');
    }
}
