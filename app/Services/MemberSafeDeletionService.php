<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/** Fail-closed physical deletion for a never-used member identity. */
final class MemberSafeDeletionService
{
    public function delete(int $memberId, int $adminId, string $reason, string $username): void
    {
        DB::transaction(function () use ($memberId, $adminId, $reason, $username): void {
            $member = DB::table('users')->where('id', $memberId)->lockForUpdate()->first();
            if (!$member || $member->role !== 'member' || $memberId === $adminId ||
                !hash_equals((string) $member->username, $username)) {
                throw new RuntimeException('Member or confirmation invalid.');
            }
            // Physical deletion is forbidden for any account with financial balances or history.
            foreach (['activation_balance','available_balance','total_investment','lifetime_income'] as $field) {
                if (property_exists($member, $field) && bccomp((string) ($member->{$field} ?? '0'), '0', 8) !== 0) {
                    throw new RuntimeException('Member has financial balance or history.');
                }
            }
            if ($member->deleted_at !== null) {
                throw new RuntimeException('Retired member has audit history; physical deletion forbidden.');
            }
            if (!Schema::hasTable('reserved_member_identities') || !Schema::hasTable('member_retirement_audits')) {
                throw new RuntimeException('Identity/audit migration missing.');
            }
            if (DB::table('users')->where('sponsor_user_id', $memberId)->exists() ||
                DB::table('users')->where('sponsor_id', $member->username)->exists()) {
                throw new RuntimeException('Member has a downline.');
            }
            // PostgreSQL-only: enumerate ALL non-system tables and scan plausible user-reference
            // columns, including every actual FK to users. Unknown DB engines fail closed.
            if (DB::getDriverName() !== 'pgsql') {
                throw new RuntimeException('Physical deletion requires verified PostgreSQL.');
            }
            $columns = DB::select(<<<'SQL'
                SELECT c.table_schema, c.table_name, c.column_name, c.data_type
                FROM information_schema.columns c
                WHERE c.table_schema = current_schema()
                  AND c.table_name <> 'users'
                  AND (
                    c.column_name ~ '(^user_id$|^member_id$|_user_id$|^user_.*_id$|^member_.*_id$|^from_user_id$|^reference_user_id$|^original_user_id$)'
                    OR (c.table_name, c.column_name) IN (
                        SELECT kcu.table_name, kcu.column_name
                        FROM information_schema.table_constraints tc
                        JOIN information_schema.key_column_usage kcu
                          ON tc.constraint_name = kcu.constraint_name AND tc.table_schema = kcu.table_schema
                        JOIN information_schema.constraint_column_usage ccu
                          ON tc.constraint_name = ccu.constraint_name AND tc.table_schema = ccu.table_schema
                        WHERE tc.constraint_type = 'FOREIGN KEY' AND ccu.table_name = 'users'
                    )
                  )
                SQL);
            foreach ($columns as $col) {
                if (!in_array($col->data_type, ['bigint', 'integer', 'smallint', 'numeric'], true)) {
                    throw new RuntimeException('Unknown reference type: '.$col->table_name.'.'.$col->column_name);
                }
                $table = '"'.str_replace('"', '""', $col->table_schema).'"."'.str_replace('"', '""', $col->table_name).'"';
                $column = '"'.str_replace('"', '""', $col->column_name).'"';
                if (DB::selectOne("SELECT 1 AS found FROM {$table} WHERE {$column} = ? LIMIT 1", [$memberId])) {
                    throw new RuntimeException('Member has dependent records in '.$col->table_name.'.');
                }
            }
            // Even non-FK sponsor usernames must be protected.
            foreach (['sponsor_id', 'sponsor_username', 'referrer_username'] as $column) {
                foreach (DB::select('SELECT table_schema, table_name FROM information_schema.columns WHERE table_schema = current_schema() AND column_name = ?', [$column]) as $table) {
                    $name = '"'.str_replace('"','""',$table->table_schema).'"."'.str_replace('"','""',$table->table_name).'"';
                    if (DB::selectOne("SELECT 1 AS found FROM {$name} WHERE \"{$column}\" = ? LIMIT 1", [$member->username])) {
                        throw new RuntimeException('Sponsor reference exists in '.$table->table_name);
                    }
                }
            }
            // Preserve identity BEFORE deleting. Unique constraints guard concurrent reuse.
            DB::table('reserved_member_identities')->insert([
                'original_user_id' => $memberId, 'username' => $member->username,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            // An audit with a preserved literal identity, not a user FK.
            DB::table('member_retirement_audits')->insert([
                'member_user_id' => $memberId, 'member_username' => $member->username,
                'admin_user_id' => $adminId, 'action' => 'safe_delete',
                'reason' => $reason, 'created_at' => now(), 'updated_at' => now(),
            ]);
            if (DB::table('users')->where('id', $memberId)->delete() !== 1) {
                throw new RuntimeException('Delete failed; transaction rolled back.');
            }
        }, 3);
    }
}
