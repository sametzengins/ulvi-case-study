<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per user. The balance is kept here so the row can be locked.
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->bigInteger('balance')->default(0);
            $table->timestamps();
        });

        // One row per balance change. Rows are never updated or deleted.
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('type');
            $table->bigInteger('amount');
            $table->bigInteger('balance_before');
            $table->bigInteger('balance_after');
            $table->string('idempotency_key')->unique();
            $table->timestamp('created_at');

            $table->index(['user_id', 'id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE wallets ADD CONSTRAINT wallets_balance_not_negative CHECK (balance >= 0)');
            DB::statement('ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_balance_arithmetic CHECK (balance_after = balance_before + amount)');
            DB::statement('ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_balance_after_not_negative CHECK (balance_after >= 0)');

            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION ledger_entries_are_append_only() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'ledger_entries is append-only (% refused)', TG_OP;
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER ledger_entries_append_only
                    BEFORE UPDATE OR DELETE ON ledger_entries
                    FOR EACH ROW EXECUTE FUNCTION ledger_entries_are_append_only();
                SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS ledger_entries_append_only ON ledger_entries; DROP FUNCTION IF EXISTS ledger_entries_are_append_only();');
        }

        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('wallets');
    }
};
