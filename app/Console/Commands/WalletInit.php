<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Opportunity;
use App\Wallet;
use App\Market;
use App\Transaction;
use App\Coin;

class WalletInit extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:init {period=all} {--reset}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        /*
        * STRATEGY ONE
       * portfolio management
         *
         * cat BTC as avea nevoie  ca sa pot maximiza totul bazat pe
       *
       *
       */
        $markets = Market::all()->pluck('name');

        $period = $this->argument('period');

        switch ($period) {

            case 'yesterday':
                $start = Carbon::yesterday();
                break;
            case 'today':
                $start = Carbon::today();//date("Y-m-d 00:00:00");
                break;
            case 'hour':
                $start = Carbon::now()->subHour();
                break;
            case '10m':
                $start = Carbon::now()->subMinutes(10);
                break;
            case 'all':
                $start = Opportunity::min('created_at');
                break;
            default:
                $start = Carbon::createFromFormat('Y-m-d', $period);

        }
        $end = Carbon::now();


        if ($this->option('reset')) {
            Wallet::truncate();
            Transaction::truncate();
        }

        $this->warn("Period $start- $end");
        //update BTC wallet
        foreach ($markets as $name) {

            $this->warn("Market $name");

            $market = Market::factory($name);


            $total_btc = Opportunity::
            where('updated_at', '>', $start)
                ->where('updated_at', '<', $end)
                ->where('ask_market', $name)
                ->sum('cost');


            $this->info("Deposit $total_btc BTC");
            $market->deposit('BTC', $total_btc);

            $coins = Opportunity::selectRaw('coin, 
                    COUNT(coin) as total, 
                    AVG(profit) as avg_margin,
                    SUM(`return`) as profit,
                    MAX(profit) AS max_margin,
                    SUM(cost) AS cost, 
                    MAX(exchange) AS max_amount')
                ->where('updated_at', '>', $start)
                ->where('updated_at', '<', $end)
                ->where('bid_market', $name)
                ->groupby('coin')
                ->orderby('coin')
                ->get();


            foreach ($coins as $coin) {

                //!TODO: real time?
                $rate =  Coin::
                where('name', $coin->coin)
                    ->where('market', $name)
                    ->value('last');


                $this->info("BUY $coin->max_amount $coin->coin at last rate $rate");
                $market->buy($coin->coin, $coin->max_amount, $rate);

            }


        }


    }
}
