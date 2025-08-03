<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Ubah user_account dan password_account jadi nullable
        DB::statement(<<<'SQL'
            ALTER TABLE vouchers
              ALTER COLUMN user_account DROP NOT NULL,
              ALTER COLUMN password_account DROP NOT NULL;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Sebelum mengembalikan ke NOT NULL, pastikan tidak ada NULL agar tidak error
        $result = DB::selectOne(<<<'SQL'
            SELECT COUNT(*) AS cnt
            FROM vouchers
            WHERE user_account IS NULL OR password_account IS NULL
        SQL);

        if ($result->cnt > 0) {
            throw new \RuntimeException("Tidak bisa mengembalikan ke NOT NULL karena ada {$result->cnt} baris dengan NULL pada user_account atau password_account.");
        }

        DB::statement(<<<'SQL'
            ALTER TABLE vouchers
              ALTER COLUMN user_account SET NOT NULL,
              ALTER COLUMN password_account SET NOT NULL;
        SQL);
    }
};
