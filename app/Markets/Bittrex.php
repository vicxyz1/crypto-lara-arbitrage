<?php
/**
 * Created by PhpStorm.
 * User: Dell
 * Date: 11/11/2017
 * Time: 1:11 PM
 */

namespace App\Markets;

use App\Coin;
use App\Market;
use Illuminate\Support\Collection;

class Bittrex extends Market
{

    protected $name = 'Bittrex';
    protected $base_url = 'https://bittrex.com/api/v1.1/public/';



    protected function request($method, $params = [])
    {

        $result = parent::request($method, $params);
        if ($result && $result->success) {
            return $result->result;
        }
        return false;
    }

    /**
     * get supported Currencies
     * @return array|mixed
     */
    public function getCurrencies($full = false)
    {

        if (is_array($this->coins)) {
            return array_keys($this->coins);
        }
        $currencies = $this->request('getcurrencies');

        if (!$currencies)
            return false;

        foreach ($currencies as $currency) {

            if (!$currency->IsActive)
                continue;

            $coins[$currency->Currency] = $currency->TxFee;
        }

        $this->coins = $coins;
        return array_keys($this->coins);


    }




    /**
     * get quantity for ask and sell in orderbook https://bittrex.com/api/v1.1/public/getorderbook?market=BTC-%s&type=both
     * @param $coin
     * @return ['ask', 'bid']
     */
    public function getOrderbook($coin)
    {
        //get orderbook

        $orderbook = $this->request('getorderbook', [
            'market' => "BTC-" . $coin,
            'type' => 'both'
        ]);

        //!TODO: to match the rate
        if (!$orderbook || !is_array($orderbook->buy)) {
            return false;
        }


        $buy = $orderbook->buy[0];
        $sell = $orderbook->sell[0];

        $order = [
            'bid' => $buy->Quantity,
            'ask' => $sell->Quantity,
            'bid_rate' => $buy->Rate,
            'ask_rate' => $sell->Rate,

        ];

        return $order;

    }

    /**
     * @return bool
     */
    public function getData()
    {

        $coins = $this->request('getmarketsummaries');

        if (!$coins)
            return false;


        if (is_array($coins))
            foreach ($coins as $coin) {

                if (strpos($coin->MarketName, 'BTC-') === false) {
                    continue;
                }
                $symbol = str_replace('BTC-', '', $coin->MarketName);

                Coin::create([
                    'market' => $this->name,
                    'name' => $symbol,
                    'ask' => $coin->Ask,
                    'bid' => $coin->Bid,
                    'last' => $coin->Last
                ]);


            }

            return true;

    }

    /**
     * https://bittrex.com/api/v1.1/public/getticker
     *
     * @param $coin
     * @return  false on fail
     */
    public function getLastPrice($coin)
    {
        if (!$this->isSupported($coin))
            return false;
        $ticker = $this->request('getticker', [
            'market' => "BTC-" . $coin
        ]);

        return $ticker->Last;
    }


}