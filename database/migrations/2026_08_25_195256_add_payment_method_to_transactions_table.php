<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('payment_method', ['pix', 'debito', 'credito', 'dinheiro', 'boleto', 'outro'])
                ->nullable()
                ->after('type');
        });

        DB::table('transactions')->where('type', '!=', 'transferencia')->whereNull('payment_method')
            ->update(['payment_method' => 'debito']);

        DB::table('transactions')->whereNotNull('credit_card_id')
            ->update(['payment_method' => 'credito']);
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });
    }
};
