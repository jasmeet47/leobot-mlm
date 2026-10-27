<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class WithdrawalPaymentEvidenceUniqueTest extends TestCase
{
    use DatabaseTransactions;

    public function test_duplicate_transaction_hash_is_rejected(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $this->assertSame('127.0.0.1', DB::connection()->getConfig('host'));
        $this->assertSame('5433', (string) DB::connection()->getConfig('port'));
        $this->assertSame('leobot_financial_disposable', DB::connection()->getDatabaseName());

        $admin = $this->person('admin');
        $member = $this->person('member');
        $first = $this->withdrawal($member);
        $second = $this->withdrawal($member);
        $hash = '0x' . str_repeat('a', 64);

        $this->insertEvidence($first, $admin, $hash);

        try {
            DB::transaction(function () use ($second, $admin, $hash): void {
                $this->insertEvidence($second, $admin, $hash);
            });
            $this->fail('Duplicate transaction was accepted.');
        } catch (QueryException $e) {
            $this->assertSame('23505', $e->errorInfo[0] ?? null);
        }

        $this->assertSame(1, DB::table('withdrawal_payment_evidence')
            ->where('network', 'BEP20')
            ->where('tx_hash', $hash)
            ->count());
    }

    private function person(string $role): int
    {
        $id = Str::random(16);
        return DB::table('users')->insertGetId([
            'username' => 'P4' . $id,
            'name' => 'Phase 4 Test',
            'email' => $id . '@example.test',
            'phone' => (string) random_int(1000000000, 9999999999),
            'password' => bcrypt(Str::random(32)),
            'status' => 'active',
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function withdrawal(int $member): int
    {
        return DB::table('withdrawal_requests')->insertGetId([
            'user_id' => $member,
            'request_key' => (string) Str::uuid(),
            'wallet_type' => 'earnings',
            'gross_amount' => '10.00000000',
            'fee_amount' => '0.00000000',
            'net_amount' => '10.00000000',
            'status' => 'approved_awaiting_payment',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertEvidence(int $withdrawal, int $admin, string $hash): void
    {
        DB::table('withdrawal_payment_evidence')->insert([
            'withdrawal_request_id' => $withdrawal,
            'network' => 'BEP20',
            'tx_hash' => $hash,
            'recipient' => '0x1111111111111111111111111111111111111111',
            'amount' => '10.00000000',
            'verified_by' => $admin,
            'verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
