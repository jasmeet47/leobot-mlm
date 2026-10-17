<?php

namespace Tests\Unit;

use App\Contracts\UsdtChainVerifier;
use App\Contracts\UsdtWalletOwnershipVerifier;
use App\Services\DisabledUsdtChainVerifier;
use App\Services\DisabledUsdtWalletOwnershipVerifier;
use Tests\TestCase;

final class UsdtVerifierBindingsFailClosedTest extends TestCase
{
    public function test_chain_verifier_is_disabled_by_default(): void
    {
        $this->assertInstanceOf(DisabledUsdtChainVerifier::class, app(UsdtChainVerifier::class));
    }

    public function test_wallet_ownership_verifier_is_disabled_by_default(): void
    {
        $this->assertInstanceOf(DisabledUsdtWalletOwnershipVerifier::class, app(UsdtWalletOwnershipVerifier::class));
    }
}
