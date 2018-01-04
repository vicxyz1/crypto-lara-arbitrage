<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddFeeToMarkets extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        if (!Schema::hasColumn('markets', 'fee')) {
            Schema::table('markets', function (Blueprint $table) {
                $table->decimal('fee', 4, 2)->default('0');
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
        if (Schema::hasColumn('markets', 'fee')) {
            Schema::table('markets', function (Blueprint $table) {
                $table->dropColumn('fee');
            }
            );
        }
    }
}
