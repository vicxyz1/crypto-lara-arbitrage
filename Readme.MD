
Crypto Lara Arbitrage
--- 

Implements basic arbitrage algorithm between crypto exchanges platforms. Currently supported platforms: Bittrex, Poloniex, Bleutrade and Kraken.


## Features



## Disclaimer
   
   This software is for educational purposes only. __Do not risk money which you are afraid to lose.__ USE THE SOFTWARE AT YOUR OWN RISK. THE AUTHORS AND ALL AFFILIATES ASSUME NO RESPONSIBILITY FOR YOUR TRADING RESULTS.

## INSTALATION

Install dependencies: 
    
    composer install

Configure settings:  

    cp .env.example .env

Create initial wallets funds

    cd config
    cp wallets.json.example wallets.json


Create database, run migrations and seeders:

    ./artisan migrate
    ./artisan db:seed
    
    ./artisan key:generate 

Install cron:


    * * * * * php /path/to/project/artisan schedule:run >> /dev/null 2>&1


## How it works



## Usage

       
## Contributing

Feel free to contribute, but please discuss the feature in a issue or contact me before a PR 
    

### TODO


* use Money library & swap instead of bcmath
    https://github.com/moneyphp/money


