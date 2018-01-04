<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class SeparateMakerTakerFees extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumns('markets', ['maker_fee', 'taker_fee'])) {
            Schema::table('markets', function (Blueprint $table) {
                $table->decimal('maker_fee', 4, 2)->default('0')->after('url');
                $table->renameColumn('fee', 'taker_fee');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumns('markets', ['maker_fee', 'taker_fee'])) {
            Schema::table('markets', function (Blueprint $table) {
                $table->dropColumn('maker_fee');
                $table->renameColumn('taker_fee', 'fee');
            });
        }
    }
}
