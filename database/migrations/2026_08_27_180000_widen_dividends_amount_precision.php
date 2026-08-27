<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dividends/JSCP on very small positions can legitimately be a fraction of
     * a cent (e.g. R$ 0,004101). The original decimal(14,2) column silently
     * rounded those down to zero, and the "Amount" field rejected them
     * outright via a min:0.01 rule sized for whole cents.
     */
    public function up(): void
    {
        Schema::table('dividends', function (Blueprint $table) {
            $table->decimal('amount', 16, 6)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dividends', function (Blueprint $table) {
            $table->decimal('amount', 14, 2)->change();
        });
    }
};
