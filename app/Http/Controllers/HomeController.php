<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TallyOrder;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
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
            ['pending', 'processing', 'retrying']
        )->count();

        $orders = TallyOrder::latest()
            ->paginate(20);

        return view('admin.dashboard', compact(
            'todayOrders',
            'successOrders',
            'failedOrders',
            'pendingOrders',
            'orders'
        ));
    }

}
