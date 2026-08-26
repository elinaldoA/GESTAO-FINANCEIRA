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
     * Fetch the latest price, day change percent and 52-week range for a ticker.
     *
     * @return array{price: float, changePercent: ?float, week52Low: ?float, week52High: ?float}|null
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

            if (! is_numeric($price)) {
                return null;
            }

            $changePercent = $response->json('results.0.regularMarketChangePercent');
            $week52Low = $response->json('results.0.fiftyTwoWeekLow');
            $week52High = $response->json('results.0.fiftyTwoWeekHigh');

            return [
                'price' => (float) $price,
                'changePercent' => is_numeric($changePercent) ? (float) $changePercent : null,
                'week52Low' => is_numeric($week52Low) ? (float) $week52Low : null,
                'week52High' => is_numeric($week52High) ? (float) $week52High : null,
            ];
        } catch (\Throwable $e) {
            Log::warning("StockQuoteService: erro ao consultar cotação de {$ticker}: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Fetch the USD/BRL exchange rate from the free AwesomeAPI (no token required).
     *
     * @return array{price: float, changePercent: ?float}|null
     */
    public function fetchUsdBrl(): ?array
    {
        try {
            $response = Http::timeout(10)->get('https://economia.awesomeapi.com.br/last/USD-BRL');

            if (! $response->successful()) {
                return null;
            }

            $price = $response->json('USDBRL.bid');
            $changePercent = $response->json('USDBRL.pctChange');

            if (! is_numeric($price)) {
                return null;
            }

            return [
                'price' => (float) $price,
                'changePercent' => is_numeric($changePercent) ? (float) $changePercent : null,
            ];
        } catch (\Throwable $e) {
            Log::warning("StockQuoteService: erro ao consultar cotação do dólar: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Fetch the Ibovespa index from Brapi. Requires a BRAPI_TOKEN (services.brapi.token) —
     * indices are not available on Brapi's free unauthenticated tier, so this returns
     * null (silently) when no token is configured, instead of making a doomed request.
     *
     * @return array{price: float, changePercent: ?float}|null
     */
    public function fetchIbovespa(): ?array
    {
        if (! config('services.brapi.token')) {
            return null;
        }

        $quote = $this->fetchQuote('^BVSP');

        return $quote === null ? null : ['price' => $quote['price'], 'changePercent' => $quote['changePercent']];
    }
}
