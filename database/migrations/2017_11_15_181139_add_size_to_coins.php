<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSizeToCoins extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('coins', 'ask_size')) {
            Schema::table('coins', function (Blueprint $table) {
                $table->decimal('ask_size', 26, 16)->default('0');
                $table->decimal('bid_size', 26, 16)->default('0');
            }
            );
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (!Schema::hasColumn('coins', 'ask_size')) {
            Schema::table('coins', function (Blueprint $table) {
                $table->dropColumn('ask_size');
                $table->dropColumn('bid_size');
            }
            );
        }
    }
}
