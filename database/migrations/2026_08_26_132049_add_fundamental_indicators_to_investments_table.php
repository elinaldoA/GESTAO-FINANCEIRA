<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->decimal('price_earnings', 10, 2)->nullable()->after('week52_high');
            $table->decimal('price_to_book', 10, 2)->nullable()->after('price_earnings');
            $table->decimal('dividend_yield', 6, 2)->nullable()->after('price_to_book');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->dropColumn(['price_earnings', 'price_to_book', 'dividend_yield']);
        });
    }
};
