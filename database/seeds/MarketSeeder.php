<?php

use Illuminate\Database\Seeder;
use App\Market;

class MarketSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Market::truncate();

        Market::create([
            'name' => 'Bittrex',
            'url' => 'https://bittrex.com/api/v1.1/public/getmarketsummaries',
            'orderbook_url' => "https://bittrex.com/api/v1.1/public/getorderbook?market=BTC-%s&type=both",
            'maker_fee' => 0.25,
            'taker_fee' => 0.25,
        ]);

        Market::create([
            'name' => 'Bleutrade',
            'url' => 'https://bleutrade.com/api/v2/public/getmarketsummaries',
            'orderbook_url' => "https://bleutrade.com/api/v2/public/getorderbook?market=%s_BTC&type=ALL&depth=3",
            'maker_fee' => 0.25,
            'taker_fee' => 0.25,
        ]);

        Market::create([
            'name' => 'Kraken',
            'url' => 'https://api.kraken.com/0/public/Ticker?pair=DASHXBT,EOSXBT,GNOXBT,ETCXBT,ETHXBT,ICNXBT,LTCXBT,MLNXBT,REPXBT,XDGXBT,XLMXBT,XMRXBT,XRPXBT,ZECXBT',
            'orderbook_url' => "https://api.kraken.com/0/public/Depth?pair=%sXBT&count=3",
            'maker_fee' => 0.16,
            'taker_fee' => 0.26,
        ]);

        Market::create([
            'name' => 'Poloniex',
            'url' => '',
            'orderbook_url' => "",
            'maker_fee' => 0.15,
            'taker_fee' => 0.25,
        ]);

    }
}
