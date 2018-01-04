<?php

namespace App\Console\Commands;

use App\Market;
use Illuminate\Console\Command;
use App\Coin;

class Trader extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trader {action} {amount} {coin} {market} ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make basic trading operations';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $action = $this->argument('action');
        $market_name = $this->argument('market');
        $market = Market::factory($market_name);
        $coin = strtoupper($this->argument('coin'));
        $amount = $this->argument('amount');
        $this->info(strtoupper($action) . " $amount $coin on $market_name");

        bcscale(16);
        switch ($action) {
            case 'deposit':
                $market->deposit($coin, $amount);
                break;
            case 'withdraw':
                $amount = $market->withdraw($coin, $amount);
                if (!$amount) {
                    $this->warn($market->getError());
                    break;
                }

                $this->info("Received $amount $coin");

                break;
            case 'buy':
                $rate = $market->getLastPrice($coin);

                if (!$rate) {
                    $this->warn("No rate found");
                    break;
                }
                $this->info("Rate: $rate");

                $outcome = $market->buy($coin, $amount, $rate);
                $this->info("Paid $outcome BTC");
                break;
            case 'sell':

                $rate = $market->getLastPrice($coin);
                if (!$rate) {
                    $this->warn("No rate found");
                    break;
                }
                $this->info("Rate: $rate");

                $income = $market->sell($coin, $amount, $rate);
                if (!$income) {
                    $this->warn($market->getError());
                    break;
                }
                $this->info("Received $income BTC");

                break;


            case 'buy_max':
                $this->info("BUY $coin  with $amount BTC");

                $price = $market->getLastPrice($coin);
                if (!$price) {
                    $this->warn("No price found");
                    break;
                }
                $this->info("Rate: $price");


                //calculate how many units of $coin
                $units = bcdiv($amount, bcmul($price, (1 + $market->maker_fee / 100)));

                $this->info("MAX: $units");

                $paid = $market->buy($coin, $units, $price);

                if (!$paid) {
                    $this->warn($market->getError());
                    break;
                }

                $this->info("Received $units $coin for $paid BTC");

                break;


            default:
                $this->warn("$action not implemented");
        }


    }
}
