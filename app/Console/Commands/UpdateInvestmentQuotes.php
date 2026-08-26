<?php

namespace App\Console\Commands;

use App\Models\Investment;
use App\Services\StockQuoteService;
use Illuminate\Console\Command;

class UpdateInvestmentQuotes extends Command
{
    protected $signature = 'investments:update-quotes';

    protected $description = 'Atualiza o valor atual dos investimentos que têm ticker e quantidade cadastrados, buscando a cotação em tempo real';

    public function handle(StockQuoteService $quotes): int
    {
        $updated = 0;
        $failed = 0;

        Investment::query()
            ->whereNotNull('ticker')
            ->whereNotNull('quantity')
            ->where('is_active', true)
            ->chunkById(100, function ($investments) use ($quotes, &$updated, &$failed) {
                foreach ($investments as $investment) {
                    $quote = $quotes->fetchQuote($investment->ticker);

                    if ($quote === null) {
                        $failed++;

                        continue;
                    }

                    $investment->update([
                        'current_amount' => round($quote['price'] * (float) $investment->quantity, 2),
                        'day_change_percent' => $quote['changePercent'],
                        'week52_low' => $quote['week52Low'],
                        'week52_high' => $quote['week52High'],
                        'quote_updated_at' => now(),
                    ]);

                    $updated++;
                }
            });

        $this->info("Cotações atualizadas para {$updated} investimento(s). {$failed} falharam.");

        return self::SUCCESS;
    }
}
