<?php
/**
 * Created by PhpStorm.
 * User: Dell
 * Date: 11/17/2017
 * Time: 12:39 PM
 */

namespace App;

use GuzzleHttp\Client;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class Market extends Model
{
    protected $fillable = ['name', 'url', 'maker_fee', 'taker_fee'];
    protected $table = 'markets';

    protected $error;
    protected $coins;

    /**
     * @param $market
     * @return Market
     */
    public static function factory($market)
    {
        $market = ucfirst($market);

        if (false === self::all()->pluck('name')->search($market)) {
            throw new \InvalidArgumentException(sprintf('Invalid market "%s" provided', $market));
        }

        $marketClass = '\\App\\Markets\\' . $market;

        $instance = new $marketClass();

        $model = Market::where('name', $market)->first();
        $instance->fill($model->toArray());
        $instance->getCurrencies();
        return $instance;
    }

    public static function getPrice($coin, $fiat = 'USD')
    {
        if (Cache::has("price:$coin:$fiat")) {
            return Cache::get("price:$coin:$fiat");
        }

        $url = "https://min-api.cryptocompare.com/data/price?fsym=$coin&tsyms=$fiat";
        $client = new Client();
        $response = $client->get($url);
        $result = json_decode($response->getBody());

        $price = $result->$fiat;

        // TODO: customize TTL?
        Cache::set("price:$coin:$fiat", $price, 10);

        return sprintf("%.10f", $price);
    }

    protected function request($method, $params = [])
    {
        $url = $this->base_url . $method;
        try {
            $client = new Client();

            $response = $client->get($url, ['query' => $params, 'connect_timeout' => 3]);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return false;
        }
        return json_decode(preg_replace('/("\w+"):(\d+(\.\d+)?)/', '\\1:"\\2"', $response->getBody()));
    }

    /**
     * Create or update the amount of the wallet
     * @param $coin
     * @param null $amount
     * @return bool|wallet
     */
    public function wallet($coin, $amount = NULL)
    {

        if (!$this->isSupported($coin)) {
            Log::warning("$coin is not supported by $this->name, can not create wallet");
            return false;
        }

        $wallet = Wallet::firstOrCreate(['market' => $this->name, 'coin' => $coin]);

        if (!is_null($amount)) {

            Log::info("$this->name WALLET set to  $amount $coin");

            $wallet->update([
                    'balance' => $amount
                ]
            );

        }

        return $wallet;
    }


    /**
     * @deprecated
     * @param $buy
     * @param $sell
     * @param $rate
     * @param $amount
     * @return bool|mixed|string
     */
    public function exchange($buy, $sell, $amount, $rate, $fee)
    {
        Log::info("EXCHANGE $buy -> $sell for $amount $buy with rate $rate $sell fee $fee%");

        $buyWallet = $this->wallet($buy);
        $sellWallet = $this->wallet($sell);

        $cost = bcmul($rate, $amount);
        Log::info("Cost without fee: $cost");

        $feeAmount = bcmul($cost, $fee / 100);
        Log::info("Fee: $feeAmount ");

        $cost = bcadd($cost, $feeAmount);

        $sellBalance = $sellWallet->balance;
        $buyBalance = $buyWallet->balance;

        if (bccomp($sellBalance, $cost) < 0) {
            $this->error = "Insufficent $cost $sell funds on $this->name to buy $cost $buy.  Current balance: {$sellWallet->balance}";
            Log::warning($this->error);
            return false;
        }

        $sellWallet->balance = bcsub($sellWallet->balance, $cost);
        Log::warning("$this->name WALLET $sell Before: $sellBalance SELL $cost After: {$sellWallet->balance}");
        $buyWallet->balance = bcadd($buyBalance, $amount);
        Log::warning("$this->name WALLET $buy Before: $buyBalance BUY $amount After: {$buyWallet->balance}");


        $buyWallet->save();
        $sellWallet->save();

        $transaction = new Transaction();
        $transaction->market = $this->name;

        if ($buy == 'BTC') {
            $transaction->type = 'sell';
            $transaction->debit = $amount;
            $transaction->wallet = $sell;
        } else {

            $transaction->type = 'buy';
            $transaction->credit = $amount;
            $transaction->wallet = $buy;
            $cost = -$cost;
        }

        $transaction->rate = $rate;
        $transaction->fee = abs($feeAmount);
        $transaction->btc = $cost;

        $transaction->save();


        return abs($cost);
    }


    /**
     *
     * BUY $amount $coin with BTC for rate $price
     * @param $coin
     * @param $units
     * @param $price
     * @return bool|mixed|string
     */
    public function buy($coin, $units, $price)
    {


        $fee = $this->maker_fee;
        Log::info("BUY $units $coin with rate $price fee $fee%");

        $buyWallet = $this->wallet($coin);
        $sellWallet = $this->wallet('BTC');

        $feeAmount = bcmul(bcmul($price , $units), $fee/100);
        Log::info("Fee: $feeAmount");
        $cost = bcround(bcmul(bcmul($price, (1+$fee/100)), $units, 17), 16);
        Log::info("Total Cost: $cost BTC");

        $sellBalance = $sellWallet->balance;
        $buyBalance = $buyWallet->balance;

        if (bccomp($sellBalance, $cost) < 0) {
            $this->error = "Insufficient $cost BTC funds on $this->name to buy $units $coin.  Current balance:$sellBalance";
            Log::warning($this->error);
            return false;
        }

        $sellWallet->balance = bcsub($sellWallet->balance, $cost);
        Log::warning("$this->name WALLET BTC Before: $sellBalance SELL $cost After: {$sellWallet->balance}");
        $buyWallet->balance = bcadd($buyBalance, $units);
        Log::warning("$this->name WALLET $coin Before: $buyBalance BUY $units After: {$buyWallet->balance}");


        $buyWallet->save();
        $sellWallet->save();

        $transaction = new Transaction();
        $transaction->market = $this->name;
        $transaction->type = 'buy';
        $transaction->credit = $units;
        $transaction->wallet = $coin;
        $transaction->rate = $price;
        $transaction->fee = $feeAmount;
        $transaction->btc = "-$cost";
        $transaction->save();
        return $cost;

    }

    public function sell($coin, $amount, $rate)
    {


        $fee = $this->taker_fee;

        Log::info("Sell $amount $coin with rate $rate fee $fee%");

        $buyWallet = $this->wallet('BTC');
        $sellWallet = $this->wallet($coin);

        $sellBalance = $sellWallet->balance;
        $buyBalance = $buyWallet->balance;

        if (bccomp($sellBalance, $amount) < 0) {
            $this->error = "Insufficent $amount $coin funds on $this->name to sell. Current balance: $sellBalance $coin";
            Log::warning($this->error);
            return false;
        }

        //calc fee
        $cost = bcmul($rate, $amount);
        Log::info("Cost without fee: $cost");

        $feeAmount = bcmul($cost, $fee / 100);
        Log::info("Fee: $feeAmount ");

        $cost = bcsub($cost, $feeAmount);

        $sellWallet->balance = bcsub($sellWallet->balance, $amount);
        Log::warning("$this->name WALLET $coin Before: $sellBalance SELL $cost After: {$sellWallet->balance}");

        $buyWallet->balance = bcadd($buyBalance, $cost);
        Log::warning("$this->name WALLET $coin Before: $buyBalance BUY $amount After: {$buyWallet->balance}");

        $buyWallet->save();
        $sellWallet->save();

        //!TODO: rewrite
        $transaction = new Transaction();
        $transaction->market = $this->name;

        $transaction->type = 'sell';
        $transaction->debit = $amount;
        $transaction->wallet = $coin;
        $transaction->rate = $rate;
        $transaction->fee = abs($feeAmount);
        $transaction->btc = $cost;
        $transaction->save();
        return $cost;
    }

    /**
     * Deposit to wallet
     * @param $coin
     * @param $amount
     * @return
     */
    public function deposit($coin, $amount)
    {

        Log::info("DEPOSIT $amount $coin on $this->name");

        if ($amount <= 0) {
            return false;
        }

        $wallet = $this->wallet($coin);

        if (!$wallet)
            return false;
        $wallet->balance = bcadd($wallet->balance, $amount);
        $wallet->save();

        Transaction::create([
            'market' => $this->name,
            'wallet' => $coin,
            'type' => 'deposit',
            'credit' => $amount,
            'rate' => 0,
            'btc' => ($coin == 'BTC') ? "-$amount" : 0
        ]);

        return $wallet->balance;

    }

    /**
     * Withdraw from wallet, return amount - fee
     * @param $coin
     * @param $amount
     * @return bool|mixed|string
     */
    public function withdraw($coin, $amount)
    {

        Log::info("WITHDRAW $amount $coin on $this->name");

        if ($amount <= 0) {
            return false;
        }

        $wallet = $this->wallet($coin);

        if (!$wallet)
            return false;

        if (bccomp($wallet->balance, $amount) < 0) {
            $this->error = "Insufficient $coin funds to withdraw $amount. Current balance: {$wallet->balance}";
            Log::warning($this->error);
            return false;
        }

        $fee = $this->getWithdrawFee($coin);

        if (bccomp($amount, $fee) < 0) {
            $this->error = "Cannot withdraw less than the minimum fee $fee $coin";
            Log::warning($this->error);
            return false;
        }


        $wallet->balance = bcsub($wallet->balance, $amount);
        $wallet->save();




        Transaction::create([
            'market' => $this->name,
            'wallet' => $coin,
            'type' => 'withdraw',
            'debit' => $amount,
            'rate' => 0,
            'fee' => $fee,
            'btc' => ($coin == 'BTC') ? bcsub($amount,  $fee) : 0
        ]);

        return ($amount - $fee);

    }

    /**
     * check if currency is supported by the market
     * @param $coin
     * @return bool
     */
    public function isSupported($coin)
    {

        if (!$this->getCurrencies()) {
            Log::error("cannot get the currencies or not array");
            return false;
        }
        return isset($this->coins[$coin]);
    }

    /**
     * @param $coin
     * @return bool
     */
    public function getWithdrawFee($coin)
    {

        if (!$this->getCurrencies()) {
            Log::error("cannot get the currencies or not array");
            return false;
        }
        return $this->coins[$coin];
    }

    /**
     * transfer from different markets
     * @param $coin
     * @param $market_to
     * @return  bool
     */
    public function transfer($coin, $amount, $market_to, $include_fee = false)
    {


        $to = Market::factory($market_to);


        if ($include_fee == false) {
            $fee = $this->getWithdrawFee($coin);
            $amount = bcadd($fee, $amount);
        }


        $received = $this->withdraw($coin, $amount);

        if (!$received) {
            Log::warning("Transfer $coin $amount $this->name to $market_to failed");
            return false;
        }

        $to->deposit($coin, $received);

        return true;

    }

    /**
     * get last price
     * @param $coin
     * @return bool
     */
    public function getLastPrice($coin)
    {
        $rate = Coin::
        where('name', $coin)
            ->where('market', $this->name)
            ->value('last');


        if ($rate > 0) return $rate;

        Log::warning("No last price found for $coin");
        return false;

    }

    /**
     * @return mixed
     */
    public function getError()
    {
        return $this->error;
    }


    /**
     * @param string $coin
     * @return bool
     */
    public function getOrderBook($coin)
    {
        return false;
    }

    /**
     * @return bool
     */
    public function getData()
    {
        return false;
    }

    /**
     * @return bool
     */
    public function getCurrencies()
    {
        return false;
    }




}