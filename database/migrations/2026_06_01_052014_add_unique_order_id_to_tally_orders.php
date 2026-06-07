<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tally_orders', function (Blueprint $table) {

            $table->unique(
                'woocommerce_order_id',
                'tally_orders_wc_order_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('tally_orders', function (Blueprint $table) {

            $table->dropUnique(
                'tally_orders_wc_order_unique'
            );
        });
    }
};