<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TallyOrder;
use App\Jobs\SyncOrderToTallyJob;

class RetryFailedTallyOrders extends Command
{
    protected $signature = 'tally:retry-failed';

    protected $description = 'Retry failed tally orders';

    public function handle()
    {
        $orders = TallyOrder::where('sync_status', 'failed')
            ->orderBy('id')
            ->get();

        if ($orders->isEmpty()) {
            $this->info('No failed orders found.');
            return;
        }

        $this->info("Found {$orders->count()} failed orders. Retrying...");

        foreach ($orders as $order) {

            // Reset karo pehle
            $order->update([
                'sync_status' => 'pending',
                'retry_count' => 0,
                'last_error'  => null,
            ]);

            SyncOrderToTallyJob::dispatch($order->id);

            $this->line("  Queued: #{$order->order_number} (DB ID: {$order->id})");
        }

        $this->info('Done. Make sure queue worker is running:');
        $this->line('php artisan queue:work --tries=3 --timeout=120');
    }
}
