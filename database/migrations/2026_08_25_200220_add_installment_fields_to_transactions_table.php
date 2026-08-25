<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedTinyInteger('installment_number')->nullable()->after('parent_transaction_id');
            $table->unsignedTinyInteger('installment_total')->nullable()->after('installment_number');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['installment_number', 'installment_total']);
        });
    }
};
