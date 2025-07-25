<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        DB::statement('ALTER TABLE vouchers DROP CONSTRAINT vouchers_status_check');
        DB::statement("ALTER TABLE vouchers ADD CONSTRAINT vouchers_status_check CHECK (status IN ('available', 'sold', 'reserved'))");
    }

    public function down()
    {
        DB::statement('ALTER TABLE vouchers DROP CONSTRAINT vouchers_status_check');
        DB::statement("ALTER TABLE vouchers ADD CONSTRAINT vouchers_status_check CHECK (status IN ('available', 'sold'))");
    }
};
