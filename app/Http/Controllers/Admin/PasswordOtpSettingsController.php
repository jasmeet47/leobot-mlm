<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PasswordOtpSettingsController extends Controller {
    private function ready(): void {
        abort_unless(Schema::hasTable('leobot_password_security_settings'), 503);
    }
    public function index() {
        $this->ready();
        return view('admin.password-otp-settings', [
            'enabled' => (bool) DB::table('leobot_password_security_settings')->where('id', 1)->value('enabled'),
        ]);
    }
    public function update(Request $request) {
        $this->ready();
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        if ($data['enabled']) {
            abort_unless(Schema::hasTable('password_reset_otps')
                && Schema::hasTable('password_reset_security_events')
                && Schema::hasColumn('users', 'member_login_version')
                && !in_array(config('mail.default'), ['log', 'array'], true), 422,
                'OTP schema and real mail delivery are required.');
        }
        DB::table('leobot_password_security_settings')->where('id', 1)->update([
            'enabled' => (bool) $data['enabled'], 'updated_at' => now(),
        ]);
        return back()->with('status', 'Password OTP setting saved.');
    }
}
