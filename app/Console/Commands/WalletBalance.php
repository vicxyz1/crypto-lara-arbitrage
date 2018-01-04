<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Market;
use Carbon\Carbon;
use App\Opportunity;
use App\Wallet;
use App\Transaction;
use App\Coin;
use Illuminate\Support\Facades\Log;

class WalletBalance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:balance {--target=strategy} {period=yesterday} {--withdraw}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Balance funds between platforms';

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
        //take a variant final
        //find deviations from final form
        //excess convert to btc, except some coins like eth
        //withdraw excess btc ??


        //check initial state, try back tracing based on transactions.

        //calculeaza diferentele -/+ si ordoneaza
        //2 array-uri + si min
        //ia mereu de unde e mai mult si da unde e mai putin si tot repeta
        //o bucla ...

        //withdraw = %BTC


        $markets = Market::all()->pluck('name');

        $targetOption = $this->option('target');

        if ($targetOption == 'strategy') {


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


            $this->warn("Period $start- $end");


            $btc_target = Opportunity::selectRaw('ask_market as market, SUM(cost) as new')
                ->where('updated_at', '>', $start)
                ->where('updated_at', '<', $end)
                ->groupby('ask_market')
                ->pluck('new', 'market');


            $target = $btc_target->map(function ($item, $key) {
                return collect(['BTC' => $item]);
            });

        } else {

            $file = $targetOption;

            $target = json_decode(file_get_contents($file));

            $target = collect($target);
            $btc_target = $target->map(function ($item, $key) {
                return $item->BTC;
            });


        }

        $existing = Wallet::
        where('coin', 'BTC')
            ->pluck('balance', 'market');


        $total_target = $btc_target->sum(); //!TODO: bcadd ?
        $this->info("Total target BTC: $total_target");

        $total_existing = $existing->sum();

        $this->info("Total existing BTC: $total_existing");

        $this->info("Existing wallets");
        dump($existing);
        $this->info("Target BTC wallets");
        dump($btc_target);


        $diff = bcsub($total_target, $total_existing);
        if ($diff > 0) {
            $this->error("Insufficient funds. You need to deposit $diff BTC");
            return;
        }

        if ($diff < 0) {
            $diff = -1 * $diff;
            $this->warn("You can withdraw $diff BTC");
        }
        $this->info("Start balancing accounts");


        $deficit = collect();
        $excess = collect();
        foreach ($markets as $name) {

            if (!$btc_target->has($name)) {
                if ($existing[$name] > 0) {
                    $excess->push(['name' => $name, 'amount' => $existing[$name]]);
                }
                continue;
            }

            if (bccomp($existing[$name], $btc_target[$name]) > 0) {
                $excess->push(['name' => $name, 'amount' => bcsub($existing[$name], $btc_target[$name])]);
                continue;
            }

            if (bccomp($existing[$name], $btc_target[$name]) < 0) {
                $deficit->push(['name' => $name, 'amount' => bcsub($btc_target[$name], $existing[$name])]);
            }
        }


        while (count($deficit) && count($excess)) {

            $excess->sortby('amount')->reverse();
            $deficit->sortby('amount')->reverse();
            Log::info("Excess");
            Log::info($excess);
            Log::info("Deficit");
            Log::info($deficit);

            $requester = $deficit->shift();
            Log::info("requester");
            Log::info($requester);

            $sender = $excess->shift();
            Log::info("sender");
            Log::info($sender);

            $market = Market::factory($sender['name']);

            //necesar mai mare
            if (bccomp($requester['amount'], $sender['amount']) >= 0) {
                $this->info("Transfer from {$sender['name']} to {$requester['name']} {$sender['amount']} BTC");

                if (!$market->transfer('BTC', $sender['amount'], $requester['name'])) {
                    $this->warn("Transfer failed");
                }
                $requester['amount'] = bcsub($requester['amount'], $sender['amount']);
                if ($requester['amount'] > 0) $deficit->push($requester);
                continue;
            }

            $this->info("Transfer from {$sender['name']} to {$requester['name']} {$requester['amount']} BTC");
            if (!$market->transfer('BTC', $requester['amount'], $requester['name'])) {
                $this->warn("Transfer failed");
            }
            $sender['amount'] = bcsub($sender['amount'], $requester['amount']);
            $excess->push($sender);

        }

        $withdraw = $this->option('withdraw');

        if ( $withdraw) {
            //withdraw la sfarsit de tot
            $excess->each(function ($item) {
                if ($item['amount'] > 0) {
                    $market = Market::factory($item['name']);
                    $this->info("Withdraw from {$item['name']} {$item['amount']} BTC");
                    $market->withdraw('BTC', $item['amount']);
                }

            });
        }


        //!TODO: balance rest of the coins
        $this->info("BTC account balanced");

        $existing = Wallet::
        where('coin', 'BTC')
            ->pluck('balance', 'market');

        dump($existing);

        //update BTC wallet
        /*   foreach ($markets as $name) {

               $this->warn("Market $name");

               $market = Market::factory($name);


               $new_btc = Opportunity::
               where('updated_at', '>', $start)
                   ->where('updated_at', '<', $end)
                   ->where('ask_market', $name)
                   ->sum('cost');


               $this->info("New  $new_btc BTC");

               $wallet = $market->wallet('BTC');

               $target['BTC'][$name] = $new_btc;
               $current['BTC'][$name] = $wallet->balance;


               $diff[$name] = bcsub($wallet->balance, $new_btc);*/
//            $market->deposit('BTC', $total_btc);
        /*
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


    }*/


    }
}
