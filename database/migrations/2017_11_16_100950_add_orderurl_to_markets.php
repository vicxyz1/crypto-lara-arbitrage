<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddOrderurlToMarkets extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('markets', 'orderbook_url')) {
            Schema::table('markets', function (Blueprint $table) {
                $table->string('orderbook_url');
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
        if (Schema::hasColumn('markets', 'orderbook_url')) {
            Schema::table('markets', function (Blueprint $table) {
                $table->dropColumn('orderbook_url');
            }
            );
        }
    }
}
