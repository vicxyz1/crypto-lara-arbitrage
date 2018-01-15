<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddStatusToMarketsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('markets', 'active')) {
            Schema::table('markets', function (Blueprint $table) {
                $table->boolean('active')->default(true);
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
        if (Schema::hasColumn('markets', 'active')) {
            Schema::table('markets', function (Blueprint $table) {
                $table->dropColumn('active');

            }
            );
        }
    }
}
