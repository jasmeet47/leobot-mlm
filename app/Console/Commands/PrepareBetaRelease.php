<?php

namespace App\Console\Commands;

use App\Services\CompanyReferralService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Additive migrations on the explicitly identified Beta service and database only. */
final class PrepareBetaRelease extends Command
{
    protected $signature = 'lumiflowon:beta-prepare';

    protected $description = 'Guard Beta isolation before additive migrations; refuse Production and live finance';

    public function handle(): int
    {
        if (getenv('RENDER_SERVICE_ID') !== 'srv-dbfhk8avcj2c7386lg5g'
            || getenv('LUMIFLOWON_RELEASE_STAGE') !== 'beta') {
            $this->error('Beta service identity guard failed.');

            return self::FAILURE;
        }
        $db = DB::connection();
        $host = (string) $db->getConfig('host');
        if ($db->getDriverName() !== 'pgsql'
            || ! preg_match('/\Adpg-dbfg8n7avr4c73e23vog(?:-[a-z0-9]+)?(?:\.[a-z0-9.-]+)?\z/D', $host)) {
            $this->error('Beta database resource identity guard failed.');

            return self::FAILURE;
        }
        foreach (['production_financial_approval', 'staking_enabled', 'staking_profit_enabled', 'deposits_enabled', 'withdrawals_enabled', 'self_income_settlement_enabled', 'generation_income_settlement_enabled'] as $flag) {
            if (config('leobot.'.$flag, false) !== false) {
                $this->error('Beta live financial operations must remain disabled.');

                return self::FAILURE;
            }
        }
        if (config('mail.default') !== 'log') {
            $this->error('Beta requires the log mailer to prevent real emails.');

            return self::FAILURE;
        }
        $identity = $db->selectOne('SELECT current_database() AS database_name');
        if (! $identity || (string) $identity->database_name !== (string) $db->getConfig('database')) {
            $this->error('Beta database connection identity does not match its binding.');

            return self::FAILURE;
        }
        if ($this->call('migrate', ['--force' => true, '--no-interaction' => true]) !== self::SUCCESS) {
            return self::FAILURE;
        }
        if (config('leobot.company_referral_enabled', false) === true) {
            $email = (string) getenv('LUMIFLOWON_BETA_COMPANY_OWNER_EMAIL');
            if (! str_ends_with($email, '@example.test')) {
                $this->error('Beta company account requires a disposable example.test owner.');

                return self::FAILURE;
            }
            CompanyReferralService::prepare($email);
            $this->info('Company referral mapping verified in isolated Beta; no funding or admin role granted.');
        }
        $this->info('LUMIFLOWON_BETA_RELEASE: identity verified; additive migrations completed; live finance disabled.');

        return self::SUCCESS;
    }
}
