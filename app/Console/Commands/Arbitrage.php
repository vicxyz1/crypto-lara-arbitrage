<?php

namespace App\Console\Commands;

use App\Market;
use Illuminate\Console\Command;

use App\Coin;
use App\Opportunity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

//use Carbon;


class Arbitrage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'arbitrage:run {--cache}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Collect data on markets';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {


        parent::__construct();
    }

    public function getData($markets)
    {
        DB::statement("INSERT INTO histories(name, market,ask,bid,`last`,ask_size,bid_size,created_at, updated_at) 
                  SELECT  name, market,ask,bid,`last`,ask_size,bid_size, created_at, CURRENT_TIMESTAMP() FROM coins");

        DB::table('coins')->truncate();

        //Gather data
        foreach ($markets as $name) {

            $this->info("Getting data for $name");

            $market = Market::factory($name);
            if (!$market->getData()) {
                $this->error("ERROR fetching data for $name");
                continue;
            }
        }

    }


    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $markets = Market::all()->pluck('name');

        $CACHE = $this->option('cache');

        if (!$CACHE) {
            $this->getData($markets);
        }

        $coins = Coin::all()->pluck('name')->unique()->sort();

        $this->info("Total coins " . count($coins) . ":\n" . $coins);

        bcscale(16);
        $sum = 0;


        $btc = Market::getPrice('BTC');
        $total_funds = 0;
        $MIN_PROFIT = env('MIN_PROFIT');

        $FULL_EXPOSURE = env('FULL_EXPOSURE');
        $MAX_EXPOSURE = env('MAX_EXPOSURE');

        $this->warn($FULL_EXPOSURE?"FULL EXPOSURE":"MAX EXPOSURE: $MAX_EXPOSURE BTC");

        foreach ($coins as $coin) {
            //ask = SELECT ask AS val, market_id FROM pairs WHERE name = coin.name ORDER BY ask LIMIT 1

            $ask = DB::table('coins')->where('name', $coin)->orderby('ask')->first();
            $bid = DB::table('coins')->where('name', $coin)->orderby('bid', 'desc')->first();

            $last_min = DB::table('coins')->where('name', $coin)->orderby('last')->first();
            $last_max = DB::table('coins')->where('name', $coin)->orderby('last', 'desc')->first();

            if ($ask->market == $bid->market) continue;


//            $this->info($coin . " {$last_min->market} Last : {$last_min->last}  {$last_max->market} Last: {$last_max->last} ");


            if ($ask->ask <= 0 || $bid->bid <= 0) {
                Log::info($coin . " {$ask->market} Ask: {$ask->ask} ({$ask->last}) {$bid->market} Bid: {$bid->bid} ({$bid->last})");
                Log::warning("Invalid ask or bid, continued");
                continue;
            }






            //POSSIBLE WINNER
            if (bcsub($bid->bid, $ask->ask) > 0) {

                Log::info($coin . " {$ask->market} Ask: {$ask->ask} ({$ask->last}) {$bid->market} Bid: {$bid->bid} ({$bid->last})");
                Log::info($coin . " {$last_min->market} Last : {$last_min->last}  {$last_max->market} Last: {$last_max->last} ");

                $spread = bcsub($bid->bid, $ask->ask);

                $volatility = bcdiv( $spread, $bid->bid)*100;

                Log::info("Spread: $spread $coin $volatility %");

                if ($volatility < 0.5) {
                    Log::info("Volatility too low, ignore opportunity");
                    continue;
                }

                $this->info($coin . " {$ask->market} Ask: {$ask->ask} ({$ask->last})| {$bid->market} Bid: {$bid->bid} ({$bid->last}) ");

                //GET ASK ORDER
                $askMarket = Market::factory($ask->market);

                $bidMarket = Market::factory($bid->market);

                if (!$askMarket->isSupported($coin)) {
                    $this->warn("Sorry $coin deactivated in $ask->market");
                    continue;
                }

                if (!$bidMarket->isSupported($coin)) {
                    $this->warn("Sorry $coin deactivated in $bid->market");
                    continue;
                }

                //get Orderbook
                if (!$CACHE || $ask->ask_size == 0 || $bid->bid_size == 0) {


                    $orderbook = $askMarket->getOrderBook($coin);

                    if (!$orderbook) {
                        Log::info($orderbook);
                        $this->info("Invalid Orderbook, ignore opportunity");
                        continue;
                    }

                    $ask->ask_size = $orderbook['ask'];
                    $ask->ask = $orderbook['ask_rate'];

                    Coin::find($ask->id)
                        ->update([
                                'ask_size' => $ask->ask_size,
                                'ask' => $ask->ask
                            ]

                        );


                    $this->info($coin . " {$ask->market} ASK Orderbook  Rate: {$ask->ask} Size: {$ask->ask_size}");

                    //GET BID ORDER
                    $orderbook = $bidMarket->getOrderBook($coin);

                    if (!$orderbook) {
                        Log::info($orderbook);
                        $this->info("Invalid Orderbook, ignore opportunity");
                        continue;
                    }


                    $bid->bid_size = $orderbook['bid'];
                    $bid->bid = $orderbook['bid_rate'];

                    Coin::find($bid->id)
                        ->update([
                                'bid_size' => $bid->bid_size,
                                'bid' => $bid->bid
                            ]

                        );
                }

                //BUY:
                $this->info($coin . " {$bid->market} BID Orderbook Rate: {$bid->bid} Size: {$bid->bid_size}");

                $amount = bccomp($ask->ask_size, $bid->bid_size) < 0 ? $ask->ask_size : $bid->bid_size; // cat pot cumpara max

                Log::info("Max size $amount $coin");


                $cost = bcmul($amount, $ask->ask); //BTC->cat platesc

                if ($cost == 0) {
                    $this->warn("Nothing to buy or invalid data, ignore opportunity");
                    Log::warning("Nothing to buy or invalid data, ignore opportunity");
                    continue;
                }


                //buy fee
                $cost = bcadd($cost, bcmul($cost, $askMarket->maker_fee / 100));


                $this->warn("BUY $amount $ask->name with $cost BTC on $ask->market");
                Log::warning("BUY $amount $ask->name with $cost BTC");


                //SELL


                $return = bcmul($amount, $bid->bid);

                //sell fee
                $return = bcsub($return, bcmul($return, $askMarket->taker_fee / 100));


                $this->warn("SELL  $amount $coin and receive $return BTC on $bid->market");
                Log::warning("SELL  $amount $coin and receive $return BTC");

                $diff = bcsub($return, $cost);
                $profit = bcdiv($diff, $cost) * 100;


                if ($profit < $MIN_PROFIT) {
                    $this->warn("Too low profit $profit%($diff BTC), ignore opportunity ");
                    Log::warning("Too low profit $profit%($diff BTC), ignore opportunity ");
                    continue;
                }

                //Find duplicate opportunity: do not remove them YET, get the window open duration

                if (!$CACHE) {
                    $opportunity = Opportunity::where([
                        'coin' => $coin,
                        'ask_market' => $ask->market,
                        'bid_market' => $bid->market,

                    ])
                        ->where(function ($query) use ($ask, $bid) {
                            $query->where('ask_value', $ask->ask)
                                ->orWhere('bid_value', $bid->bid);
                        })
                        ->orderBy('updated_at', 'DESC')
                        ->first();

                    if (!is_null($opportunity)) {
                        $this->warn("Existing opportunity, ignore");
                        $opportunity->touch();
                        continue;
                    }


                    Opportunity::create([
                        'coin' => $coin,
                        'ask_market' => $ask->market,
                        'ask_value' => $ask->ask,
                        'exchange' => $amount,
                        'bid_market' => $bid->market,
                        'bid_value' => $bid->bid,
                        'return' => $diff,
                        'profit' => round($profit, 3),
                        'cost' => $cost


                    ]);
                }

                $this->error("WINNER! buy $amount $coin on {$ask->market} and sell on {$bid->market} Max gain: $diff BTC $profit %");
                Log::error("WINNER! buy $amount $coin on {$ask->market} and sell on {$bid->market} Max gain: $diff BTC $profit %");

                $sum = bcadd($sum, $diff);
                $total_funds = bcadd($total_funds, $cost);

                //simulate transaction
                $askWallet = $askMarket->wallet('BTC');
                $bidWallet = $bidMarket->wallet($coin);
//

                $exposure = ($FULL_EXPOSURE == true) ? $askWallet->balance : min($MAX_EXPOSURE, $askWallet->balance);

                if (bccomp($exposure, $cost) < 0) {

                    if ($askWallet->balance == 0) {
                        $this->error("MISSED! NO BTC funds on $ask->market to buy $amount $coin");
                        Log::error("MISSED! NO BTC funds on $ask->market to buy $amount $coin");
                        continue;
                    }
                    //max BTC to pay
//                    $exposure = ($full_exposure == true) ? $askWallet->balance : min($max_exposure, $askWallet->balance);

                    $amount = bcdiv($exposure, bcmul($ask->ask, $askMarket->taker_fee/100+1));
                    $this->info("MAX amount $amount $coin to pay with $exposure BTC");

                }

                if (bccomp($bidWallet->balance, $amount) < 0) {

                    if ($bidWallet->balance == 0) {
                        $this->error("MISSED! NO $coin funds on $bid->market to sell $amount");
                        Log::error("MISSED! NO $coin funds on $bid->market to sell $amount");
                        continue;
                    }
                    $amount = $bidWallet->balance;
                    $this->info("Max available to sell $amount $coin");
                }

                $paid = $askMarket->buy($coin, $amount, $ask->ask);
                if (!$paid) {
                    $this->error("NOT PAID due insufficient BTC funds");
                    continue;
                }

                $received = $bidMarket->sell($coin, $amount, $bid->bid);
                $gain = bcsub($received, $paid);
                $usd_gain = bcmul($btc, $diff);
                $this->error("Real Gain $gain BTC ($usd_gain USD)");


            }
        }

        $usd = bcmul($sum, $btc);

        if ($total_funds > 0) {
            $margin = bcdiv($sum, $total_funds) * 100;

            $this->info("Total profit : $sum BTC (%$margin) => $usd USD");
            Log::info("Total profit : $sum BTC (%$margin) => $usd USD");
        }

    }
}
