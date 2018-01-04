<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Coin extends Model
{
    //

    protected $fillable = ['market', 'name', 'ask', 'bid', 'last', 'bid_size', 'ask_size'];
}
