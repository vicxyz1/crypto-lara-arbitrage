<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTransactionsTable extends Migration
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
            $table->string('coin');
            // $this->error("WINNER! buy $coin on {$ask->market} and sell on {$bid->market} and win $return BTC Diff:  $diff BTC $profit %");
            $table->string('ask_market');
            $table->decimal('ask_value', 26,10);
            $table->decimal('exchange', 26,10)->default('0');
            $table->decimal('cost', 26, 16)->default('0');
            $table->string('bid_market');
            $table->decimal('bid_value', 26,10);
//            $table->integer('ask_id')->unsigned();;
//            $table->integer('bid_id')->unsigned();;
            $table->decimal('return',26,10);
            $table->decimal('profit', 5, 3);
            $table->timestamps();
            //nu pot face truncate
//            $table->foreign('ask_id')->references('id')->on('coins')->onDelete('cascade')->onUpdate('cascade');
//            $table->foreign('bid_id')->references('id')->on('coins')->onDelete('cascade')->onUpdate('cascade');

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
