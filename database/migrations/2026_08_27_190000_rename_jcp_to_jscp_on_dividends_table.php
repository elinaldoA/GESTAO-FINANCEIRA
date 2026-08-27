<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "JCP" was the wrong abbreviation for Juros sobre Capital Próprio — the
     * correct one is JSCP. Existing rows are relabeled before the enum is
     * narrowed, since MySQL silently blanks out any row whose value isn't in
     * the new allowed list.
     */
    public function up(): void
    {
        // Widen the enum first so existing 'jcp' rows can be updated without
        // MySQL truncating them to '' — then narrow it to the final list.
        Schema::table('dividends', function (Blueprint $table) {
            $table->enum('type', ['dividendo', 'jcp', 'jscp', 'rendimento', 'outro'])->default('dividendo')->change();
        });

        DB::table('dividends')->where('type', 'jcp')->update(['type' => 'jscp']);

        Schema::table('dividends', function (Blueprint $table) {
            $table->enum('type', ['dividendo', 'jscp', 'rendimento', 'outro'])->default('dividendo')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dividends', function (Blueprint $table) {
            $table->enum('type', ['dividendo', 'jcp', 'jscp', 'rendimento', 'outro'])->default('dividendo')->change();
        });

        DB::table('dividends')->where('type', 'jscp')->update(['type' => 'jcp']);

        Schema::table('dividends', function (Blueprint $table) {
            $table->enum('type', ['dividendo', 'jcp', 'rendimento', 'outro'])->default('dividendo')->change();
        });
    }
};
