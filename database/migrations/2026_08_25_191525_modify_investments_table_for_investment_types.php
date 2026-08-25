<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->dropColumn('type');
            $table->foreignId('investment_type_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('investment_type_id');
            $table->enum('type', ['renda_fixa', 'acoes', 'fundos_imobiliarios', 'tesouro_direto', 'criptomoeda', 'previdencia', 'outro'])->default('renda_fixa')->after('user_id');
        });
    }
};
