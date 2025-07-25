<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // $table->string('payment_method')->nullable()->after('status');
            $table->string('va_number')->nullable()->after('payment_method');
            $table->string('vendor')->nullable()->after('va_number');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'va_number', 'vendor']);
        });
    }
};
