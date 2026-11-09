<?php

namespace App\Contracts;

interface UsdtChainVerificationDiagnostics
{
    public function failureReason(): ?string;
}
