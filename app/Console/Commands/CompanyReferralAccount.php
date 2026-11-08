<?php

namespace App\Console\Commands;

use App\Services\CompanyReferralService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class CompanyReferralAccount extends Command
{
    protected $signature = 'lumiflowon:company-referral {--owner-email=} {--create : Create only after explicit database-write approval}';

    protected $description = 'Audit root ADMIN and prepare a member-only company referral account without financial funding';

    public function handle(): int
    {
        try {
            $admin = CompanyReferralService::mainAdmin();
            if (! $this->option('create')) {
                $exists = DB::table('users')->whereRaw('upper(username) = ?', ['LUMIFLOWON'])->exists();
                $this->line(json_encode(['main_admin_id' => $admin->id, 'company_username_exists' => $exists, 'read_only' => true], JSON_THROW_ON_ERROR));

                return self::SUCCESS;
            }
            $company = CompanyReferralService::prepare((string) $this->option('owner-email'));
            $this->line(json_encode(['company_user_id' => $company->id, 'username' => $company->username, 'sponsor_user_id' => $company->sponsor_user_id, 'role' => $company->role, 'status' => $company->status, 'referral_link' => route('register.form', ['ref' => $company->username])], JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (\RuntimeException $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }
    }
}
