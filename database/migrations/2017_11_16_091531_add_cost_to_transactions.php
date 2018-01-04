<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCostToTransactions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('transactions', 'cost')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->decimal('cost', 26, 16)->default('0');
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
        if (!Schema::hasColumn('transactions', 'cost')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropColumn('cost');
            }
            );
        }
    }
}
