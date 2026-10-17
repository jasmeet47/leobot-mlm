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
        $this->app->bind(UsdtChainVerifier::class, DisabledUsdtChainVerifier::class);
        if ($this->app->environment('testing')) {
            $this->app->bind(UsdtWalletOwnershipVerifier::class, \App\Services\MultiNetworkUsdtWalletOwnershipVerifier::class);
        } else {
            $this->app->bind(UsdtWalletOwnershipVerifier::class, DisabledUsdtWalletOwnershipVerifier::class);
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