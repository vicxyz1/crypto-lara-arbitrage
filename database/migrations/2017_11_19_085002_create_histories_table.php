<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateHistoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('histories', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('market');
            $table->decimal('ask', 26,10);
            $table->decimal('bid', 26,10);
            $table->decimal('last', 26,10);
            $table->decimal('ask_size', 26, 16)->default('0');
            $table->decimal('bid_size', 26, 16)->default('0');
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
        Schema::dropIfExists('histories');
    }
}
