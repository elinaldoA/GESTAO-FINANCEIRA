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
        Schema::create('investment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('investment_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->enum('type', ['compra', 'venda', 'aporte', 'resgate']);
            $table->decimal('quantity', 18, 8)->nullable();
            $table->decimal('unit_price', 18, 4)->nullable();
            $table->decimal('fees', 14, 2)->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['investment_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('investment_transactions');
    }
};
