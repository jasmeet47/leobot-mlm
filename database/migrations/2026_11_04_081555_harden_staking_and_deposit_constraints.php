<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE UNIQUE INDEX staking_controls_singleton ON staking_controls ((true))');
        DB::statement('ALTER TABLE staking_positions ADD CONSTRAINT staking_positive_amount CHECK (amount > 0)');
        DB::statement('CREATE UNIQUE INDEX deposit_network_hash_canonical ON member_deposit_requests (network, lower(tx_hash))');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX deposit_network_hash_canonical');
        DB::statement('ALTER TABLE staking_positions DROP CONSTRAINT staking_positive_amount');
        DB::statement('DROP INDEX staking_controls_singleton');
    }
};
