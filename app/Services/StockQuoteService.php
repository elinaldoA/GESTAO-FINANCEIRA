<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StockQuoteService
{
    /**
     * Fetch the latest price for a single ticker from the Brapi API.
     *
     * Tickers are requested one at a time: a single unknown/delisted symbol
     * makes Brapi return an error for the whole batch, so batching would
     * cause one bad ticker to block quote updates for every other investment.
     */
    public function fetchPrice(string $ticker): ?float
    {
        return $this->fetchQuote($ticker)['price'] ?? null;
    }

    /**
     * Fetch the latest price and day change percent for a single ticker.
     *
     * @return array{price: float, changePercent: ?float}|null
     */
    public function fetchQuote(string $ticker): ?array
    {
        try {
            $response = Http::timeout(10)->get("https://brapi.dev/api/quote/{$ticker}", array_filter([
                'token' => config('services.brapi.token'),
            ]));

            if (! $response->successful()) {
                Log::warning("StockQuoteService: falha ao consultar cotação de {$ticker}", [
                    'status' => $response->status(),
                ]);

                return null;
            }

            $price = $response->json('results.0.regularMarketPrice');
            $changePercent = $response->json('results.0.regularMarketChangePercent');

            if (! is_numeric($price)) {
                return null;
            }

            return [
                'price' => (float) $price,
                'changePercent' => is_numeric($changePercent) ? (float) $changePercent : null,
            ];
        } catch (\Throwable $e) {
            Log::warning("StockQuoteService: erro ao consultar cotação de {$ticker}: {$e->getMessage()}");

            return null;
        }
    }
}
