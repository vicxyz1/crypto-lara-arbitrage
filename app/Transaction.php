<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model 
{

    protected $table = 'transactions';
    public $timestamps = true;
    protected $fillable = ['market', 'wallet', 'type', 'debit', 'credit', 'rate', 'fee', 'btc'];

}