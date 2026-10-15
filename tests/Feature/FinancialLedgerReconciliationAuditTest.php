<?php
namespace Tests\Feature;

use App\Models\User;
use App\Services\GenerationAllocationService;
use App\Services\UnifiedProfitSharingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class FinancialLedgerReconciliationAuditTest extends TestCase
{
    use RefreshDatabase { refreshDatabase as private laravelRefreshDatabase; }

    public function refreshDatabase()
    {
        $this->guardDisposable();
        $this->laravelRefreshDatabase();
    }

    private function guardDisposable(): void
    {
        $connection = DB::connection();
        $cfg = $connection->getConfig();
        $safe = app()->environment('testing')
            && $connection->getDriverName() === 'pgsql'
            && ($cfg['host'] ?? null) === '127.0.0.1'
            && (string)($cfg['port'] ?? '') === '5433'
            && ($cfg['database'] ?? null) === 'leobot_financial_disposable'
            && empty($cfg['url']);
        if ($safe) {
            $identity = DB::selectOne('SELECT current_database() AS db, inet_server_port() AS port');
            $safe = $identity && $identity->db === 'leobot_financial_disposable' && (int)$identity->port === 5433;
        }
        if (!$safe) { throw new RuntimeException('SAFETY STOP: disposable PostgreSQL only.'); }
        \Tests\Support\FinancialSeedState::captureIfNeeded();
    }

    protected function setUp(): void
    {
        parent::setUp();
        \Tests\Support\FinancialSeedState::captureIfNeeded();
    }

    protected function afterTruncatingDatabase(): void
    {
        \Tests\Support\FinancialSeedState::restoreOrCapture();
    }

    private function user(string $name, string $role = 'member'): User
    {
        $id = DB::table('users')->insertGetId([
            'username' => $name,
            'name' => 'Ledger Audit',
            'email' => Str::uuid().'@example.test',
            'phone' => '9000000000',
            'password' => Hash::make('AuditOnlyNotProduction123!'),
            'security_pin' => null,
            'status' => 'active',
            'role' => $role,
            'activation_balance' => '0.00000000',
            'available_balance' => '0.00000000',
            'total_investment' => '0.00000000',
            'lifetime_income' => '0.00000000',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return User::findOrFail($id);
    }

    private function eq(string $expected, mixed $actual): void
    {
        $this->assertSame(0, bccomp($expected, (string)$actual, 8),
            'Expected '.$expected.', received '.(string)$actual);
    }

    private function add(string $a, string $b): string
    {
        return bcadd($a, $b, 8);
    }

    public function test_two_periods_reconcile_all_funding_rows_reserves_51_levels_and_journal(): void
    {
        $admin = $this->user('AUDITADMIN', 'admin');
        $a = $this->user('AUDITMEMBERA');
        $b = $this->user('AUDITMEMBERB');
        DB::table('company_wallet')->where('id', 1)->update(['balance' => '2000.00000000']);
        DB::table('profit_sharing_settings')->where('id', 1)->update(['combined_funding_enabled' => true]);
        $ids = [];
        $ids[] = UnifiedProfitSharingService::fund($admin, '1000.00000000', 'AUDIT-PERIOD-01', [
            $a->id => '600.00000000', $b->id => '400.00000000',
        ]);
        $ids[] = UnifiedProfitSharingService::fund($admin, '200.00000004', 'AUDIT-PERIOD-02', [
            $a->id => '101.00000001', $b->id => '99.00000003',
        ]);
        $fundings = DB::table('profit_sharing_fundings')->whereIn('id', $ids)->orderBy('id')->get();
        $this->assertCount(2, $fundings);
        $totalSelf = '0'; $totalGen = '0'; $totalMagic = '0'; $totalFunded = '0';
        $totalProfit = '0'; $totalRemainder = '0';
        foreach ($fundings as $fund) {
            $parts = $this->add($this->add((string)$fund->self_amount, (string)$fund->generation_amount), (string)$fund->magic_amount);
            $this->eq($parts, $fund->funded_total);
            $this->eq($fund->verified_profit_amount, $this->add((string)$fund->funded_total, (string)$fund->company_rounding_remainder));
            $totalSelf = $this->add($totalSelf, (string)$fund->self_amount);
            $totalGen = $this->add($totalGen, (string)$fund->generation_amount);
            $totalMagic = $this->add($totalMagic, (string)$fund->magic_amount);
            $totalFunded = $this->add($totalFunded, (string)$fund->funded_total);
            $totalProfit = $this->add($totalProfit, (string)$fund->verified_profit_amount);
            $totalRemainder = $this->add($totalRemainder, (string)$fund->company_rounding_remainder);
            $selfRows = DB::table('profit_sharing_self_reserves')->where('funding_id', $fund->id)->get();
            $this->assertCount(2, $selfRows);
            $selfSum = '0'; $profitSum = '0';
            foreach ($selfRows as $r) {
                $selfSum = $this->add($selfSum, (string)$r->self_share_reserved);
                $profitSum = $this->add($profitSum, (string)$r->member_profit_amount);
            }
            $this->eq($fund->self_amount, $selfSum);
            $this->eq($fund->verified_profit_amount, $profitSum);
            $generation = DB::table('generation_profit_fundings')->find($fund->generation_funding_id);
            $this->assertNotNull($generation);
            $this->eq($fund->generation_amount, $generation->pool_amount);
            $budgets = DB::table('generation_level_budgets')->where('funding_id', $generation->id)->orderBy('level_number')->get();
            $this->assertCount(51, $budgets);
            $budgetTotal = '0';
            foreach ($budgets as $index => $budget) {
                $this->assertSame($index + 1, (int)$budget->level_number);
                $classified = $this->add($this->add((string)$budget->unallocated_amount, (string)$budget->held_amount),
                    $this->add((string)$budget->payable_amount, (string)$budget->paid_amount));
                $this->eq($budget->budget_amount, $classified);
                $budgetTotal = $this->add($budgetTotal, (string)$budget->budget_amount);
            }
            $this->eq($generation->pool_amount, $this->add($budgetTotal, (string)$generation->rounding_unallocated));
            $ledger = DB::table('financial_ledger')->where('reference_type', 'profit_sharing_funding')
                ->where('reference_id', $fund->id)->get();
            $this->assertCount(4, $ledger);
            $net = '0'; $types = [];
            foreach ($ledger as $entry) {
                $this->assertSame('completed', $entry->status);
                $this->assertNotNull($entry->transaction_key);
                $this->assertNotContains($entry->entry_type, $types);
                $types[] = $entry->entry_type;
                $net = $this->add($net, (string)$entry->amount);
                $this->eq($entry->balance_after, $this->add((string)$entry->balance_before, (string)$entry->amount));
            }
            $this->eq('0', $net);
        }
        $this->eq($totalProfit, $this->add($totalFunded, $totalRemainder));
        $account = DB::table('profit_sharing_reserve_accounts')->find(1);
        $this->eq($totalSelf, $account->self_balance);
        $this->eq($totalSelf, $account->total_self_funded);
        $this->eq($totalMagic, $account->magic_balance);
        $this->eq($totalMagic, $account->total_magic_funded);
        $pool = DB::table('generation_pool_accounts')->find(1);
        $this->eq($totalGen, $pool->balance);
        $this->eq($totalGen, $pool->total_funded);
        $this->eq($totalGen, $this->add($this->add((string)$pool->unallocated_balance, (string)$pool->held_balance), (string)$pool->payable_balance));
        $this->eq('0', $pool->total_paid);
        $this->eq(bcsub('2000.00000000', $totalFunded, 8), DB::table('company_wallet')->where('id', 1)->value('balance'));
        $beforeLedger = DB::table('financial_ledger')->count();
        $beforeFundings = DB::table('profit_sharing_fundings')->count();
        $sameId = UnifiedProfitSharingService::fund($admin, '1000.00000000', 'AUDIT-PERIOD-01', [
            $a->id => '600.00000000', $b->id => '400.00000000',
        ]);
        $this->assertSame((int)$ids[0], $sameId);
        $this->assertSame($beforeLedger, DB::table('financial_ledger')->count());
        $this->assertSame($beforeFundings, DB::table('profit_sharing_fundings')->count());
        $genId = (int)$fundings[0]->generation_funding_id;
        $run = GenerationAllocationService::allocate($admin, $genId);
        $this->assertSame($run, GenerationAllocationService::allocate($admin, $genId));
        $this->assertSame(1, DB::table('generation_allocation_runs')->where('funding_id', $genId)->count());
        $this->assertSame($beforeLedger, DB::table('financial_ledger')->count());
        $this->eq($totalGen, DB::table('generation_pool_accounts')->where('id', 1)->value('balance'));
        $this->eq('0', $a->fresh()->available_balance);
        $this->eq('0', $a->fresh()->lifetime_income);
        $this->eq('0', $b->fresh()->available_balance);
        $this->eq('0', $b->fresh()->lifetime_income);
    }
}