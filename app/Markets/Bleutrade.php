<?php
/**
 * Created by PhpStorm.
 * User: Dell
 * Date: 11/11/2017
 * Time: 1:13 PM
 */

namespace App\Markets;

use App\Coin;
use App\Market;

class Bleutrade extends Market
{
    protected $name = 'Bleutrade';
    protected $base_url = 'https://bleutrade.com/api/v2/public/';

    /**
     * Overrides for result
     * @param $method
     * @param array $params
     * @return bool
     */

    protected function request($method, $params = [])
    {
        $result = parent::request($method, $params);
        if ($result && $result->success) {
            return $result->result;
        }
        return false;
    }

    /**
     * https://bleutrade.com/api/v2/public/getorderbook?market=OK_BTC&type=ALL&depth=3
     * get quantity for ask and sell in orderbook
     * @param $coin
     * @return ['ask', 'bid']
     */
    public function getOrderbook($coin)
    {
        //get orderbook

        $orderbook = $this->request('getorderbook', [
            'market' => $coin . "_BTC",
            'type' => 'ALL',
            'depth' => 3
        ]);

        //!TODO: to match the rate
        if (empty($orderbook->buy)) {
            return false;
        }

        if (empty($orderbook->sell)) {
            return false;
        }

        $buy = $orderbook->buy[0];
        $sell = $orderbook->sell[0];


        $orderbook = [
            'bid' => $buy->Quantity,
            'ask' => $sell->Quantity,
            'bid_rate' => $buy->Rate,
            'ask_rate' => $sell->Rate,
        ];

        return $orderbook;

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

                if (strpos($coin->MarketName, 'BTC') === false) {
                    continue;
                }
                $symbol = str_replace('_BTC', '', $coin->MarketName);

                Coin::create([
                    'market' => $this->name,
                    'name' => $symbol,
                    'ask' => $coin->Ask,
                    'bid' => $coin->Bid,
                    'last' => $coin->Last,
                ]);
            }
        return true;

    }

    public function getCurrencies()
    {
        if (!is_null($this->coins)) {
            return $this->coins;
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
        return array_keys($coins);
    }


    /**
     * get last price
     * @param $coin
     * @return bool
     */
    public function getLastPrice($coin)
    {
        if (!$this->isSupported($coin))
            return false;
        $ticker = $this->request('getticker', [
            'market' => $coin . "_BTC",
        ]);

        if (!isset($ticker[0]))
            return false;
        $ticker = $ticker[0];

        return $ticker->Last;
    }
}