<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tally Middleware Dashboard</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>

    <style>

        body{
            background:#f8faf7;
            font-family:'Segoe UI',sans-serif;
            color:#1f2937;
        }

        .top-header{
            background:linear-gradient(135deg,#d9f99d,#ffffff,#fef08a);
            padding:28px;
            border-radius:24px;
            box-shadow:0 10px 30px rgba(0,0,0,0.06);
            margin-bottom:28px;
        }

        .top-header h1{
            font-size:36px;
            font-weight:800;
            margin:0;
            color:#14532d;
        }

        .top-header p{
            margin-top:5px;
            color:#4b5563;
            font-size:15px;
        }

        .refresh-btn{
            border-radius:50px;
            padding:12px 22px;
            font-size:14px;
            font-weight:600;
        }

        .stats-card{
            border-radius:22px;
            padding:24px;
            background:white;
            transition:.3s;
            box-shadow:0 10px 24px rgba(0,0,0,0.05);
            position:relative;
            overflow:hidden;
            height:100%;
        }

        .stats-card:hover{
            transform:translateY(-4px);
        }

        .stats-card .icon{
            position:absolute;
            right:20px;
            top:20px;
            font-size:38px;
            opacity:.12;
        }

        .stats-card h6{
            font-size:15px;
            font-weight:600;
            margin-bottom:8px;
            color:#6b7280;
        }

        .stats-card h2{
            font-size:34px;
            font-weight:800;
            margin:0;
        }

        .primary-card{
            border-left:5px solid #3b82f6;
        }

        .success-card{
            border-left:5px solid #22c55e;
        }

        .danger-card{
            border-left:5px solid #ef4444;
        }

        .warning-card{
            border-left:5px solid #eab308;
        }

        .table-card{
            background:white;
            border-radius:24px;
            padding:26px;
            box-shadow:0 10px 24px rgba(0,0,0,0.05);
        }

        .table-title{
            font-size:26px;
            font-weight:800;
            margin-bottom:4px;
        }

        .search-sort-wrapper{
            display:flex;
            align-items:center;
            gap:12px;
            flex-wrap:nowrap;
        }

        .search-input{
            border-radius:50px;
            height:48px;
            border:1px solid #d1d5db;
            padding:0 18px;
            width:270px;
            font-size:14px;
        }

        .search-input:focus{
            box-shadow:none;
            border-color:#84cc16;
        }

        .sort-select{
            border-radius:50px;
            height:48px;
            border:1px solid #d1d5db;
            width:170px;
            padding:0 16px;
            font-size:14px;
        }

        .sort-select:focus{
            box-shadow:none;
            border-color:#84cc16;
        }

        .apply-btn{
            border-radius:50px;
            padding:11px 22px;
            font-size:14px;
            font-weight:600;
            white-space:nowrap;
        }

        .table{
            margin-bottom:0;
        }

        .table thead{
            background:#f1f5f9;
        }

        .table thead th{
            border:none;
            padding:16px;
            color:#374151;
            font-weight:700;
            font-size:15px;
            white-space:nowrap;
        }

        .table tbody td{
            padding:18px 16px;
            vertical-align:middle;
            border-color:#edf2f7;
            font-size:15px;
        }

        .table tbody tr{
            transition:.3s;
        }

        .table tbody tr:hover{
            background:#f9fafb;
        }

        .status-badge{
            padding:8px 14px;
            border-radius:30px;
            font-size:12px;
            font-weight:700;
            display:inline-block;
        }

        .status-success{
            background:#dcfce7;
            color:#166534;
        }

        .status-failed{
            background:#fee2e2;
            color:#991b1b;
        }

        .status-pending,
        .status-processing,
        .status-retrying{
            background:#fef9c3;
            color:#854d0e;
        }

        .action-btn{
            border:none;
            border-radius:12px;
            padding:9px 14px;
            font-size:13px;
            font-weight:600;
            transition:.3s;
            white-space:nowrap;
        }

        .btn-view{
            background:#ecfccb;
            color:#365314;
        }

        .btn-view:hover{
            background:#d9f99d;
        }

        .btn-retry{
            background:#fef08a;
            color:#854d0e;
        }

        .btn-retry:hover{
            background:#fde047;
        }

        .btn-delete{
            background:#fee2e2;
            color:#991b1b;
        }

        .btn-delete:hover{
            background:#fecaca;
        }

        .pagination-area{
            margin-top:28px;
            display:flex;
            justify-content:space-between;
            align-items:center;
            flex-wrap:wrap;
            gap:18px;
        }

        .pagination-info{
            color:#6b7280;
            font-size:15px;
            font-weight:600;
        }

        .pagination{
            margin:0;
            display:flex;
            align-items:center;
            gap:10px;
        }

        .pagination .page-item{
            list-style:none;
        }

        .pagination .page-link{
            border:none !important;
            width:44px;
            height:44px;
            border-radius:14px !important;
            display:flex;
            align-items:center;
            justify-content:center;
            background:#f3f4f6;
            color:#14532d;
            font-weight:700;
            font-size:14px;
            transition:.3s;
            box-shadow:0 4px 12px rgba(0,0,0,0.05);
        }

        .pagination .page-link:hover{
            background:#dcfce7;
            color:#14532d;
            transform:translateY(-2px);
        }

        .pagination .active .page-link{
            background:linear-gradient(135deg,#84cc16,#22c55e) !important;
            color:white !important;
            box-shadow:none;
        }

        .pagination .disabled .page-link{
            opacity:.45;
            pointer-events:none;
            background:#f3f4f6;
        }

        .pagination svg{
            width:14px;
            height:14px;
        }
        .top-header{
            background:linear-gradient(135deg,#ffffff,#f7fee7,#ecfccb);
            padding:32px;
            border-radius:28px;
            box-shadow:0 12px 35px rgba(0,0,0,0.06);
            margin-bottom:30px;
            border:1px solid rgba(132,204,22,0.15);
            position:relative;
        }

        .dashboard-badge{
            display:inline-flex;
            align-items:center;
            gap:8px;
            background:rgba(34,197,94,0.12);
            color:#15803d;
            padding:8px 16px;
            border-radius:50px;
            font-size:12px;
            font-weight:700;
            letter-spacing:.5px;
            backdrop-filter:blur(10px);
        }

        .dashboard-title{
            font-size:38px;
            font-weight:800;
            margin:0;
            color:#14532d;
            line-height:1.2;
        }

        .dashboard-subtitle{
            margin-top:8px;
            color:#4b5563;
            font-size:16px;
            font-weight:500;
        }

        .refresh-btn-new{
            background:linear-gradient(135deg,#111827,#1f2937);
            color:white;
            border:none;
            border-radius:50px;
            padding:13px 24px;
            font-size:15px;
            font-weight:600;
            box-shadow:0 10px 25px rgba(0,0,0,0.12);
            transition:.3s;
        }

        .refresh-btn-new:hover{
            transform:translateY(-2px);
            color:white;
        }

        .floating-ball{
            position:absolute;
            border-radius:50%;
            filter:blur(2px);
            opacity:.45;
            animation:floatMove 10s infinite ease-in-out;
        }

        .ball-1{
            width:90px;
            height:90px;
            background:#bef264;
            top:-20px;
            right:120px;
            animation-delay:0s;
        }

        .ball-2{
            width:55px;
            height:55px;
            background:#86efac;
            bottom:10px;
            right:40px;
            animation-delay:2s;
        }

        .ball-3{
            width:70px;
            height:70px;
            background:#fde047;
            top:40%;
            left:45%;
            animation-delay:4s;
        }

        .ball-4{
            width:35px;
            height:35px;
            background:#4ade80;
            top:15px;
            left:35%;
            animation-delay:1s;
        }

        @keyframes floatMove{

            0%{
                transform:translateY(0px) translateX(0px);
            }

            25%{
                transform:translateY(-15px) translateX(10px);
            }

            50%{
                transform:translateY(10px) translateX(-10px);
            }

            75%{
                transform:translateY(-8px) translateX(12px);
            }

            100%{
                transform:translateY(0px) translateX(0px);
            }

        }

        @media(max-width:768px){

            .top-header{
                padding:24px;
            }

            .dashboard-title{
                font-size:28px;
            }

            .dashboard-subtitle{
                font-size:14px;
            }

            .refresh-btn-new{
                width:100%;
                text-align:center;
            }

        }
        @media(max-width:991px){

            .search-sort-wrapper{
                width:100%;
                flex-wrap:wrap;
            }

            .search-input{
                width:100%;
            }

            .sort-select{
                width:100%;
            }

            .apply-btn{
                width:100%;
            }

        }

        @media(max-width:768px){

            .top-header h1{
                font-size:28px;
            }

            .stats-card h2{
                font-size:28px;
            }

            .table-card{
                padding:18px;
            }

            .table-responsive{
                overflow-x:auto;
            }

            .pagination-area{
                flex-direction:column;
                align-items:center;
                text-align:center;
            }

        }

    </style>

</head>
<body>

<div class="container-fluid p-4">

<div class="top-header position-relative overflow-hidden">

    {{-- MOVING GLOW BALLS --}}

    <div class="floating-ball ball-1"></div>
    <div class="floating-ball ball-2"></div>
    <div class="floating-ball ball-3"></div>
    <div class="floating-ball ball-4"></div>

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 position-relative z-2">

        <div>

            <!-- <div class="dashboard-badge mb-3">

                <i class="fa-solid fa-bolt"></i>

                LIVE TALLY CONNECTOR

            </div> -->
            @if($tallyConnected)

            <div class="dashboard-badge mb-3"
                style="background:#dcfce7;color:#15803d;">
                <i class="fa-solid fa-circle-check"></i>
                TALLY CONNECTED
            </div>

            @else

            <div class="dashboard-badge mb-3"
                style="background:#fee2e2;color:#dc2626;">
                <i class="fa-solid fa-circle-xmark"></i>
                TALLY DISCONNECTED
            </div>

            @endif

            <h1 class="dashboard-title">
                Tally Middleware Dashboard
            </h1>

            <p class="dashboard-subtitle">
                WooCommerce → Tally ERP Real-Time Order Sync Monitor
            </p>

        </div>

        <a href="{{ route('admin.bulk-sync') }}" class="btn refresh-btn-new">
            <i class="fas fa-cloud-upload-alt me-2"></i>
            Bulk Sync Orders
        </a>

    </div>

</div>

    <div class="row g-4 mb-4">

        <div class="col-lg-3 col-md-6">

            <div class="stats-card primary-card">

                <div class="icon">
                    <i class="fa fa-cart-shopping"></i>
                </div>

                <h6>Today's Orders</h6>

                <h2>{{ $todayOrders }}</h2>

            </div>

        </div>

        <div class="col-lg-3 col-md-6">

            <div class="stats-card success-card">

                <div class="icon">
                    <i class="fa fa-circle-check"></i>
                </div>

                <h6>Synced Orders</h6>

                <h2>{{ $successOrders }}</h2>

            </div>

        </div>

        <div class="col-lg-3 col-md-6">

            <div class="stats-card danger-card">

                <div class="icon">
                    <i class="fa fa-circle-xmark"></i>
                </div>

                <h6>Failed Orders</h6>

                <h2>{{ $failedOrders }}</h2>

            </div>

        </div>

        <div class="col-lg-3 col-md-6">

            <div class="stats-card warning-card">

                <div class="icon">
                    <i class="fa fa-clock"></i>
                </div>

                <h6>Pending Orders</h6>

                <h2>{{ $pendingOrders }}</h2>

            </div>

        </div>

    </div>

    <div class="table-card">

        <form method="GET">

            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">

                <div>

                    <h3 class="table-title">
                        📦 Orders Monitor
                    </h3>

                    <small class="text-muted">
                        Real-time synced orders from WooCommerce to Tally
                    </small>

                </div>

                <div class="search-sort-wrapper">

                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        class="form-control search-input"
                        placeholder="Search Order / Customer..."
                    >

                    <select
                        name="sort"
                        class="form-select sort-select"
                    >

                        <option value="latest"
                            {{ request('sort') == 'latest' ? 'selected' : '' }}>
                            Latest
                        </option>

                        <option value="oldest"
                            {{ request('sort') == 'oldest' ? 'selected' : '' }}>
                            Oldest
                        </option>

                        <option value="amount_high"
                            {{ request('sort') == 'amount_high' ? 'selected' : '' }}>
                            Amount High
                        </option>

                        <option value="amount_low"
                            {{ request('sort') == 'amount_low' ? 'selected' : '' }}>
                            Amount Low
                        </option>

                    </select>

                    <button class="btn btn-dark apply-btn">

                        <i class="fa fa-filter me-1"></i>

                        Apply

                    </button>

                </div>

            </div>

        </form>

        <div class="table-responsive">

            <table class="table align-middle">

                <thead>

                    <tr>

                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Retries</th>
                        <th>Synced At</th>
                        <th width="300">Actions</th>

                    </tr>

                </thead>

                <tbody>

                @forelse($orders as $order)

                    <tr>

                        <td class="fw-bold">
                            #{{ $order->order_number }}
                        </td>

                        <td>
                            {{ $order->customer_name ?? 'N/A' }}
                        </td>

                        <td class="fw-semibold text-success">
                            ₹{{ number_format($order->amount ?? 0, 2) }}
                        </td>

                        <td>

                            <span class="status-badge status-{{ $order->sync_status }}">
                                {{ strtoupper($order->sync_status) }}
                            </span>

                        </td>

                        <td>

                            <span class="badge bg-dark">
                                {{ $order->retry_count }}
                            </span>

                        </td>

                        <td>

                            {{ $order->synced_at ? \Carbon\Carbon::parse($order->synced_at)->format('d M Y h:i A') : '-' }}

                        </td>

                        <td>

                            <div class="d-flex gap-2 align-items-center flex-nowrap">

                                <a
                                    href="{{ route('admin.orders.show',$order->id) }}"
                                    class="action-btn btn-view text-decoration-none"
                                >
                                    <i class="fa fa-eye"></i>
                                    View
                                </a>

                                @if($order->sync_status == 'failed')

                                    <form
                                        method="POST"
                                        action="{{ route('admin.retry', $order->id) }}"
                                    >

                                        @csrf

                                        <button class="action-btn btn-retry">

                                            <i class="fa fa-rotate-right"></i>
                                            Retry

                                        </button>

                                    </form>

                                @endif

                                <form
                                    method="POST"
                                    action="{{ route('admin.orders.delete', $order->id) }}"
                                    onsubmit="return confirm('Delete this order?')"
                                >

                                    @csrf
                                    @method('DELETE')

                                    <button class="action-btn btn-delete">

                                        <i class="fa fa-trash"></i>
                                        Delete

                                    </button>

                                </form>

                            </div>

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td colspan="7" class="text-center py-5">

                            <img
                                src="https://cdn-icons-png.flaticon.com/512/7466/7466073.png"
                                width="110"
                                class="mb-3"
                            >

                            <h5>No Orders Found</h5>

                        </td>

                    </tr>

                @endforelse

                </tbody>

            </table>

        </div>

        <div class="pagination-area">

            <div class="pagination-info">

                Showing
                {{ $orders->firstItem() ?? 0 }}
                to
                {{ $orders->lastItem() ?? 0 }}
                of
                {{ $orders->total() }}
                results

            </div>

            <div>

                {{ $orders->onEachSide(1)->links('pagination::bootstrap-5') }}

            </div>

        </div>

    </div>

</div>

</body>
</html>