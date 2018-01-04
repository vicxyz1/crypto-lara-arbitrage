<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateNewTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('market');
            $table->string('wallet');
            $table->enum('type', array('sell', 'buy', 'deposit', 'withdraw'));
			$table->decimal('debit', 26,16)->default('0');
			$table->decimal('credit', 26,16)->default('0');
			$table->decimal('rate', 26,16);
			$table->decimal('fee', 26,16)->default('0');
			$table->decimal('btc', 26,16)->default('0');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transactions');
    }
}
