<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\MagicSplitPolicy;
use Illuminate\Console\Command;

class ActivateMagicSplitPolicy extends Command
{
    protected $signature = 'magic:activate-split {--actor= : Existing authorized admin ID}';

    protected $description = 'Activate the approved 65/30/3/2 policy for new funding only; no payout';

    public function handle(): int
    {
        MagicSplitPolicy::activate(User::findOrFail((int) $this->option('actor')));
        $this->info('MAGIC_SPLIT_POLICY_ACTIVE: new fundings only; no wallet credit performed.');

        return self::SUCCESS;
    }
}
