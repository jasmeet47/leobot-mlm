<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DistributeDailyROI extends Command
{
    /**
     * Keep the existing command name for compatibility.
     */
    protected $signature = 'roi:distribute';

    protected $description =
        'ROI distribution disabled until secure package-wise payout system is ready.';

    /**
     * Fail safely without modifying any financial data.
     */
    public function handle(): int
    {
        Log::warning(
            'Legacy ROI distribution was blocked. No payouts executed.'
        );

        $this->error(
            'ROI distribution is disabled for security. '
            . 'The new package-wise ROI system is not ready yet.'
        );

        return self::FAILURE;
    }
}
