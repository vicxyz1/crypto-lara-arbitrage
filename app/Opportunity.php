<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Opportunity extends Model
{
    protected $fillable = ['coin', 'return', 'profit','ask_market','ask_value', 'bid_market','bid_value', 'exchange', 'cost'];

    //

//    public function ask()
//    {
//        return $this->belongsTo('App\Coin');
//    }
//
//    public function bid()
//    {
//        return $this->belongsTo('App\Coin');
//    }
}
