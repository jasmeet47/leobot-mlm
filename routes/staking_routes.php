<?php
use Illuminate\Support\Facades\Route;
Route::middleware(['auth','account.not_blocked'])->group(function(){
 Route::post('/member/staking', [\App\Http\Controllers\Member\StakingController::class,'store'])->middleware(['throttle:10,1',\App\Http\Middleware\EnsureMemberPin::class])->name('member.staking.store');
});
Route::middleware(['auth','account.not_blocked','admin'])->group(function(){
 Route::post('/admin/staking/toggle',[\App\Http\Controllers\Admin\StakingControlController::class,'update'])->middleware('throttle:5,1')->name('admin.staking.toggle');
});

Route::middleware(['auth','account.not_blocked'])->get(
    '/member/staking',
    function () {
        $enabled = \Illuminate\Support\Facades\Schema::hasTable('staking_controls')
            && (bool) \Illuminate\Support\Facades\DB::table('staking_controls')->value('enabled');

        return view('member.staking', compact('enabled'));
    }
)->name('member.staking.index');

Route::middleware(['auth','account.not_blocked','admin'])->get(
    '/admin/staking',
    function () {
        $ready = \Illuminate\Support\Facades\Schema::hasTable('staking_controls')
            && \Illuminate\Support\Facades\Schema::hasTable('staking_positions')
            && \Illuminate\Support\Facades\Schema::hasTable('staking_control_audits');

        $enabled = $ready
            && (bool) \Illuminate\Support\Facades\DB::table('staking_controls')->value('enabled');

        return view('admin.staking', compact('ready','enabled'));
    }
)->name('admin.staking.index');