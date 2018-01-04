<?php
/**
 * Created by PhpStorm.
 * User: Dell
 * Date: 11/11/2017
 * Time: 1:41 PM
 */

namespace App\Markets;

use App\Market;
use GuzzleHttp\Client;
use App\Coin;

class Kraken extends Market
{

    protected $name = 'Kraken';
    protected $base_url = 'https://api.kraken.com/0/public/';


    protected function request($method, $params = [])
    {


        $result = parent::request($method, $params);
        if ($result && empty($result->error)) {
            return $result->result;
        }

        return false;

    }

    /**
     * get quantity for ask and sell in orderbook
     * https://api.kraken.com/0/public/Depth?pair=%sXBT&count=3
     * @param $coin
     * @return ['ask', 'bid']
     */
    public function getOrderbook($coin)
    {
        //get orderbook

        if ($coin == 'DOGE') $coin = 'XDG';
        if ($coin == 'BCC') $coin = 'BCH';

        $orderbook = $this->request('Depth', [
            'pair' => $coin . 'XBT',
            'count' => 3
        ]);


        //!FIXE: o metoda cu foreach ar fi...
        $orderbook = each($orderbook);
        $orderbook = $orderbook['value'];

        //!TODO: to match the rate
        if (empty($orderbook->bids)) {
            return false;
        }

        if (empty($orderbook->asks)) {
            return false;
        }

        $buy = $orderbook->bids[0];
        $sell = $orderbook->asks[0];


        $quantity = [
            'bid' => $buy[1],
            'ask' => $sell[1],
            'bid_rate' => $buy[0],
            'ask_rate' => $sell[0],
        ];

        return $quantity;

    }

    /**
     * https://api.kraken.com/0/public/Ticker?pair=BCHXBT,DASHXBT,EOSXBT,GNOXBT,ETCXBT,ETHXBT,ICNXBT,LTCXBT,MLNXBT,REPXBT,XDGXBT,XLMXBT,XMRXBT,XRPXBT,ZECXBT
     */
    public function getData()
    {

        $currencies = $this->request('Ticker', [
            'pair' => 'BCHXBT,DASHXBT,EOSXBT,GNOXBT,ETCXBT,ETHXBT,ICNXBT,LTCXBT,MLNXBT,REPXBT,XDGXBT,XLMXBT,XMRXBT,XRPXBT,ZECXBT'
        ]);

        if (!$currencies)
            return false;

        if ($currencies)
            foreach ($currencies as $pair => $coin) {

                if (!preg_match('/BCH|DASH|EOS|GNO|ETC|ETH|ICN|LTC|MLN|REP|XDG|XLM|XMR|XRP|ZEC/', $pair, $match)) {
                    continue;
                }

                $symbol = $match[0];

                //doge
                if ($symbol == 'XDG') $symbol = 'DOGE';
                //bitcoin cash
                if ($symbol == 'BCH') $symbol = 'BCC';


                Coin::create([
                    'market' => $this->name,
                    'name' => $symbol,
                    'ask' => $coin->a[0],
                    'bid' => $coin->b[0],
                    'last' => $coin->c[0],
                ]);
            }

            return true;


    }

    /**
     * Get currencies in standard format
     * @return array
     */
    public function getCurrencies()
    {
        //standard format COIN:FEE
        //https://support.kraken.com/hc/en-us/articles/201893608-What-are-the-withdrawal-fees-
        $this->coins = [
            'BTC'=>0.001,
            'BCC'=>0.001,
            'DASH'=>0.005,
            'EOS'=>0.50000,
            'GNO'=>0.01,
            'ETC'=>0.005,
            'ETH'=>0.005,
            'ICN'=> 0.2,
            'LTC'=>0.02,
            'MLN'=>0.003,
            'REP'=>0.01,
            'DOGE'=>2.00,
            'XLM'=>0.00002,
            'XMR'=>0.05,
            'XRP'=>0.02,
            'ZEC'=>0.00010
        ];

        //USDT=>5

        return array_keys($this->coins);
    }

    /**
     *  trial to get supported coins
     * @deprecated USE getCurrencies()
     * @return array
     */
    public function getCoins()
    {
        $url = 'https://api.kraken.com/0/public/Assets';

        $client = new Client();
        $response = $client->get($url);

        $result = json_decode($response->getBody());

        if (empty($result->error)) {
            $currencies = $result->result;
        }

        $coins = [];
        foreach ($currencies as $symbol => $coin) {

            //elimin fiat money
            if (substr($symbol, 0, 1) == 'Z') {
                continue;
            }

            if ($coin->altname == 'XDG') {
                $coin->altname = 'DOGE';
            }

            $coins[] = $coin->altname;

        }


        return $coins;
    }

    /**
     *  'BCHXBT,DASHXBT,EOSXBT,GNOXBT,ETCXBT,ETHXBT,ICNXBT,LTCXBT,MLNXBT,REPXBT,XDGXBT,XLMXBT,XMRXBT,XRPXBT,ZECXBT'
     * @param $coin
     * @return bool
     */
    public function getLastPrice($coin)
    {

        if (!$this->isSupported($coin))
            return false;

        if ($coin == 'DOGE') $coin = 'XDG';
        if ($coin == 'BCC') $coin = 'BCH';

        $pair = $coin . "XBT";

        $currencies = $this->request('Ticker', [
            'pair' => $pair
        ]);

        if (!$currencies)
            return false;


        if ($currencies)
            foreach ($currencies as $pair => $coin) {
                return $coin->c[0];
            }

        return true;


    }


}