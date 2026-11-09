<?php

namespace App\Contracts;

interface IncomingUsdtChainVerifier
{
    /** Verify canonical, confirmed incoming USDT covering the requested credit; return exact receipt and uncredited surplus. */
    public function incomingEvidence(string $network, string $txHash, string $recipient, string $requestedAmount): array;
}
