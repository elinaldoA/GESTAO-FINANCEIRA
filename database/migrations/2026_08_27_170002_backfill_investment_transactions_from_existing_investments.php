<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Seeds one initial transaction per existing investment so the ledger
     * (introduced by the previous migration) has a starting point that
     * reproduces the investment's current invested_amount/quantity — without
     * this, every investment created before the ledger existed would show
     * zero quantity/invested amount once those columns become derived.
     */
    public function up(): void
    {
        DB::table('investments')
            ->whereNull('deleted_at')
            ->where('invested_amount', '>', 0)
            ->orderBy('id')
            ->get()
            ->each(function ($investment) {
                $isTicker = ! empty($investment->ticker) && (float) $investment->quantity > 0;

                DB::table('investment_transactions')->insert([
                    'user_id' => $investment->user_id,
                    'investment_id' => $investment->id,
                    'date' => $investment->created_at ? substr($investment->created_at, 0, 10) : now()->format('Y-m-d'),
                    'type' => $isTicker ? 'compra' : 'aporte',
                    'quantity' => $isTicker ? $investment->quantity : null,
                    'unit_price' => $isTicker ? round((float) $investment->invested_amount / (float) $investment->quantity, 4) : null,
                    'fees' => null,
                    'amount' => $investment->invested_amount,
                    'notes' => 'Saldo inicial migrado automaticamente.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('investment_transactions')
            ->where('notes', 'Saldo inicial migrado automaticamente.')
            ->delete();
    }
};
