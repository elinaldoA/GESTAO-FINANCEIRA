<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['renda_fixa', 'acoes', 'fundos_imobiliarios', 'tesouro_direto', 'criptomoeda', 'previdencia', 'outro'])->default('renda_fixa');
            $table->string('broker')->nullable();
            $table->decimal('invested_amount', 14, 2)->default(0);
            $table->decimal('current_amount', 14, 2)->default(0);
            $table->string('color', 7)->default('#3b82f6');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investments');
    }
};
