<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    private function equivalentUniqueIndexExists(): bool
    {
        // PostgreSQL: exactly (network, tx_hash), valid, non-partial, non-expression UNIQUE index.
        $result = DB::selectOne(<<<'SQL'
SELECT EXISTS (
    SELECT 1
    FROM pg_index i
    JOIN pg_class t ON t.oid = i.indrelid
    JOIN pg_namespace ns ON ns.oid = t.relnamespace
    WHERE ns.nspname = current_schema()
      AND t.relname = 'member_deposit_requests'
      AND i.indisunique AND i.indisvalid AND i.indisready
      AND i.indpred IS NULL AND i.indexprs IS NULL
      AND i.indnkeyatts = 2
      AND i.indnatts = 2
      AND (SELECT array_agg(a.attname ORDER BY keys.ordinality)
           FROM unnest(i.indkey) WITH ORDINALITY AS keys(attnum, ordinality)
           JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = keys.attnum
          ) = ARRAY['network','tx_hash']::name[]
) AS present
SQL);
        return in_array(strtolower((string) ($result->present ?? '')), ['1', 't', 'true'], true);
    }

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('PostgreSQL required; migration stopped.');
        }
        if (!Schema::hasTable('member_deposit_requests')) {
            throw new RuntimeException('Deposit request table missing; migration stopped.');
        }
        if ($this->equivalentUniqueIndexExists()) {
            return; // Existing valid equivalent constraint already protects deposits.
        }
        $duplicates = DB::table('member_deposit_requests')
            ->select('network', 'tx_hash')->groupBy('network', 'tx_hash')
            ->havingRaw('COUNT(*) > 1')->limit(1)->exists();
        if ($duplicates) {
            throw new RuntimeException('Duplicate deposit hashes exist; reconcile manually before migration.');
        }
        Schema::table('member_deposit_requests', function (Blueprint $table): void {
            $table->unique(['network', 'tx_hash'], 'lumi_deposit_network_tx_unique');
        });
    }

    public function down(): void
    {
        // Do not drop an index that existed before this migration.
        // A separately approved rollback must inspect migration history and ownership.
        throw new RuntimeException('Automatic rollback disabled to protect existing deposit uniqueness.');
    }
};