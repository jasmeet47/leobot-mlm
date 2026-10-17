<?php
namespace App\Contracts;

/** Implement using independent trusted chain RPC/indexer; never trust browser/admin supplied receipts. */
interface UsdtChainVerifier
{
    /**
     * Return true only after checking finality, successful token Transfer event,
     * canonical USDT contract, chain ID, recipient, exact base-unit amount,
     * and no reorg. Throw on RPC errors or uncertainty.
     */
    public function verified(string $network, string $txHash, string $recipient, string $amount): bool;
}
