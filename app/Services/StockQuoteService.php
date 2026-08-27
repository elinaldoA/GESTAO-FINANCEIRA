<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StockQuoteService
{
    /**
     * Minimum time between two live requests for the same ticker. Auto-refresh
     * (wire:poll) can trigger a fetch every few seconds from multiple open tabs;
     * this cache absorbs that without hammering the upstream API or its rate limits.
     */
    private const CACHE_SECONDS = 30;

    /**
     * How long a failed fetch is remembered before trying again. Laravel's
     * Cache::remember() treats a null result as a cache miss, so a downed API
     * would otherwise be hit again on every single call (e.g. every wire:poll
     * tick, from every open tab) instead of backing off.
     */
    private const FAILURE_CACHE_SECONDS = 20;

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
     * Fetch the latest price, day change percent, 52-week range and fundamentalist
     * indicators (P/L, P/VP, dividend yield — all available on Brapi's free tier via
     * the defaultKeyStatistics module) for a ticker. Results are cached briefly (see
     * CACHE_SECONDS) to protect the upstream API from being hammered by auto-refresh
     * polling.
     *
     * @return array{price: float, changePercent: ?float, week52Low: ?float, week52High: ?float, priceEarnings: ?float, priceToBook: ?float, dividendYield: ?float}|null
     */
    public function fetchQuote(string $ticker): ?array
    {
        return $this->rememberOrFail("quote:{$ticker}", self::CACHE_SECONDS, fn () => $this->fetchQuoteLive($ticker));
    }

    /**
     * Like Cache::remember(), but a failed (null) result is also cached briefly
     * under a separate key, so repeated calls back off instead of hitting the
     * upstream API again on every miss.
     *
     * @param  \Closure(): (array|null)  $live
     */
    private function rememberOrFail(string $key, int $ttl, \Closure $live): ?array
    {
        $cached = Cache::get($key);

        if ($cached !== null) {
            return $cached;
        }

        if (Cache::has("{$key}:failed")) {
            return null;
        }

        $result = $live();

        if ($result === null) {
            Cache::put("{$key}:failed", true, self::FAILURE_CACHE_SECONDS);

            return null;
        }

        Cache::put($key, $result, $ttl);

        return $result;
    }

    private function fetchQuoteLive(string $ticker): ?array
    {
        try {
            $response = Http::timeout(10)->get("https://brapi.dev/api/quote/{$ticker}", array_filter([
                'token' => config('services.brapi.token'),
                'modules' => 'defaultKeyStatistics',
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
            $priceEarnings = $response->json('results.0.defaultKeyStatistics.trailingPE')
                ?? $response->json('results.0.priceEarnings');
            $priceToBook = $response->json('results.0.defaultKeyStatistics.priceToBook');
            $dividendYield = $response->json('results.0.defaultKeyStatistics.dividendYield');

            return [
                'price' => (float) $price,
                'changePercent' => is_numeric($changePercent) ? (float) $changePercent : null,
                'week52Low' => is_numeric($week52Low) ? (float) $week52Low : null,
                'week52High' => is_numeric($week52High) ? (float) $week52High : null,
                'priceEarnings' => is_numeric($priceEarnings) ? (float) $priceEarnings : null,
                'priceToBook' => is_numeric($priceToBook) ? (float) $priceToBook : null,
                'dividendYield' => is_numeric($dividendYield) ? (float) $dividendYield * 100 : null,
            ];
        } catch (\Throwable $e) {
            Log::warning("StockQuoteService: erro ao consultar cotação de {$ticker}: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Fetch daily closing prices for a ticker over the given range (free on Brapi,
     * no token required), for the price history chart on the investment detail page.
     *
     * @return array<int, array{date: string, close: float}>
     */
    public function fetchHistory(string $ticker, string $range = '6mo'): array
    {
        return Cache::remember("history:{$ticker}:{$range}", 3600, function () use ($ticker, $range) {
            try {
                $response = Http::timeout(10)->get("https://brapi.dev/api/quote/{$ticker}", array_filter([
                    'token' => config('services.brapi.token'),
                    'range' => $range,
                    'interval' => '1d',
                ]));

                if (! $response->successful()) {
                    return [];
                }

                $points = $response->json('results.0.historicalDataPrice') ?? [];

                return collect($points)
                    ->filter(fn ($point) => isset($point['date'], $point['close']))
                    ->map(fn ($point) => [
                        'date' => Carbon::createFromTimestamp($point['date'])->format('d/m'),
                        'close' => (float) $point['close'],
                    ])
                    ->values()
                    ->all();
            } catch (\Throwable $e) {
                Log::warning("StockQuoteService: erro ao consultar histórico de {$ticker}: {$e->getMessage()}");

                return [];
            }
        });
    }

    /**
     * Fetch the USD/BRL exchange rate from the free AwesomeAPI (no token required).
     *
     * @return array{price: float, changePercent: ?float}|null
     */
    public function fetchUsdBrl(): ?array
    {
        return $this->fetchAwesomeApiPair('USD-BRL', 'USDBRL');
    }

    /**
     * Fetch the EUR/BRL exchange rate from the free AwesomeAPI (no token required).
     *
     * @return array{price: float, changePercent: ?float}|null
     */
    public function fetchEurBrl(): ?array
    {
        return $this->fetchAwesomeApiPair('EUR-BRL', 'EURBRL');
    }

    /**
     * Fetch the BTC/BRL price from the free AwesomeAPI (no token required).
     *
     * @return array{price: float, changePercent: ?float}|null
     */
    public function fetchBtcBrl(): ?array
    {
        return $this->fetchAwesomeApiPair('BTC-BRL', 'BTCBRL');
    }

    /**
     * Fetch the GBP/BRL exchange rate from the free AwesomeAPI (no token required).
     *
     * @return array{price: float, changePercent: ?float}|null
     */
    public function fetchGbpBrl(): ?array
    {
        return $this->fetchAwesomeApiPair('GBP-BRL', 'GBPBRL');
    }

    /**
     * Fetch the ARS/BRL exchange rate from the free AwesomeAPI (no token required).
     *
     * @return array{price: float, changePercent: ?float}|null
     */
    public function fetchArsBrl(): ?array
    {
        return $this->fetchAwesomeApiPair('ARS-BRL', 'ARSBRL');
    }

    /**
     * Fetch the ETH/BRL price from the free AwesomeAPI (no token required).
     *
     * @return array{price: float, changePercent: ?float}|null
     */
    public function fetchEthBrl(): ?array
    {
        return $this->fetchAwesomeApiPair('ETH-BRL', 'ETHBRL');
    }

    /**
     * Fetch the XRP/BRL price from the free AwesomeAPI (no token required).
     *
     * @return array{price: float, changePercent: ?float}|null
     */
    public function fetchXrpBrl(): ?array
    {
        return $this->fetchAwesomeApiPair('XRP-BRL', 'XRPBRL');
    }

    /**
     * Fetch a currency/asset pair from the free AwesomeAPI (economia.awesomeapi.com.br),
     * which needs no token and covers fiat pairs (USD-BRL, EUR-BRL, ...) and crypto
     * (BTC-BRL, ...) under the same "last/{PAIR}" endpoint and response shape.
     *
     * @return array{price: float, changePercent: ?float}|null
     */
    private function fetchAwesomeApiPair(string $pair, string $jsonKey): ?array
    {
        return $this->rememberOrFail("quote:{$jsonKey}", self::CACHE_SECONDS, fn () => $this->fetchAwesomeApiPairLive($pair, $jsonKey));
    }

    private function fetchAwesomeApiPairLive(string $pair, string $jsonKey): ?array
    {
        try {
            $response = Http::timeout(10)->get("https://economia.awesomeapi.com.br/last/{$pair}");

            if (! $response->successful()) {
                return null;
            }

            $price = $response->json("{$jsonKey}.bid");
            $changePercent = $response->json("{$jsonKey}.pctChange");

            if (! is_numeric($price)) {
                return null;
            }

            return [
                'price' => (float) $price,
                'changePercent' => is_numeric($changePercent) ? (float) $changePercent : null,
            ];
        } catch (\Throwable $e) {
            Log::warning("StockQuoteService: erro ao consultar cotação de {$pair}: {$e->getMessage()}");

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
