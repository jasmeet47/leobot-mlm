<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

final class CompanyReferralService
{
    public static function mainAdmin(): User
    {
        $rows = User::whereRaw('upper(username) = ?', ['ADMIN'])->get();
        $admin = $rows->first();
        if ($rows->count() !== 1 || ! $admin || ! in_array($admin->role, ['admin', 'super_admin'], true) || $admin->status !== 'active' || $admin->is_blocked || $admin->deleted_at !== null || $admin->sponsor_user_id !== null) {
            throw new RuntimeException('Unique active root ADMIN identity required.');
        }

        return $admin;
    }

    public static function prepare(string $ownerEmail): User
    {
        $ownerEmail = Str::lower(trim($ownerEmail));
        if (! filter_var($ownerEmail, FILTER_VALIDATE_EMAIL) || strlen($ownerEmail) > 255) {
            throw new RuntimeException('A valid authorized company owner email is required.');
        }

        return DB::transaction(function () use ($ownerEmail): User {
            $root = self::mainAdmin();
            $admin = User::whereKey($root->id)->lockForUpdate()->firstOrFail();
            if ($admin->username !== 'ADMIN' || $admin->sponsor_user_id !== null || ! in_array($admin->role, ['admin', 'super_admin'], true) || $admin->status !== 'active' || $admin->is_blocked || $admin->deleted_at !== null) {
                throw new RuntimeException('Main administrator identity changed.');
            }
            $matches = User::whereRaw('upper(username) = ?', ['LUMIFLOWON'])->lockForUpdate()->get();
            if ($matches->isNotEmpty()) {
                $company = $matches->first();
                if ($matches->count() !== 1 || $company->username !== 'LUMIFLOWON' || $company->name !== 'LUMIFLOWON Official Company' || $company->role !== 'member' || (int) $company->sponsor_user_id !== $admin->id || $company->sponsor_id !== $admin->username || $company->email !== $ownerEmail || $company->is_blocked || $company->deleted_at !== null) {
                    throw new RuntimeException('Existing company account requires ownership and sponsor review; no changes made.');
                }

                return $company;
            }
            if (User::whereRaw('lower(email) = ?', [$ownerEmail])->exists()) {
                throw new RuntimeException('Company owner email already belongs to another account.');
            }
            $company = User::create(['username' => 'LUMIFLOWON', 'name' => 'LUMIFLOWON Official Company', 'sponsor_id' => $admin->username, 'sponsor_user_id' => $admin->id, 'email' => $ownerEmail, 'phone' => '', 'password' => Hash::make(Str::random(64)), 'security_pin' => null, 'status' => 'inactive']);
            if ($company->fresh()->role !== 'member') {
                throw new RuntimeException('Company account must have member permissions only.');
            }
            TeamCounterService::synchronize($company->id);
            DB::table('member_security_audits')->insert(['actor_id' => $admin->id, 'action' => 'company_referral_prepared', 'details' => json_encode(['user_id' => $company->id, 'sponsor_user_id' => $admin->id, 'authentication' => 'random_password_email_otp_recovery', 'automatic_financial_qualification' => false], JSON_THROW_ON_ERROR), 'created_at' => now()]);

            return $company;
        }, 3);
    }
}
