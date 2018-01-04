<?php

namespace App\Console\Commands;

use App\Market;
use App\Wallet;
use App\Coin;
use Illuminate\Console\Command;

class WalletPortfolio extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:portfolio {market=all}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display all the wallets with a positive balance and the total value in BTC/USD';

    /**
     * Create a new command instance.
     *
     */
    public function __construct()
    {
        bcscale(16);

        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

        $btc = Market::getPrice('BTC');
        $this->info("Current BTC/USD price: $btc USD");

        $market = $this->argument('market');

        if ($market != 'all') {
            $btc = $this->marketPortfolio($market);
        } else {
            $btc = 0;
            $markets = Market::all()->pluck('name');
            foreach ($markets as $market) {
                $btc = bcadd($btc, $this->marketPortfolio($market), 10);
            }
        }

        $usd = bcmul($btc, Market::getPrice('BTC'), 2);

        $this->warn("Total assets value: $btc BTC ($usd USD)");


    }

    private function marketPortfolio($market)
    {
        $wallets = Wallet::select(['market', 'coin', 'balance'])
            ->where('balance', '>', 0)
            ->where('market', '=', $market)
            ->orderBy('coin')
            ->get();

        $data = [];

        $btc_usd = Market::getPrice('BTC');

        $Market = Market::factory($market);

        foreach ($wallets as $wallet) {

            //take last price

            $last = ($wallet->coin == 'BTC')?1: $Market->getLastPrice($wallet->coin);

            $btc = bcmul($wallet->balance, $last, 10);

            $data[] = [
                'market' => $wallet->market,
                'coin' => $wallet->coin,
                'balance' => $wallet->balance,
                'btc_value' => $btc,
                'usd_value' => bcmul($btc, $btc_usd, 2),
            ];
        }

        $collectedData = collect($data);
        bcscale(10);
        $total_btc = $collectedData->pluck('btc_value')->reduce('bcadd');
        bcscale(2);
        // add total row
        $data[] = [
            'market' => '',
            'coin' => '',
            'balance' => 'TOTAL:',
            'btc_value' => $total_btc,
            'usd_value' => $collectedData->pluck('usd_value')->reduce('bcadd'),
        ];

        $this->table(
            ['Market', 'Coin', 'Balance', 'BTC Value', 'USD Value'],
            $data
        );

//        $this->info("Total $total_btc BTC");

        return $total_btc;
    }
}
