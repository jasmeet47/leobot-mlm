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
        if ($this->app->environment('testing')) {
            $this->app->bind(UsdtWalletOwnershipVerifier::class, \App\Services\MultiNetworkUsdtWalletOwnershipVerifier::class);
        } else {
            $this->app->bind(UsdtWalletOwnershipVerifier::class, function ($app) {
                if (
                    config('leobot.production_financial_approval', false) === true &&
                    config('leobot_usdt_rpc.enabled', false) === true &&
                    config('leobot_withdrawal.external_payment_verification_enabled', false) === true
                ) {
                    return $app->make(\App\Services\MultiNetworkUsdtWalletOwnershipVerifier::class);
                }

                return $app->make(DisabledUsdtWalletOwnershipVerifier::class);
            });
        }
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