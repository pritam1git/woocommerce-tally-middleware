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
        $orders = TallyOrder::whereIn('sync_status', [
            'failed',
            'pending'
        ])->get();

        foreach ($orders as $order) {

            SyncOrderToTallyJob::dispatch($order->id);
        }

        $this->info('Retry jobs dispatched successfully');
    }
}
