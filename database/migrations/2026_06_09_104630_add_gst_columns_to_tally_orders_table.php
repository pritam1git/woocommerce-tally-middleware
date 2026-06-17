<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tally_orders', function (Blueprint $table) {

            /*
            |--------------------------------------------------------------------------
            | GST
            |--------------------------------------------------------------------------
            */

            $table->decimal(
                'tax_amount',
                10,
                2
            )->default(0);

            $table->decimal(
                'shipping_tax',
                10,
                2
            )->default(0);

            $table->string(
                'gst_type'
            )->nullable();

            /*
            |--------------------------------------------------------------------------
            | TALLY TRACKING
            |--------------------------------------------------------------------------
            */

            $table->string(
                'voucher_number'
            )->nullable();

            $table->timestamp(
                'queued_at'
            )->nullable();

            /*
            |--------------------------------------------------------------------------
            | PERFORMANCE
            |--------------------------------------------------------------------------
            */

            $table->index('sync_status');

            $table->index('order_number');
        });
    }

    public function down(): void
    {
        Schema::table('tally_orders', function (Blueprint $table) {

            $table->dropColumn([

                'tax_amount',

                'shipping_tax',

                'gst_type',

                'voucher_number',

                'queued_at',
            ]);

            $table->dropIndex([
                'sync_status'
            ]);

            $table->dropIndex([
                'order_number'
            ]);
        });
    }
};