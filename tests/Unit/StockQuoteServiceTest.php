<?php

namespace Tests\Unit;

use App\Services\StockQuoteService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StockQuoteServiceTest extends TestCase
{
    public function test_fetch_quote_returns_price_change_and_week52_range(): void
    {
        Http::fake([
            'brapi.dev/api/quote/PETR4*' => Http::response([
                'results' => [[
                    'regularMarketPrice' => 40.5,
                    'regularMarketChangePercent' => -1.2,
                    'fiftyTwoWeekLow' => 29.31,
                    'fiftyTwoWeekHigh' => 50.69,
                ]],
            ]),
        ]);

        $quote = (new StockQuoteService)->fetchQuote('PETR4');

        $this->assertSame(40.5, $quote['price']);
        $this->assertSame(-1.2, $quote['changePercent']);
        $this->assertSame(29.31, $quote['week52Low']);
        $this->assertSame(50.69, $quote['week52High']);
    }

    public function test_fetch_quote_returns_fundamental_indicators(): void
    {
        Http::fake([
            'brapi.dev/api/quote/PETR4*' => Http::response([
                'results' => [[
                    'regularMarketPrice' => 41.35,
                    'defaultKeyStatistics' => [
                        'trailingPE' => 4.44,
                        'priceToBook' => 1.11,
                        'dividendYield' => 0.09,
                    ],
                ]],
            ]),
        ]);

        $quote = (new StockQuoteService)->fetchQuote('PETR4');

        $this->assertSame(4.44, $quote['priceEarnings']);
        $this->assertSame(1.11, $quote['priceToBook']);
        $this->assertSame(9.0, $quote['dividendYield']);
    }

    public function test_fetch_history_returns_dated_closing_prices(): void
    {
        Http::fake([
            'brapi.dev/*' => Http::response([
                'results' => [[
                    'regularMarketPrice' => 41.0,
                    'historicalDataPrice' => [
                        ['date' => 1785121200, 'close' => 39.5],
                        ['date' => 1785207600, 'close' => 40.2],
                    ],
                ]],
            ]),
        ]);

        $history = (new StockQuoteService)->fetchHistory('PETR4');

        $this->assertCount(2, $history);
        $this->assertSame(39.5, $history[0]['close']);
    }

    public function test_fetch_history_returns_an_empty_array_when_the_api_fails(): void
    {
        Http::fake(['brapi.dev/*' => Http::response(['error' => true], 500)]);

        $this->assertSame([], (new StockQuoteService)->fetchHistory('PETR4'));
    }

    public function test_fetch_quote_returns_null_when_ticker_is_unknown(): void
    {
        Http::fake([
            'brapi.dev/*' => Http::response(['error' => true, 'code' => 'MISSING_TOKEN'], 402),
        ]);

        $this->assertNull((new StockQuoteService)->fetchQuote('INVALIDXYZ'));
    }

    public function test_fetch_usd_brl_reads_the_awesomeapi_response(): void
    {
        Http::fake([
            'economia.awesomeapi.com.br/*' => Http::response([
                'USDBRL' => ['bid' => '5.15', 'pctChange' => '0.13'],
            ]),
        ]);

        $quote = (new StockQuoteService)->fetchUsdBrl();

        $this->assertSame(5.15, $quote['price']);
        $this->assertSame(0.13, $quote['changePercent']);
    }

    public function test_fetch_ibovespa_returns_null_without_a_configured_token(): void
    {
        config(['services.brapi.token' => null]);
        Http::fake();

        $this->assertNull((new StockQuoteService)->fetchIbovespa());
        Http::assertNothingSent();
    }

    public function test_fetch_ibovespa_queries_brapi_when_a_token_is_configured(): void
    {
        config(['services.brapi.token' => 'fake-token']);
        Http::fake([
            'brapi.dev/api/quote/*' => Http::response([
                'results' => [['regularMarketPrice' => 130000.0, 'regularMarketChangePercent' => 0.8]],
            ]),
        ]);

        $quote = (new StockQuoteService)->fetchIbovespa();

        $this->assertSame(130000.0, $quote['price']);
        $this->assertSame(0.8, $quote['changePercent']);
    }

    public function test_repeated_calls_for_the_same_ticker_are_served_from_cache(): void
    {
        Http::fake([
            'brapi.dev/api/quote/PETR4*' => Http::response([
                'results' => [['regularMarketPrice' => 40.0]],
            ]),
        ]);

        $service = new StockQuoteService;
        $service->fetchQuote('PETR4');
        $service->fetchQuote('PETR4');
        $service->fetchQuote('PETR4');

        Http::assertSentCount(1);
    }

    public function test_a_failed_fetch_is_not_cached_and_can_be_retried(): void
    {
        Http::fakeSequence()
            ->push(['error' => true], 500)
            ->push(['results' => [['regularMarketPrice' => 40.0]]]);

        $service = new StockQuoteService;

        $this->assertNull($service->fetchQuote('PETR4'));
        $this->assertSame(40.0, $service->fetchQuote('PETR4')['price']);
    }
}
