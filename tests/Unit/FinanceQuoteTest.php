<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/fengbro_finance.php';

final class FinanceQuoteTest extends TestCase
{
    public function test_regular_price_excludes_after_hours_and_conflicting_baselines(): void
    {
        $meta = ['regularMarketPrice' => 98, 'regularMarketChangePercent' => -2,
            'fulldayPrice' => 102, 'fulldayChange' => 2, 'previousClose' => 95, 'chartPreviousClose' => 70];
        $this->assertSame(['prevClose' => null, 'change' => null, 'changePercent' => -2.0], fengbroFinanceSessionChange($meta));
        $this->assertSame(['prevClose' => 100.0, 'change' => -2.0, 'changePercent' => -2.0], fengbroFinanceSessionChange($meta, 100));
        $this->assertSame(0.0, fengbroFinanceSessionChange(['regularMarketPrice' => 98, 'regularMarketChange' => 0])['change']);
    }

    public function test_taiwan_quote_preserves_the_sign_without_guessing_from_unsigned_html(): void
    {
        $item = fengbroFinanceDefaultItems()[1];
        $memo = &fengbroFinanceUrlMemo();
        $saved = $memo;
        $chartUrl = 'https://query1.finance.yahoo.com/v8/finance/chart/2330.TW?range=1y&interval=1d&lang=zh-TW&region=TW';
        try {
            $memo[$chartUrl] = '';
            $memo[$item['url']] = '<p>成交 98 昨收 100 漲跌 2 漲跌幅 2%</p>';
            $quote = fengbroFinanceParseYahooTwQuote($item);
            $this->assertSame('-2', $quote['change']);
            $this->assertSame('-2%', $quote['changePercent']);
            $memo[$item['url']] = '<p>成交 98 漲跌 2 漲跌幅 2%</p>';
            $this->assertSame('', fengbroFinanceParseYahooTwQuote($item)['change']);
            $memo[$item['url']] = '<p>成交 98 漲跌 -2 漲跌幅 -2%</p>';
            $this->assertSame('-2', fengbroFinanceParseYahooTwQuote($item)['change']);
        } finally {
            $memo = $saved;
        }
    }

    public function test_weekly_range_excludes_current_week_and_old_quotes(): void
    {
        $points = [
            ['date' => '2026-11-23', 'price' => 999], ['date' => '2026-11-16', 'price' => 30],
            ['date' => '2026-05-25', 'price' => 20], ['date' => '2026-05-18', 'price' => 1],
        ];
        $this->assertSame(['high' => 30.0, 'low' => 20.0], fengbroFinanceWeeklyBand($points, '2026-11-27'));
        $this->assertSame([], fengbroFinanceWeeklyBand([], '2026-11-27'));
    }

    public function test_display_and_history_use_the_exchange_and_provider_symbols(): void
    {
        foreach (['.SPX' => '^GSPC', '.SOX' => '^SOX', '@LCO.1' => 'BZ=F', 'BTC.CM=' => 'BTC-USD', '998407.O' => '^N225'] as $input => $symbol) {
            $this->assertSame($symbol, fengbroFinanceHistorySymbol(['symbol' => $input]));
        }
        $this->assertFalse(fengbroFinanceRedForGains(['exchangeTimezone' => 'America/New_York', 'group' => 'Korea']));
        $this->assertTrue(fengbroFinanceRedForGains(['symbol' => '2330.TW']));
    }
}
