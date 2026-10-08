<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ActivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class ActivationServiceTest extends TestCase
{
    use RefreshDatabase;

    /*
     * Create a test member.
     * This member belongs only to the test database.
     */
    private function createTestMember(
        string $activationBalance = '200.00000000'
    ): User {
        $unique = Str::lower(
            str_replace('-', '', (string) Str::uuid())
        );

        $id = DB::table('users')->insertGetId([
            'username' => 'TEST' . substr($unique, 0, 12),
            'name' => 'Activation Test Member',
            'email' => $unique . '@example.test',
            'phone' => '9000000000',
            'password' => Hash::make('TestPassword123!'),
            'security_pin' => Hash::make('123456'),
            'status' => 'inactive',
            'activation_balance' => $activationBalance,
            'available_balance' => '0',
            'total_investment' => '0',
            'lifetime_income' => '0',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::findOrFail($id);
    }

    /*
     * Compare financial amounts without floats.
     */
    private function assertAmountEquals(
        string $expected,
        mixed $actual
    ): void {
        $this->assertSame(
            0,
            bccomp($expected, (string) $actual, 8),
            'Financial amounts do not match.'
        );
    }

    /*
     * TEST 1:
     * Activation creates one package,
     * debits the wallet and records the ledger.
     */
    public function test_activation_creates_package_and_ledger(): void
    {
        $member = $this->createTestMember('200');

        $key = (string) Str::uuid();

        $packageId = ActivationService::activateSelf(
            $member,
            '100.00000000',
            $key
        );

        $this->assertGreaterThan(0, $packageId);

        $package = DB::table('activation_packages')
            ->where('id', $packageId)
            ->first();

        $this->assertNotNull($package);

        $this->assertSame(
            (int) $member->id,
            (int) $package->user_id
        );

        $this->assertSame('active', $package->status);

        $this->assertFalse((bool) $package->roi_enabled);

        $this->assertAmountEquals(
            '100',
            $package->investment_amount
        );

        $this->assertAmountEquals(
            '3',
            $package->roi_cap_multiplier
        );

        $this->assertAmountEquals(
            '3',
            $package->magic_cap_multiplier
        );

        $updatedMember = $member->fresh();

        $this->assertSame('active', $updatedMember->status);

        $this->assertAmountEquals(
            '100',
            $updatedMember->activation_balance
        );

        $this->assertAmountEquals(
            '100',
            $updatedMember->total_investment
        );

        $ledger = DB::table('financial_ledger')
            ->where(
                'transaction_key',
                'activation:debit:' . $key
            )
            ->first();

        $this->assertNotNull($ledger);

        $this->assertSame(
            'activation_balance',
            $ledger->wallet_type
        );

        $this->assertAmountEquals(
            '-100',
            $ledger->amount
        );

        $this->assertAmountEquals(
            '200',
            $ledger->balance_before
        );

        $this->assertAmountEquals(
            '100',
            $ledger->balance_after
        );

        $this->assertSame(
            1,
            DB::table('activation_packages')->count()
        );

        $this->assertSame(
            1,
            DB::table('financial_ledger')->count()
        );
    }

    /*
     * TEST 2:
     * Retrying the same activation UUID
     * must not debit the wallet again.
     */
    public function test_duplicate_request_does_not_debit_twice(): void
    {
        $member = $this->createTestMember('200');

        $key = (string) Str::uuid();

        $firstId = ActivationService::activateSelf(
            $member,
            '100',
            $key
        );

        $secondId = ActivationService::activateSelf(
            $member,
            '100.00000000',
            $key
        );

        $this->assertSame($firstId, $secondId);

        $this->assertAmountEquals(
            '100',
            $member->fresh()->activation_balance
        );

        $this->assertAmountEquals(
            '100',
            $member->fresh()->total_investment
        );

        $this->assertSame(
            1,
            DB::table('activation_packages')->count()
        );

        $this->assertSame(
            1,
            DB::table('financial_ledger')->count()
        );
    }

    /*
     * TEST 3:
     * Insufficient balance must not
     * create a package or ledger entry.
     */
    public function test_insufficient_balance_rolls_back_activation(): void
    {
        $member = $this->createTestMember('20');

        $key = (string) Str::uuid();

        $failed = false;

        try {
            ActivationService::activateSelf(
                $member,
                '100',
                $key
            );
        } catch (RuntimeException $exception) {
            $failed = true;
        }

        $this->assertTrue(
            $failed,
            'Activation should fail with insufficient balance.'
        );

        $this->assertSame(
            0,
            DB::table('activation_packages')->count()
        );

        $this->assertSame(
            0,
            DB::table('financial_ledger')->count()
        );

        $this->assertAmountEquals(
            '20',
            $member->fresh()->activation_balance
        );

        $this->assertAmountEquals(
            '0',
            $member->fresh()->total_investment
        );

        $this->assertSame(
            'inactive',
            $member->fresh()->status
        );
    }

    /*
     * TEST 4:
     * Reusing an activation UUID with
     * a different amount must be rejected.
     */
    public function test_same_key_with_different_amount_is_rejected(): void
    {
        $member = $this->createTestMember('200');

        $key = (string) Str::uuid();

        ActivationService::activateSelf(
            $member,
            '50',
            $key
        );

        $failed = false;

        try {
            ActivationService::activateSelf(
                $member,
                '60',
                $key
            );
        } catch (RuntimeException $exception) {
            $failed = true;
        }

        $this->assertTrue(
            $failed,
            'A reused UUID with a different amount must fail.'
        );

        $this->assertAmountEquals(
            '150',
            $member->fresh()->activation_balance
        );

        $this->assertAmountEquals(
            '50',
            $member->fresh()->total_investment
        );

        $this->assertSame(
            1,
            DB::table('activation_packages')->count()
        );

        $this->assertSame(
            1,
            DB::table('financial_ledger')->count()
        );
    }
}
