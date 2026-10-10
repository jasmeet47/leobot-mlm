<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BusinessQualificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class BusinessQualificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function createMember(?int $sponsorId = null): User
    {
        $unique = str_replace('-', '', (string) Str::uuid());

        $id = DB::table('users')->insertGetId([
            'username' => 'BQ' . substr($unique, 0, 12),
            'name' => 'Qualification Test Member',
            'email' => $unique . '@example.test',
            'phone' => '9000000000',
            'sponsor_user_id' => $sponsorId,
            'password' => Hash::make('TestPassword123!'),
            'security_pin' => Hash::make('123456'),
            'status' => 'inactive',
            'activation_balance' => '0',
            'available_balance' => '0',
            'total_investment' => '0',
            'lifetime_income' => '0',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::findOrFail($id);
    }

    private function createPackage(
        User $member,
        string $amount,
        string $status = 'active'
    ): int {
        return DB::table('activation_packages')->insertGetId([
            'user_id' => $member->id,
            'activation_key' => (string) Str::uuid(),
            'investment_amount' => $amount,
            'status' => $status,
            'activated_at' => $status === 'active'
                ? now()
                : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assertAmount(
        string $expected,
        mixed $actual
    ): void {
        $this->assertSame(
            0,
            bccomp($expected, (string) $actual, 8)
        );
    }

    public function test_level_one_needs_no_team_business(): void
    {
        $member = $this->createMember();

        $result = BusinessQualificationService::calculate(
            $member->id
        );

        $this->assertSame(
            1,
            $result['unlocked_through_level']
        );

        $this->assertAmount(
            '0',
            $result['total_team_business']
        );

        $this->assertCount(
            51,
            $result['level_business']
        );

        $this->assertTrue(
            BusinessQualificationService::isLevelUnlocked(
                $member->id,
                1
            )
        );

        $this->assertFalse(
            BusinessQualificationService::isLevelUnlocked(
                $member->id,
                2
            )
        );
    }

    public function test_business_thresholds_unlock_correct_levels(): void
    {
        $root = $this->createMember();
        $direct = $this->createMember($root->id);

        $packageId = $this->createPackage(
            $direct,
            '499.00000000'
        );

        $cases = [
            ['499.00000000', 1],
            ['500.00000000', 2],
            ['1499.00000000', 2],
            ['1500.00000000', 4],
            ['2999.00000000', 4],
            ['3000.00000000', 6],
            ['5999.00000000', 6],
            ['6000.00000000', 8],
            ['10999.00000000', 8],
            ['11000.00000000', 51],
        ];

        foreach ($cases as [$amount, $expectedLevel]) {
            DB::table('activation_packages')
                ->where('id', $packageId)
                ->update([
                    'investment_amount' => $amount,
                ]);

            $result = BusinessQualificationService::calculate(
                $root->id
            );

            $this->assertSame(
                $expectedLevel,
                $result['unlocked_through_level'],
                'Wrong unlock level for business ' . $amount
            );

            $this->assertAmount(
                $amount,
                $result['level_business'][1]
            );
        }
    }

    public function test_only_active_packages_count_as_business(): void
    {
        $root = $this->createMember();
        $direct = $this->createMember($root->id);

        $this->createPackage($direct, '400', 'active');
        $this->createPackage($direct, '5000', 'pending');
        $this->createPackage($direct, '6000', 'closed');

        $result = BusinessQualificationService::calculate(
            $root->id
        );

        $this->assertAmount(
            '400',
            $result['total_team_business']
        );

        $this->assertSame(
            1,
            $result['unlocked_through_level']
        );
    }

    public function test_multiple_active_packages_are_summed(): void
    {
        $root = $this->createMember();
        $direct = $this->createMember($root->id);

        $this->createPackage($direct, '200');
        $this->createPackage($direct, '300');

        $result = BusinessQualificationService::calculate(
            $root->id
        );

        $this->assertAmount(
            '500',
            $result['level_business'][1]
        );

        $this->assertSame(
            2,
            $result['unlocked_through_level']
        );
    }

    public function test_business_is_counted_at_correct_team_level(): void
    {
        $root = $this->createMember();
        $levelOne = $this->createMember($root->id);
        $levelTwo = $this->createMember($levelOne->id);

        $this->createPackage($levelOne, '500');
        $this->createPackage($levelTwo, '1000');

        $result = BusinessQualificationService::calculate(
            $root->id
        );

        $this->assertAmount(
            '500',
            $result['level_business'][1]
        );

        $this->assertAmount(
            '1000',
            $result['level_business'][2]
        );

        $this->assertAmount(
            '1500',
            $result['total_team_business']
        );

        $this->assertSame(
            4,
            $result['unlocked_through_level']
        );
    }

    public function test_level_ten_business_can_unlock_all_51_levels(): void
    {
        $root = $this->createMember();
        $parent = $root;

        for ($level = 1; $level <= 10; $level++) {
            $parent = $this->createMember($parent->id);
        }

        $this->createPackage($parent, '11000');

        $result = BusinessQualificationService::calculate(
            $root->id
        );

        $this->assertAmount(
            '11000',
            $result['level_business'][10]
        );

        $this->assertAmount(
            '0',
            $result['level_business'][1]
        );

        $this->assertSame(
            51,
            $result['unlocked_through_level']
        );

        $this->assertTrue(
            BusinessQualificationService::isLevelUnlocked(
                $root->id,
                51
            )
        );
    }

    public function test_qualification_calculation_does_not_pay_money(): void
    {
        $root = $this->createMember();
        $direct = $this->createMember($root->id);

        $this->createPackage($direct, '11000');

        BusinessQualificationService::calculate($root->id);

        $this->assertSame(
            0,
            DB::table('financial_ledger')->count()
        );

        $this->assertAmount(
            '0',
            $root->fresh()->available_balance
        );

        $this->assertAmount(
            '0',
            $root->fresh()->lifetime_income
        );
    }
}
