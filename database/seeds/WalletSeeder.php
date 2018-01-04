<?php

use Illuminate\Database\Seeder;
use App\Wallet;
use Illuminate\Support\Facades\Log;
use App\Transaction;

class WalletSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        if (!file_exists(config_path("wallets.json"))) {

            throw new Exception("Seeding with wallets.json not found");

        }



        $wallets = json_decode(file_get_contents(config_path("wallets.json")));

        Wallet::truncate();
        Transaction::truncate();

        foreach ($wallets as $name => $wallet) {

            $className = "\\App\\Markets\\$name";

            $market = new $className();

            $market->getCurrencies();

            foreach ($wallet as $coin => $amount) {
                $market->deposit($coin, $amount);

            }


        }


    }
}
