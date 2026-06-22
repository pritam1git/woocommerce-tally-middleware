<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Support\Facades\Http;
use App\Models\TallyOrder;
use App\Http\Controllers\Controller;
use App\Jobs\SyncOrderToTallyJob;

class DashboardController extends Controller
{
    public function index()
    {
        /*
        |--------------------------------------------------------------------------
        | STATS
        |--------------------------------------------------------------------------
        */

        $todayOrders = TallyOrder::whereDate(
            'created_at',
            today()
        )->count();

        $successOrders = TallyOrder::where(
            'sync_status',
            'success'
        )->count();

        $failedOrders = TallyOrder::where(
            'sync_status',
            'failed'
        )->count();

        $pendingOrders = TallyOrder::whereIn(
            'sync_status',
            [
                'pending',
                'processing',
                'retrying'
            ]
        )->count();

        /*
        |--------------------------------------------------------------------------
        | STUCK PROCESSING
        |--------------------------------------------------------------------------
        */

        $stuckOrders = TallyOrder::where(
            'sync_status',
            'processing'
        )
        ->where(
            'updated_at',
            '<',
            now()->subMinutes(10)
        )
        ->count();

        /*
        |--------------------------------------------------------------------------
        | QUERY
        |--------------------------------------------------------------------------
        */

        $query = TallyOrder::query();

        /*
        |--------------------------------------------------------------------------
        | SEARCH
        |--------------------------------------------------------------------------
        */

        if (request()->filled('search')) {

            $search = trim(
                request('search')
            );

            $query->where(function ($q) use ($search) {

                $q->where(
                    'order_number',
                    'like',
                    "%{$search}%"
                )

                ->orWhere(
                    'customer_name',
                    'like',
                    "%{$search}%"
                )

                ->orWhere(
                    'sync_status',
                    'like',
                    "%{$search}%"
                );
            });
        }

        /*
        |--------------------------------------------------------------------------
        | SORTING
        |--------------------------------------------------------------------------
        */

        $allowedSorts = [

            'latest',
            'oldest',
            'amount_high',
            'amount_low',
        ];

        $sort = request('sort', 'latest');

        if (!in_array($sort, $allowedSorts)) {
            $sort = 'latest';
        }

        switch ($sort) {

            case 'oldest':

                $query->oldest();
                break;

            case 'amount_high':

                $query->orderBy('amount', 'desc');
                break;

            case 'amount_low':

                $query->orderBy('amount', 'asc');
                break;

            default:

                $query->latest();
                break;
        }

        /*
        |--------------------------------------------------------------------------
        | PAGINATION
        |--------------------------------------------------------------------------
        */

        $orders = $query
            ->paginate(20)
            ->withQueryString();

        $tallyConnected = false;

        try {

            $response = Http::timeout(3)
                ->get(config('tally.url'));

            $tallyConnected = true;

        } catch (\Throwable $e) {

            $tallyConnected = false;
        }

        return view('admin.dashboard', compact(

            'todayOrders',

            'successOrders',

            'failedOrders',

            'pendingOrders',

            'stuckOrders',

            'orders',

            'tallyConnected'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */

    public function show($id)
    {
        $order = TallyOrder::findOrFail($id);

        return view(
            'admin.order-show',
            compact('order')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */

    public function delete($id)
    {
        $order = TallyOrder::findOrFail($id);

        /*
        |--------------------------------------------------------------------------
        | PREVENT DELETE OF PROCESSING
        |--------------------------------------------------------------------------
        */

        if (
            in_array(
                $order->sync_status,
                ['processing']
            )
        ) {

            return back()->with(

                'error',

                'Processing order cannot be deleted.'
            );
        }

        $order->delete();

        return back()->with(

            'success',

            'Order deleted successfully.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | RETRY
    |--------------------------------------------------------------------------
    */

    public function retry($id)
    {
        $order = TallyOrder::findOrFail($id);

        /*
        |--------------------------------------------------------------------------
        | PREVENT SUCCESS RETRY
        |--------------------------------------------------------------------------
        */

        if ($order->sync_status === 'success') {

            return back()->with(

                'error',

                'Order already synced.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | PREVENT MULTIPLE RETRIES
        |--------------------------------------------------------------------------
        */

        if (
            in_array(
                $order->sync_status,
                ['processing', 'retrying']
            )
        ) {

            return back()->with(

                'error',

                'Order already in queue.'
            );
        }

        $order->update([
            'sync_status' => 'pending',
            'last_error' => null,
        ]);

        SyncOrderToTallyJob::dispatch(
            $order->id
        );

        return back()->with(

            'success',

            'Retry queued successfully.'
        );
    }
}