<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MagicIncomeSettlementService;
use App\Services\MagicMatrixCycleService;
use App\Services\MagicMatrixSettingsService;
use App\Services\UnifiedProfitSharingService;
use App\Services\WithdrawalRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/** Run ONLY on identified disposable PostgreSQL. Never point this suite at production. */
final class OptionAIntegrationFinancialTest extends TestCase
{
    use RefreshDatabase { refreshDatabase as private refreshDatabaseWithLaravelTrait; }
    public function refreshDatabase() {
        $this->beforeTruncatingDatabase();
        $this->refreshDatabaseWithLaravelTrait();
    }
    protected function beforeTruncatingDatabase(): void {
        $c=DB::connection();$cfg=$c->getConfig();
        $safe=app()->environment('testing') && $c->getDriverName()==='pgsql' &&
              ($cfg['host']??'')==='127.0.0.1' && (string)($cfg['port']??'')==='5433' &&
              ($cfg['database']??'')==='leobot_financial_disposable' && empty($cfg['url']);
        if ($safe) {
            $identity=DB::selectOne('SELECT current_database() AS database_name, inet_server_port() AS server_port');
            $safe=$identity && $identity->database_name==='leobot_financial_disposable' &&
                  (int)$identity->server_port===5433;
        }
        if (!$safe) throw new RuntimeException('SAFETY STOP: disposable 127.0.0.1:5433/leobot_financial_disposable only.');
        \Tests\Support\FinancialSeedState::captureIfNeeded();
    }
    protected function afterTruncatingDatabase(): void { \Tests\Support\FinancialSeedState::restoreOrCapture(); }

    private function person(string $role='member'):User {
        $uid=Str::random(13);
        $id=DB::table('users')->insertGetId([
            'username'=>'OA'.$uid,'name'=>'Option A','email'=>$uid.'@example.test',
            'phone'=>(string) random_int(1000000000, 9999999999),'password'=>Hash::make('ExamplePassword1!'),
            'security_pin'=>null,'status'=>'active','role'=>$role,
            'activation_balance'=>'0.00000000','available_balance'=>'0.00000000',
            'total_investment'=>'0.00000000','lifetime_income'=>'0.00000000',
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        return User::findOrFail($id);
    }
    private function enroll(User $u,string $ref):int {
        $pid=DB::table('activation_packages')->insertGetId([
            'user_id'=>$u->id,'activation_key'=>'OA-'.$ref,
            'investment_amount'=>'60.00000000','status'=>'active',
            'activated_at'=>now(),'created_at'=>now(),'updated_at'=>now(),
        ]);
        return MagicMatrixCycleService::enrollFromActivePackage($u,$pid,$ref);
    }
    private function fundAndReadyMagic(User $admin,User $source,string $amount='1000.00000000'):int {
        DB::table('profit_sharing_settings')->where('id',1)->update(['combined_funding_enabled'=>true]);
        DB::table('company_wallet')->where('id',1)->update(['balance'=>$amount]);
        DB::table('magic_income_settings')->where('id',1)->update(['magic_enabled'=>false]);
        $funding=UnifiedProfitSharingService::fund($admin,$amount,'OA-PROFIT-001',[$source->id=>$amount]);
        DB::table('magic_income_settings')->where('id',1)->update(['magic_enabled'=>true]);
        DB::table('magic_matrix_settings')->where('id',1)->update(['settlement_enabled'=>true,'frequency'=>'manual']);
        config()->set('leobot_magic.magic_matrix_settlement_enabled',true);
        return $funding;
    }
    public function test_source_id_distributes_3_percent_only_from_verified_trading_profit():void {
        $admin=$this->person('admin');$root=$this->person();$source=$this->person();
        DB::table('magic_income_settings')->where('id',1)->update(['magic_enabled'=>true]);
        DB::table('magic_matrix_settings')->where('id',1)->update(['enrollment_enabled'=>true]);
        config()->set('leobot_magic.magic_matrix_enrollment_enabled',true);
        $this->enroll($root,'OA-ROOT-001');$this->enroll($source,'OA-SOURCE-001');
        config()->set('leobot_magic.magic_matrix_settings_write_enabled',true);
        config()->set('leobot_magic.magic_matrix_rates_write_enabled',true);
        MagicMatrixSettingsService::updateLevelRates($admin,array_fill_keys(range(1,20),'5.000000000'));
        $fid=$this->fundAndReadyMagic($admin,$source);
        $this->assertSame('30.00000000',(string)DB::table('profit_sharing_fundings')->find($fid)->magic_amount);
        $result=MagicIncomeSettlementService::settleSource($admin,$fid,$source->id);
        $this->assertSame('1.50000000',$result['credited_usdt']);
        $this->assertSame(20,DB::table('magic_income_entitlements')->count());
        $this->assertSame('1.50000000',(string)DB::table('users')->find($root->id)->available_balance);
        $this->assertSame('28.50000000',(string)DB::table('profit_sharing_reserve_accounts')->value('magic_balance'));
        $this->assertSame('1.50000000',(string)DB::table('profit_sharing_reserve_accounts')->value('total_magic_settled'));
        $second=MagicIncomeSettlementService::settleSource($admin,$fid,$source->id);
        $this->assertTrue($second['already_settled']);
        $this->assertSame(1,DB::table('financial_ledger')->where('entry_type','magic_income_settlement')->count());
    }
    public function test_magic_cap_is_not_exceeded_and_excess_remains_held():void {
        $admin=$this->person('admin');$root=$this->person();$source=$this->person();
        DB::table('magic_income_settings')->where('id',1)->update(['magic_enabled'=>true]);
        DB::table('magic_matrix_settings')->where('id',1)->update(['enrollment_enabled'=>true]);
        config()->set('leobot_magic.magic_matrix_enrollment_enabled',true);
        $cid=$this->enroll($root,'OA-CAP-ROOT');$this->enroll($source,'OA-CAP-SOURCE');
        DB::table('magic_matrix_cycles')->where('id',$cid)->update(['magic_paid_usdt'=>'179.75000000']);
        config()->set('leobot_magic.magic_matrix_settings_write_enabled',true);
        config()->set('leobot_magic.magic_matrix_rates_write_enabled',true);
        MagicMatrixSettingsService::updateLevelRates($admin,array_fill_keys(range(1,20),'5.000000000'));
        $fid=$this->fundAndReadyMagic($admin,$source);
        $result=MagicIncomeSettlementService::settleSource($admin,$fid,$source->id);
        $this->assertSame('0.25000000',$result['credited_usdt']);
        $this->assertSame('capped',(string)DB::table('magic_matrix_cycles')->find($cid)->status);
        $this->assertSame('0.25000000',(string)DB::table('users')->find($root->id)->available_balance);
    }
    public function test_withdrawal_hold_review_and_refund_without_external_payment():void {
        $admin=$this->person('admin');$member=$this->person();
        DB::table('users')->where('id',$member->id)->update(['available_balance'=>'100.00000000']);
        DB::table('withdrawal_settings')->where('id',1)->update([
            'member_requests_enabled'=>true,'admin_review_enabled'=>true,
        ]);
        config()->set('leobot.withdrawals_enabled',true);
        $walletId=DB::table('usdt_member_wallets')->insertGetId([
    'user_id'=>$member->id,
    'network'=>'BEP20',
    'address'=>'0x1111111111111111111111111111111111111111',
    'verified_at'=>now(),
    'verification_method'=>'integration_test_fixture',
    'proof_digest'=>str_repeat('a',64),
    'created_at'=>now(),
    'updated_at'=>now(),
]);
$id=WithdrawalRequestService::request($member,'20.00000000','OA-WITH-001','earnings',$walletId);
        $this->assertSame($id,WithdrawalRequestService::request($member,'20.00000000','OA-WITH-001','earnings',$walletId));
        $this->assertSame('80.00000000',(string)DB::table('users')->find($member->id)->available_balance);
        WithdrawalRequestService::approve($admin,$id);
        $this->assertSame('approved_awaiting_payment',(string)DB::table('withdrawal_requests')->find($id)->status);
        WithdrawalRequestService::reject($admin,$id);
        $this->assertSame('100.00000000',(string)DB::table('users')->find($member->id)->available_balance);
        $this->assertSame('rejected',(string)DB::table('withdrawal_requests')->find($id)->status);
        $this->assertSame(1,DB::table('financial_ledger')->where('entry_type','withdrawal_hold')->count());
        $this->assertSame(1,DB::table('financial_ledger')->where('entry_type','withdrawal_refund')->count());
    }
}
