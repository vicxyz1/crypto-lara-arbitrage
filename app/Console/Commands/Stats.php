<?php

namespace App\Console\Commands;

use App\Market;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Opportunity;
use Carbon\Carbon;

class Stats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'arbitrage:stats {period=all} {--limit=20}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Market statistics';

    /**
     * Create a new command instance.
     *
     * @return void
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
        //select sum(DISTINCT `return`) from transactions;

        $this->info("Current BTC/USD price: $btc USD");

        $period = $this->argument('period');

        $end = Carbon::now();
        $last = Opportunity::max('created_at'); //date("Y-m-d H:i:s");

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

        $total = DB::table('opportunities')
            ->where('updated_at', '>', $start)
            ->where('updated_at', '<', $end)
            ->count();

        $total_cost = DB::table('opportunities')
            ->where('updated_at', '>', $start)
            ->where('updated_at', '<', $end)
            ->sum('cost');

        $this->warn("Period: $period ($start - $end)");
        $this->warn("Total $total opportunities, last found on $last");

        $sum = DB::table('opportunities')
            ->where('updated_at', '>', $start)
            ->where('updated_at', '<', $end)
            ->distinct()->sum('return');

        $gain_usd = bcmul($btc, $sum, 2);
        $cost_usd = bcmul($btc, $total_cost, 2);

        $this->error("MAX TOTAL GAIN: $sum BTC ($gain_usd USD)  Cost: $total_cost BTC ($cost_usd USD)");

        if ($total_cost > 0) {
            $margin = bcdiv($sum, $total_cost, 4) * 100;
            $this->error("Profit: $margin%");
        }



        //select DISTINCT(coin) as , count(coin) as t, AVG(profit), AVG(`return`), SUM( DISTINCT `return`) from opportunities group by coin order by t desc;

        $coins = Opportunity::selectRaw('DISTINCT(coin), 
        COUNT(coin) as total, 
        AVG(profit) as avg_margin,
        SUM(`return`) as profit,
        MAX(profit) AS max_margin,
        SUM(cost) AS cost, 
        MAX(exchange) AS max_amount')
            ->where('updated_at', '>', $start)
            ->where('updated_at', '<', $end)
            ->groupby('coin')
            ->orderby('profit', 'DESC')
            ->get();

        $headers = ['Coin', 'Opportunities', 'AVG Margin (%)', 'MAX Margin(%)', 'Total Profit (BTC)', 'MAX Amount', 'Total Cost (BTC)', 'Margin %'];

        $data = [];
        foreach ($coins as $coin) {
            $data[] = [
                'coin' => $coin->coin,
                'total' => $coin->total,
                'avg_margin' => $coin->avg_margin,
                'max_margin' => $coin->max_margin,
                'total_profit' => $coin->profit,
                'max_amount' => $coin->max_amount . " {$coin->coin}",
                'cost' => $coin->cost,
                'margin' => ($coin->cost > 0) ? round($coin->profit * 100 / $coin->cost, 2) : 'n/a'


            ];
        }

        $this->table($headers, $data);

        $this->info("Markets to BUY most");
        //select ask_market, count(DISTINCT `return`) as a from opportunities group by ask_market order by a desc;
        $markets = Opportunity::selectRaw('ask_market AS market, count(DISTINCT `return`) AS total')
            ->where('updated_at', '>', $start)
            ->where('updated_at', '<', $end)
            ->groupBy('ask_market')
            ->orderBy('total', 'DESC')
            ->get();

        $headers = ['Market', 'Total', '%'];

        $data = [];

        $total = Opportunity::distinct()
            ->where('updated_at', '>', $start)
            ->where('updated_at', '<', $end)
            ->count('return');

        foreach ($markets as $market) {
            $data[] = [
                'market' => $market->market,
                'total' => $market->total,
                '%' => round($market->total / $total, 4) * 100


            ];
        }

        $this->table($headers, $data);

        $this->info("Markets to SELL most");
        $markets = Opportunity::selectRaw('bid_market AS market, count(DISTINCT `return`) AS total')
            ->where('updated_at', '>', $start)
            ->where('updated_at', '<', $end)
            ->groupBy('bid_market')
            ->orderBy('total', 'DESC')
            ->get();

        $headers = ['Market', 'Total', '%'];

        $data = [];

        $total = Opportunity::distinct()
            ->where('updated_at', '>', $start)
            ->where('updated_at', '<', $end)
            ->count('return');

        foreach ($markets as $market) {
            $data[] = [
                'market' => $market->market,
                'total' => $market->total,
                '%' => round($market->total / $total, 4) * 100


            ];
        }

        $this->table($headers, $data);


        //Display opportunities

        $limit = $this->option('limit');
        $opportunities = Opportunity::select('*', DB::raw('timediff(updated_at , created_at) AS duration'))
//            ->orderBy('duration', 'desc')
            ->orderBy('updated_at', 'desc')
            ->where('updated_at', '>', $start)
            ->where('updated_at', '<', $end)
            ->limit($limit)
            ->get();


        if (count($opportunities)) {
            $funds = env('INITIAL_DEPOSIT');

            $max_open = $opportunities[0];
//            $this->info("Max open window $max_open->duration for $max_open->coin on $max_open->ask_market <> $max_open->bid_market");

            $headers = ['COIN', 'BUY_ON', 'AMOUNT', 'PAID (BTC)', 'SELL_ON', 'PROFIT (BTC)', 'MARGIN (%)', 'LAST UPDATED'];
            $data = [];

            $this->info("Last $limit opportunities:");
            foreach ($opportunities as $opportunity) {
                $data[] = [
                    $opportunity->coin,
                    $opportunity->ask_market,
                    $opportunity->exchange,
                    $opportunity->cost,
                    $opportunity->bid_market,
                    $opportunity->return,
                    $opportunity->profit,
                    $opportunity->updated_at,


                ];

//                $this->warn("BUY $transaction->exchange $transaction->coin on $transaction->ask_market with $funds BTC");
//                $this->warn("SELL  $transaction->exchange $transaction->coin and gain $transaction->return BTC");

            }

            $this->table($headers, $data);

        }


    }


}
