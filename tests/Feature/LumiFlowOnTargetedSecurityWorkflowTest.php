<?php
namespace Tests\Feature;

use App\Models\User;
use App\Services\MemberSafeDeletionService;
use App\Services\RegistrationEmailOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class LumiFlowOnTargetedSecurityWorkflowTest extends TestCase
{
    use RefreshDatabase { refreshDatabase as private originalRefreshDatabase; }
    public function refreshDatabase(): void
    {
        $cfg = config('database.connections.pgsql', []);
        if (!app()->environment('testing') || config('database.default') !== 'pgsql'
            || ($cfg['host'] ?? '') !== '127.0.0.1' || (string)($cfg['port'] ?? '') !== '5433'
            || ($cfg['database'] ?? '') !== 'leobot_financial_disposable' || !empty($cfg['url'])) {
            throw new \RuntimeException('SAFETY STOP: Wrong disposable DB config.');
        }
        $r = DB::selectOne('SELECT current_database() AS db, inet_server_port() AS port');
        if (!$r || $r->db !== 'leobot_financial_disposable' || (int)$r->port !== 5433) {
            throw new \RuntimeException('SAFETY STOP: Wrong physical database.');
        }
        $this->originalRefreshDatabase();
    }
    private function member(string $username, string $role='member'): User
    {
        return User::create(['username'=>$username,'name'=>'Security Test','email'=>strtolower($username).'@example.test',
            'phone'=>'9000000000','password'=>Hash::make('Password12345!'),'status'=>'active',
            'security_pin'=>null] + []);
    }
    public function test_retired_member_cannot_login_via_web_or_api(): void
    {
        $member = $this->member('RETIRETEST');
        DB::table('users')->where('id',$member->id)->update(['deleted_at'=>now(),'is_blocked'=>false]);
        $this->from('/join')->post('/login',['username'=>$member->username,'password'=>'Password12345!'])
            ->assertRedirect('/join')->assertSessionHasErrors('username');
        $this->postJson('/api/login',['login'=>$member->username,'password'=>'Password12345!'])->assertForbidden();
    }
    public function test_retired_member_with_existing_token_cannot_access_api(): void
    {
        $member = $this->member('RETIREDAPI');
        $token = $member->createToken('member_auth',['member:read'])->plainTextToken;
        DB::table('users')->where('id',$member->id)->update(['deleted_at'=>now(),'is_blocked'=>false]);
        $this->withToken($token)->getJson('/api/dashboard-counters')->assertForbidden();
    }
    public function test_admin_can_restore_retired_member_but_account_stays_blocked(): void
    {
        $admin=$this->member('RESTOREADMIN');
        DB::table('users')->where('id',$admin->id)->update(['role'=>'admin']);
        $admin=$admin->fresh();
        $member=$this->member('RESTORETARGET');
        DB::table('users')->where('id',$member->id)->update(['deleted_at'=>now(),'is_blocked'=>true]);
        $this->actingAs($admin)->withSession(['member_login_version'=>(int)$admin->member_login_version])
            ->post(route('admin.members.restore',$member->id),['reason'=>'Verified restoration request'])
            ->assertRedirect();
        $row=DB::table('users')->where('id',$member->id)->first();
        $this->assertNull($row->deleted_at);
        $this->assertTrue((bool)$row->is_blocked);
        $this->assertDatabaseHas('member_retirement_audits',['member_user_id'=>$member->id,'action'=>'restore']);
    }
    public function test_otp_send_verify_consume_once_and_registration_rejects_replay(): void
    {
        // Raw email uses the array transport; MailFake::assertSentCount counts Mailables, not raw messages.
        $this->assertSame('array', config('mail.default'), 'SAFETY STOP: Mail must use the in-memory array transport.');
        $sponsor=$this->member('OTPSPONSOR');
        DB::table('registration_email_otp_settings')->where('id',1)->update(['is_enabled'=>true]);
        $email='otp-new-member@example.test';
        RateLimiter::clear('reg-otp-send:'.hash('sha256',$email.'|127.0.0.1'));
        $service=app(RegistrationEmailOtpService::class);
        $token=$service->send($email,'127.0.0.1');
        $this->assertSame(64,strlen($token));
        $this->assertSame(1,DB::table('registration_email_otp_challenges')->where('email',$email)->count());
        $this->postJson('/api/register',['sponsor_id'=>$sponsor->username,'name'=>'New Member','email'=>$email,
            'mobile'=>'9000000001','password'=>'Password12345!','password_confirmation'=>'Password12345!'])
            ->assertUnprocessable()->assertJsonValidationErrors('challenge_token');
        // The persisted challenge confirms send() executed; no external email is sent in this test.
        $this->assertSame(1, DB::table('registration_email_otp_challenges')->where('email', $email)->count());
        $challenge=DB::table('registration_email_otp_challenges')->where('email',$email)->first();
        $this->assertNull($challenge->verified_at);
        $this->assertNull($challenge->consumed_at);
        $this->assertSame(hash('sha256',$token),$challenge->challenge_token_hash);
        $this->assertFalse(Hash::check('000000',$challenge->otp_hash));
        // Simulate the correctly received OTP with a controlled hash; never send a real email.
        DB::table('registration_email_otp_challenges')->where('id',$challenge->id)->update(['otp_hash'=>Hash::make('123456')]);
        $this->postJson('/registration-email-otp/verify',['email'=>$email,'otp'=>'123456','challenge_token'=>$token])->assertOk();
        $data=['sponsor_id'=>$sponsor->username,'name'=>'New Member','email'=>$email,'mobile'=>'9000000001',
            'password'=>'Password12345!','password_confirmation'=>'Password12345!','challenge_token'=>$token];
        $this->postJson('/api/register',$data)->assertCreated();
        $this->assertNotNull(DB::table('registration_email_otp_challenges')->where('id',$challenge->id)->value('consumed_at'));
        $this->expectException(ValidationException::class);
        $service->consumeVerified($email,$token);
    }
    public function test_safe_delete_refuses_member_with_financial_history(): void
    {
        $admin=$this->member('SAFEADMIN');
        $member=$this->member('SAFEMEMBER');
        DB::table('users')->where('id',$member->id)->update(['available_balance'=>'10.00000000']);
        $this->expectException(\RuntimeException::class);
        app(MemberSafeDeletionService::class)->delete($member->id,$admin->id,'Financial history must be retained','SAFEMEMBER');
    }
}