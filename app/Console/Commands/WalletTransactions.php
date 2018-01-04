<?php

namespace App\Console\Commands;

use App\Transaction;
use App\Market;
use Carbon\Carbon;
use Illuminate\Console\Command;

class WalletTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:transactions {period=all} {--market=all} {--limit=20} {--all} {--coin=all}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display summary of transactions and P&L report';

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
        $btc = Market::getPrice('BTC');

        $this->info("Current BTC/USD price: $btc USD");

        $period = $this->argument('period');
        $market = $this->option("market");

        $all = $this->option('all');

        $coin = $this->option('coin');

        $end = Carbon::now();


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
                $start = Transaction::min('created_at');
                break;
            default:
                $start = Carbon::createFromFormat('Y-m-d 0:0:0', $period);

        }

        $select = Transaction::
        orderBy('created_at', 'desc')
            ->where('created_at', '>=', $start)
            ->where('created_at', '<=', $end);

        if (!$all) {
            $select->where(function ($query) {
                $query->where('type', 'sell')
                    ->orWhere('type', 'buy');
            });
        }

        if ($coin != 'all') {

            $coin = strtoupper($coin);

            $select->where('wallet', $coin);
        }

        if ($market != 'all') $select->where('market', $market);

        $count = $select
            ->count();

        $sum = $select
            ->sum('btc');

        $fee = $select
            ->sum('fee');

        $last = Transaction::max('created_at'); //date("Y-m-d H:i:s");

        $this->warn("Period: $period ($start - $end)");
        $this->warn("Total $count transactions, last found on $last");


        $gain_usd = bcmul($btc, $sum, 2);
        $fee_usd = bcmul($btc, $fee, 2);

        $this->error("Transactions Profit/Loss: $sum BTC ($gain_usd USD) ");
        $this->warn("Total fees: $fee BTC ($fee_usd USD)");


        //Summary per wallet
        $selectWallet = Transaction::selectRaw("wallet, SUM(credit) as credit, SUM(debit) as debit, SUM(btc) as BTC")
            ->where('created_at', '>', $start)
            ->where('created_at', '<', $end);


        if ($market != 'all') $selectWallet->where('market', $market);

        $transactions = $selectWallet->groupBy('wallet')
            ->orderBy('wallet', 'asc')
            ->get();


        $price = Market::getPrice('BTC');
        $headers = ['WALLET', 'BUY VOLUME', 'SELL VOLUME', 'BTC', 'USD'];
        if (count($transactions)) {
            $this->info("Market: $market");
            $data = [];
            foreach ($transactions as $transaction) {
                $data[] = [
                    $transaction->wallet,
                    $transaction->credit,
                    $transaction->debit,
                    $transaction->BTC,
                    bcmul($transaction->BTC, $price)
                ];


            }

            $this->table($headers, $data);
        }


        //display transactions
        $limit = $this->option('limit');

        $transactions = $select->limit($limit)
            ->get();

        if (count($transactions)) {


            $headers = ['DATE', 'MARKET', 'WALLET', 'TYPE', 'AMOUNT', 'RATE', 'FEE', 'BTC'];
            $data = [];

            $this->info("Last $limit transactions:");
            foreach ($transactions as $transaction) {
                $data[] = [
                    $transaction->created_at,
                    $transaction->market,
                    $transaction->wallet,
                    $transaction->type,
                    max($transaction->debit, $transaction->credit),
                    $transaction->rate,
                    $transaction->fee,
                    $transaction->btc


                ];


            }

            $this->table($headers, $data);
        }

    }
}
