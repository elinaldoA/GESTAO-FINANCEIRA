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
            $table->string('ticker', 20)->nullable()->after('broker');
            $table->decimal('quantity', 18, 8)->nullable()->after('ticker');
            $table->timestamp('quote_updated_at')->nullable()->after('current_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->dropColumn(['ticker', 'quantity', 'quote_updated_at']);
        });
    }
};
