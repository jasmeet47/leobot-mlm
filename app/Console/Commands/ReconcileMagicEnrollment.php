<?php

namespace App\Console\Commands;

use App\Services\MagicMatrixEnrollmentService;
use Illuminate\Console\Command;

final class ReconcileMagicEnrollment extends Command
{
    protected $signature = 'magic:reconcile-enrollment {--check : Report pending enrollment without writing}';

    protected $description = 'Place missing eligible Magic members while preserving existing positions and all wallet balances';

    public function handle(): int
    {
        $enabled = MagicMatrixEnrollmentService::enrollmentEnabled();
        $placed = $this->option('check') ? 0 : MagicMatrixEnrollmentService::reconcilePending();
        $pending = count(MagicMatrixEnrollmentService::pendingMemberIds());
        $this->line(json_encode(['enrollment_enabled' => $enabled, 'read_only' => (bool) $this->option('check'), 'placed' => $placed, 'pending' => $pending], JSON_THROW_ON_ERROR));

        return $enabled && $pending === 0 ? self::SUCCESS : self::FAILURE;
    }
}
