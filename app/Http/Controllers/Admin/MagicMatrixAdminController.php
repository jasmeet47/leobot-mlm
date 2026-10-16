<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MagicIncomeSettlementService;
use App\Services\MagicMatrixSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;
use Illuminate\Auth\Access\AuthorizationException;

final class MagicMatrixAdminController extends Controller
{
    public function index():View {
        return view('admin.magic-matrix',[
            'settings'=>DB::table('magic_matrix_settings')->where('id',1)->first(),
            'global'=>DB::table('magic_income_settings')->where('id',1)->first(),
            'rates'=>DB::table('magic_matrix_level_rates')->orderBy('level')->get(),
            'recent'=>DB::table('magic_income_entitlements')->orderByDesc('id')->limit(25)->get(),
            'canEdit'=>config('leobot_magic.magic_matrix_settings_write_enabled',false)===true &&
                (!app()->environment('production') || config('leobot_magic.production_financial_approval',false)===true),
            'canSettle'=>config('leobot_magic.magic_matrix_settlement_enabled',false)===true &&
                (!app()->environment('production') || config('leobot_magic.production_financial_approval',false)===true),
        ]);
    }
    public function updateRates(Request $request):RedirectResponse {
        abort_unless(config('leobot_magic.magic_matrix_settings_write_enabled',false)===true,404);
        $data=$request->validate(['rates'=>['required','array','size:20'],'rates.*'=>['required','regex:/\A\d+(?:\.\d{1,9})?\z/D']]);
        $rates=[];
        foreach (range(1,20) as $i) {
            if (!isset($data['rates'][$i])) return back()->withErrors(['rates'=>'All levels 1–20 required.']);
            $rates[$i]=$data['rates'][$i];
        }
        try {
            MagicMatrixSettingsService::updateLevelRates($request->user(),$rates);
        } catch (\InvalidArgumentException|RuntimeException|AuthorizationException $e) {
            return back()->withErrors(['rates'=>$e->getMessage()]);
        }
        return back()->with('status','20 rates saved with audit. Financial switches remain unchanged.');
    }
    public function updatePolicy(Request $request):RedirectResponse {
        abort_unless(config('leobot_magic.magic_matrix_settings_write_enabled',false)===true,404);
        $data=$request->validate([
            'minimum_usdt'=>['required','regex:/\A\d+(?:\.\d{1,8})?\z/D'],
            'earning_multiplier'=>['required','integer','in:3,4,5'],
            'frequency'=>['required','in:manual,daily,weekly,monthly'],
        ]);
        try {
            MagicMatrixSettingsService::update($request->user(),$data['minimum_usdt'],
                (int)$data['earning_multiplier'],$data['frequency']);
        } catch (\InvalidArgumentException|RuntimeException|AuthorizationException $e) {
            return back()->withErrors(['policy'=>$e->getMessage()]);
        }
        return back()->with('status','Magic policy saved. This does not enable payout.');
    }
    public function updateSwitches(Request $request):RedirectResponse {
        abort_unless(config('leobot_magic.magic_matrix_settings_write_enabled',false)===true &&
            (!app()->environment('production') || config('leobot_magic.production_financial_approval',false)===true),404);
        $data=$request->validate([
            'enrollment_enabled'=>['required','boolean'],
            'topup_enabled'=>['required','boolean'],
            'settlement_enabled'=>['required','boolean'],
        ]);
        DB::transaction(function()use($request,$data):void{
            $actor=DB::table('users')->where('id',$request->user()->id)->lockForUpdate()->first();
            if (!$actor || !in_array($actor->role,['admin','super_admin'],true) ||
                $actor->status!=='active' || in_array(strtolower((string)$actor->is_blocked),['1','t','true'],true)) {
                throw new AuthorizationException('Active unblocked admin required.');
            }
            $settings=DB::table('magic_matrix_settings')->where('id',1)->lockForUpdate()->first();
            if (!$settings) throw new RuntimeException('Settings missing.');
            $before=[];$after=[];
            foreach (['enrollment_enabled','topup_enabled','settlement_enabled'] as $key) {
                $before[$key]=(bool)$settings->{$key};
                $after[$key]=(bool)$data[$key];
            }
            DB::table('magic_matrix_settings')->where('id',1)->update($after+[
                'updated_by'=>$actor->id,'updated_at'=>now()
            ]);
            DB::table('magic_matrix_settings_audits')->insert([
                'admin_user_id'=>$actor->id,'action'=>'switch_update',
                'before_json'=>json_encode($before,JSON_THROW_ON_ERROR),
                'after_json'=>json_encode($after,JSON_THROW_ON_ERROR),'created_at'=>now(),
            ]);
        },3);
        return back()->with('status','Database Matrix switches updated; global and code approval gates still apply.');
    }
    public function settle(Request $request):RedirectResponse {
        abort_unless(config('leobot_magic.magic_matrix_settlement_enabled',false)===true,404);
        $data=$request->validate([
            'funding_id'=>['required','integer','min:1'],
            'source_user_id'=>['required','integer','min:1'],
        ]);
        try {
            $result=MagicIncomeSettlementService::settleSource($request->user(),
                (int)$data['funding_id'],(int)$data['source_user_id']);
        } catch (RuntimeException|AuthorizationException $e) {
            return back()->withErrors(['settle'=>$e->getMessage()]);
        }
        return back()->with('status','Magic settlement reviewed: '.json_encode($result,JSON_THROW_ON_ERROR));
    }
}
