<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tally_orders', function (Blueprint $table) {

            $table->id();

            $table->string('woocommerce_order_id')->nullable();

            $table->string('order_number')->nullable();

            $table->longText('payload');

            $table->enum('sync_status', [
                'pending',
                'processing',
                'success',
                'failed'
            ])->default('pending');

            $table->integer('retry_count')->default(0);

            $table->text('last_error')->nullable();

            $table->timestamp('synced_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_orders');
    }
};
