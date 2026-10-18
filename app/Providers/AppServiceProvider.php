<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use App\Contracts\UsdtChainVerifier;
use App\Contracts\UsdtWalletOwnershipVerifier;
use App\Services\DisabledUsdtChainVerifier;
use App\Services\DisabledUsdtWalletOwnershipVerifier;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UsdtChainVerifier::class, function ($app) {
            if (
                config('leobot.production_financial_approval', false) === true &&
                config('leobot_usdt_rpc.enabled', false) === true &&
                config('leobot_withdrawal.external_payment_verification_enabled', false) === true
            ) {
                return $app->make(\App\Services\ReadOnlyUsdtChainVerifier::class);
            }

            return $app->make(DisabledUsdtChainVerifier::class);
        });
        // Wallet ownership signatures authorize enrollment only.
        // They do not authorize deposits, withdrawals, or payments.
        $this->app->bind(
            UsdtWalletOwnershipVerifier::class,
            \App\Services\MultiNetworkUsdtWalletOwnershipVerifier::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }
    }
}