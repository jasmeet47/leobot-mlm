<?php

namespace App\Console\Commands;

use App\Services\DepositDestinationService;
use App\Services\StakingReconciliationService;
use App\Services\UsdtPaymentEvidence;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class StakingReadiness extends Command
{
    protected $signature = 'staking:readiness {--json : Emit a machine-readable report}';

    protected $description = 'Read-only staking schema, accounting and configuration checks; never activates financial operations';

    public function handle(): int
    {
        $report = ['checks' => [], 'accounting' => null, 'on_chain_reserve_solvency' => 'NOT VERIFIED'];
        foreach (['BEP20', 'TRC20'] as $network) {
            try {
                UsdtPaymentEvidence::assertAddress($network, DepositDestinationService::address($network));
                $report['checks'][$network.'_treasury_address'] = 'PASS';
            } catch (Throwable) {
                $report['checks'][$network.'_treasury_address'] = 'FAIL';
            }
        }
        foreach (['bsc_rpc_url', 'tron_full_node_url', 'tron_solidity_node_url'] as $key) {
            $url = (string) config('leobot_usdt_rpc.'.$key, '');
            $report['checks'][$key] = filter_var($url, FILTER_VALIDATE_URL) && parse_url($url, PHP_URL_SCHEME) === 'https' ? 'PASS' : 'FAIL';
        }
        foreach (['production_financial_approval', 'staking_enabled', 'withdrawals_enabled'] as $flag) {
            $report['checks'][$flag] = config('leobot.'.$flag, false) === true ? 'PASS' : 'FAIL';
        }
        $report['checks']['chain_verification_enabled'] = config('leobot_usdt_rpc.enabled', false) === true
            && config('leobot_withdrawal.external_payment_verification_enabled', false) === true ? 'PASS' : 'FAIL';
        try {
            if (DB::connection()->getDriverName() !== 'pgsql') {
                throw new \RuntimeException('PostgreSQL is required.');
            }
            $report['accounting'] = DB::transaction(function (): array {
                if (DB::transactionLevel() === 1) {
                    DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
                }
                foreach (['staking_positions', 'staking_principal_withdrawals', 'staking_profit_statements', 'staking_profit_allocations', 'withdrawal_payment_submissions'] as $table) {
                    if (! Schema::hasTable($table)) {
                        throw new \RuntimeException('Required staking schema is missing.');
                    }
                }
                if (! Schema::hasColumn('staking_positions', 'withdrawn_amount')) {
                    throw new \RuntimeException('Principal accounting migration is missing.');
                }

                return StakingReconciliationService::inspect();
            });
            $report['checks']['accounting'] = $report['accounting']['accounting_passed'] ? 'PASS' : 'FAIL';
            $policy = DB::table('withdrawal_settings')->where('id', 1)->first();
            $report['checks']['principal_policy'] = $policy && $policy->principal_fee_configured_at && $policy->principal_release_enabled
                && $policy->member_requests_enabled && $policy->admin_review_enabled
                && bccomp((string) $policy->principal_withdrawal_fee_percent, '0', 9) >= 0
                && bccomp((string) $policy->principal_withdrawal_fee_percent, '100', 9) < 0 ? 'PASS' : 'FAIL';
        } catch (Throwable) {
            $report['checks']['accounting'] = 'FAIL';
            $report['accounting_error'] = 'Unable to reconcile required PostgreSQL staking schema. Inspect migrations and read-only database access.';
        }
        $report['configuration_and_accounting_passed'] = ! in_array('FAIL', $report['checks'], true);
        $report['notice'] = 'This checks configuration shape and internal accounting only. Real RPC connectivity, treasury solvency, deployment identity and operating approvals require independent verification.';
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $report['configuration_and_accounting_passed'] ? self::SUCCESS : self::FAILURE;
    }
}
