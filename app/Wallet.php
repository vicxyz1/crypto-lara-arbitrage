<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    protected $fillable = ['market', 'coin', 'address', 'additional_info' ,'balance'];


//    public function __construct(array $attributes = [])
//    {
//        parent::__construct($attributes);
//    }
}
