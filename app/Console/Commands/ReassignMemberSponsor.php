<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\TeamCounterService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReassignMemberSponsor extends Command
{
    protected $signature = 'lumiflowon:reassign-sponsor {member} {sponsor} {--actor= : Existing authorized admin ID} {--apply : Apply the audited change} {--beta-if-present : Beta-only execution; skip accounts absent from the isolated Beta database}';

    protected $description = 'Preview or atomically move a member referral subtree; no wallet or Matrix changes';

    public function handle(): int
    {
        if ($this->option('beta-if-present')) {
            if (getenv('RENDER_SERVICE_ID') !== 'srv-dbfhk8avcj2c7386lg5g' || DB::getDriverName() !== 'pgsql' || ! preg_match('/\Adpg-dbfg8n7avr4c73e23vog(?:-[a-z0-9]+)?(?:\.[a-z0-9.-]+)?\z/D', (string) DB::connection()->getConfig('host'))) {
                $this->error('Isolated Beta identity required.');

                return self::FAILURE;
            }
            if (User::whereIn('username', [$this->argument('member'), $this->argument('sponsor')])->count() !== 2) {
                $this->info('Requested live accounts are absent from Beta; existing Beta records preserved.');

                return self::SUCCESS;
            }
        }
        $member = User::where('username', $this->argument('member'))->firstOrFail();
        $sponsor = User::where('username', $this->argument('sponsor'))->firstOrFail();
        if (! $this->option('apply')) {
            $this->line(json_encode(['member' => $member->username, 'name' => $member->name, 'current_sponsor' => $member->sponsor_id, 'new_sponsor' => $sponsor->username, 'sponsor_name' => $sponsor->name], JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }
        $this->line(json_encode(TeamCounterService::reassignSponsor((int) $this->option('actor'), $member->id, $sponsor->id), JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
