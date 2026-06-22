{{-- resources/views/admin/bulk-sync.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Bulk Sync — Tally</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>

    <style>

        /* ═══════════════════════════════════════
           BASE — same as dashboard
        ═══════════════════════════════════════ */

        body {
            background: #f8faf7;
            font-family: 'Segoe UI', sans-serif;
            color: #1f2937;
        }

        /* ═══════════════════════════════════════
           NAVBAR
        ═══════════════════════════════════════ */

        .top-nav {
            background: #111827;
            padding: 14px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .top-nav .brand {
            font-size: 20px;
            font-weight: 800;
            color: #fff;
            text-decoration: none;
            letter-spacing: .3px;
        }

        .top-nav .brand span { color: #84cc16; }

        .top-nav .back-btn {
            background: rgba(255,255,255,.1);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: background .2s;
        }

        .top-nav .back-btn:hover {
            background: rgba(255,255,255,.18);
            color: #fff;
        }

        /* ═══════════════════════════════════════
           TOP HEADER — same gradient as dashboard
        ═══════════════════════════════════════ */

        .top-header {
            background: linear-gradient(135deg, #ffffff, #f7fee7, #ecfccb);
            padding: 32px;
            border-radius: 28px;
            box-shadow: 0 12px 35px rgba(0,0,0,.06);
            margin-bottom: 30px;
            border: 1px solid rgba(132,204,22,.15);
            position: relative;
            overflow: hidden;
        }

        .dashboard-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(34,197,94,.12);
            color: #15803d;
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .5px;
        }

        .dashboard-title {
            font-size: 34px;
            font-weight: 800;
            margin: 6px 0 6px;
            color: #14532d;
            line-height: 1.2;
        }

        .dashboard-subtitle {
            margin: 0;
            color: #4b5563;
            font-size: 15px;
            font-weight: 500;
        }

        /* floating balls — same as dashboard */
        .floating-ball {
            position: absolute;
            border-radius: 50%;
            filter: blur(2px);
            opacity: .45;
            animation: floatMove 10s infinite ease-in-out;
        }
        .ball-1 { width:90px; height:90px; background:#bef264; top:-20px; right:120px; animation-delay:0s; }
        .ball-2 { width:55px; height:55px; background:#86efac; bottom:10px; right:40px; animation-delay:2s; }
        .ball-3 { width:70px; height:70px; background:#fde047; top:40%; left:45%; animation-delay:4s; }
        .ball-4 { width:35px; height:35px; background:#4ade80; top:15px; left:35%; animation-delay:1s; }

        @keyframes floatMove {
            0%   { transform: translateY(0px) translateX(0px); }
            25%  { transform: translateY(-15px) translateX(10px); }
            50%  { transform: translateY(10px) translateX(-10px); }
            75%  { transform: translateY(-8px) translateX(12px); }
            100% { transform: translateY(0px) translateX(0px); }
        }

        .refresh-btn-new {
            background: linear-gradient(135deg, #111827, #1f2937);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 13px 24px;
            font-size: 15px;
            font-weight: 600;
            box-shadow: 0 10px 25px rgba(0,0,0,.12);
            transition: .3s;
            cursor: pointer;
        }

        .refresh-btn-new:hover { transform: translateY(-2px); color: white; }

        /* ═══════════════════════════════════════
           STAT CARDS — same as dashboard
        ═══════════════════════════════════════ */

        .stats-card {
            border-radius: 22px;
            padding: 24px;
            background: white;
            transition: .3s;
            box-shadow: 0 10px 24px rgba(0,0,0,.05);
            position: relative;
            overflow: hidden;
            height: 100%;
        }

        .stats-card:hover { transform: translateY(-4px); }

        .stats-card .icon {
            position: absolute;
            right: 20px; top: 20px;
            font-size: 38px;
            opacity: .12;
        }

        .stats-card h6 { font-size: 15px; font-weight: 600; margin-bottom: 8px; color: #6b7280; }
        .stats-card h2 { font-size: 34px; font-weight: 800; margin: 0; }

        .primary-card { border-left: 5px solid #3b82f6; }
        .success-card  { border-left: 5px solid #22c55e; }
        .danger-card   { border-left: 5px solid #ef4444; }
        .warning-card  { border-left: 5px solid #eab308; }

        /* ═══════════════════════════════════════
           MAIN CARDS
        ═══════════════════════════════════════ */

        .main-card {
            background: white;
            border-radius: 24px;
            padding: 26px;
            box-shadow: 0 10px 24px rgba(0,0,0,.05);
        }

        .main-card-title {
            font-size: 20px;
            font-weight: 800;
            margin-bottom: 4px;
            color: #111;
        }

        /* ═══════════════════════════════════════
           FORM ELEMENTS
        ═══════════════════════════════════════ */

        .bsp-label {
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
            display: block;
        }

        .bsp-input {
            width: 100%;
            border: 1.5px solid #e5e7eb;
            border-radius: 12px;
            padding: 11px 14px;
            font-size: 14px;
            color: #111;
            background: #fff;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
            -webkit-appearance: none;
        }

        .bsp-input:focus {
            border-color: #84cc16;
            box-shadow: 0 0 0 3px rgba(132,204,22,.15);
        }

        /* ═══════════════════════════════════════
           QUICK PILLS
        ═══════════════════════════════════════ */

        .pill-wrap { display: flex; flex-wrap: wrap; gap: 8px; }

        .qpill {
            padding: 7px 16px;
            border-radius: 50px;
            border: 1.5px solid #e5e7eb;
            background: #fff;
            font-size: 12px;
            font-weight: 600;
            color: #555;
            cursor: pointer;
            transition: all .15s;
            user-select: none;
        }

        .qpill:hover { border-color: #84cc16; color: #15803d; background: #f7fee7; }
        .qpill.active { background: #14532d; border-color: #14532d; color: #fff; }

        /* ═══════════════════════════════════════
           BUTTONS
        ═══════════════════════════════════════ */

        .btn-preview-orders {
            width: 100%;
            padding: 12px;
            border-radius: 50px;
            border: 1.5px solid #d1d5db;
            background: #fff;
            font-size: 14px;
            font-weight: 600;
            color: #374151;
            cursor: pointer;
            transition: all .15s;
        }

        .btn-preview-orders:hover:not(:disabled) {
            background: #f9fafb;
            border-color: #9ca3af;
        }

        .btn-preview-orders:disabled { opacity: .45; cursor: not-allowed; }

        .btn-start-sync {
            width: 100%;
            padding: 13px;
            border-radius: 50px;
            border: none;
            background: linear-gradient(135deg, #15803d, #16a34a, #22c55e);
            font-size: 15px;
            font-weight: 700;
            color: #fff;
            cursor: pointer;
            box-shadow: 0 8px 20px rgba(22,163,74,.3);
            transition: all .2s;
        }

        .btn-start-sync:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(22,163,74,.4);
        }

        .btn-start-sync:disabled { opacity: .4; cursor: not-allowed; transform: none; box-shadow: none; }

        /* ═══════════════════════════════════════
           SUMMARY BOX
        ═══════════════════════════════════════ */

        .sum-box {
            background: #f9fafb;
            border-radius: 14px;
            padding: 16px 18px;
            margin-top: 18px;
            border: 1px solid #e5e7eb;
            animation: fadeUp .3s ease;
        }

        .sum-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            padding: 4px 0;
        }

        .sum-row .k { color: #6b7280; }
        .sum-row .v { font-weight: 700; color: #111; }
        .sum-row .v.g { color: #16a34a; }
        .sum-row .v.b { color: #2563eb; }

        /* ═══════════════════════════════════════
           ALERTS
        ═══════════════════════════════════════ */

        .bsp-alert {
            border-radius: 16px;
            padding: 16px 20px;
            font-size: 13px;
            margin-bottom: 20px;
            animation: fadeUp .3s ease;
            line-height: 1.65;
        }

        .bsp-alert.success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; }
        .bsp-alert.error   { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; }
        .bsp-alert.info    { background: #f0f9ff; border: 1px solid #bae6fd; color: #0369a1; }
        .bsp-alert.loading {
            background: #fafffe;
            border: 1px solid #d1fae5;
            color: #065f46;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* ═══════════════════════════════════════
           SPINNER
        ═══════════════════════════════════════ */

        .spin-circle {
            width: 18px; height: 18px;
            border-radius: 50%;
            border: 2.5px solid #a7f3d0;
            border-top-color: #16a34a;
            animation: doSpin .65s linear infinite;
            flex-shrink: 0;
        }

        @keyframes doSpin { to { transform: rotate(360deg); } }

        /* ═══════════════════════════════════════
           PROGRESS BAR
        ═══════════════════════════════════════ */

        .prog-track {
            background: #f0fdf4;
            border-radius: 50px;
            height: 8px;
            overflow: hidden;
            margin: 8px 0 4px;
        }

        .prog-fill {
            height: 100%;
            border-radius: 50px;
            background: linear-gradient(90deg, #22c55e, #84cc16);
            transition: width .5s ease;
        }

        /* ═══════════════════════════════════════
           TABLE
        ═══════════════════════════════════════ */

        .bsp-table { width: 100%; border-collapse: collapse; }

        .bsp-table thead th {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: #6b7280;
            padding: 12px 16px;
            background: #f9fafb;
            border-bottom: 1px solid #f3f4f6;
        }

        .bsp-table tbody td {
            font-size: 13px;
            padding: 12px 16px;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
        }

        .bsp-table tbody tr:last-child td { border-bottom: none; }
        .bsp-table tbody tr:hover td { background: #fafff7; }

        .stag {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 700;
        }

        .stag.proc { background: #eff6ff; color: #1d4ed8; }
        .stag.comp { background: #f0fdf4; color: #15803d; }
        .stag.ship { background: #fdf4ff; color: #7e22ce; }

        /* ═══════════════════════════════════════
           RECENT LIST
        ═══════════════════════════════════════ */

        .ri {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 13px 20px;
            border-bottom: 1px solid #f3f4f6;
            transition: background .1s;
        }

        .ri:last-child { border-bottom: none; }
        .ri:hover { background: #f9fffe; }
        .ri-num  { font-size: 14px; font-weight: 700; color: #111; }
        .ri-name { font-size: 12px; color: #9ca3af; margin-top: 2px; }
        .ri-amt  { font-size: 14px; font-weight: 700; color: #16a34a; }
        .ri-time { font-size: 11px; color: #9ca3af; text-align: right; margin-top: 2px; }

        /* ═══════════════════════════════════════
           RETRY STRIP
        ═══════════════════════════════════════ */

        .retry-strip {
            background: #fff8f8;
            border: 1px solid #fecaca;
            border-radius: 18px;
            padding: 16px 20px;
            margin-top: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .btn-do-retry {
            background: linear-gradient(135deg, #dc2626, #ef4444);
            color: #fff;
            border: none;
            border-radius: 50px;
            padding: 9px 20px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: all .15s;
        }

        .btn-do-retry:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(220,38,38,.3); }

        /* ═══════════════════════════════════════
           BADGE
        ═══════════════════════════════════════ */

        .bs-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 700;
        }

        .bs-badge.green  { background: #dcfce7; color: #15803d; }
        .bs-badge.blue   { background: #dbeafe; color: #1d4ed8; }
        .bs-badge.red    { background: #fee2e2; color: #b91c1c; }

        /* ═══════════════════════════════════════
           SECTION LABEL
        ═══════════════════════════════════════ */

        .section-label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #9ca3af;
            margin-bottom: 8px;
        }

        /* ═══════════════════════════════════════
           ANIMATION
        ═══════════════════════════════════════ */

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: none; }
        }

        .anim-1 { animation: fadeUp .4s .05s ease both; }
        .anim-2 { animation: fadeUp .4s .12s ease both; }
        .anim-3 { animation: fadeUp .4s .18s ease both; }
        .anim-4 { animation: fadeUp .4s .24s ease both; }

        /* ═══════════════════════════════════════
           EMPTY STATE
        ═══════════════════════════════════════ */

        .empty-st {
            text-align: center;
            padding: 48px 20px;
            color: #9ca3af;
            font-size: 13px;
        }

        .empty-st .ei { font-size: 40px; display: block; margin-bottom: 10px; }

        /* ═══════════════════════════════════════
           RESPONSIVE
        ═══════════════════════════════════════ */

        @media(max-width:768px) {
            .top-header { padding: 24px; }
            .dashboard-title { font-size: 26px; }
            .stats-card h2 { font-size: 28px; }
            .refresh-btn-new { width: 100%; text-align: center; }
        }

    </style>
</head>
<body>

{{-- ══ NAVBAR ═══════════════════════════════════════════════════ --}}
<div class="top-nav">
    <a href="{{ route('admin.dashboard') }}" class="brand">
        UPJAU <span>TALLY</span>
    </a>
    <a href="{{ route('admin.dashboard') }}" class="back-btn">
        <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
    </a>
</div>

<div class="container-fluid p-4">

    {{-- ══ HEADER ════════════════════════════════════════════════ --}}
    <div class="top-header anim-1">

        <div class="floating-ball ball-1"></div>
        <div class="floating-ball ball-2"></div>
        <div class="floating-ball ball-3"></div>
        <div class="floating-ball ball-4"></div>

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 position-relative" style="z-index:2">
            <div>
                <div class="dashboard-badge mb-3">
                    <i class="fa-solid fa-arrows-rotate"></i>
                    BULK SYNC ENGINE
                </div>
                <h1 class="dashboard-title">Bulk Order Sync</h1>
                <p class="dashboard-subtitle">
                    Fetch WooCommerce orders · preview · push vouchers to Tally ERP
                </p>
            </div>
            <button class="refresh-btn-new" onclick="loadProgress()" id="refreshBtn">
                <i class="fas fa-rotate-right me-2"></i> Refresh Stats
            </button>
        </div>
    </div>

    {{-- ══ STAT CARDS ════════════════════════════════════════════ --}}
    <div class="row g-4 mb-4">
        <div class="col-lg-3 col-md-6 anim-1">
            <div class="stats-card primary-card">
                <div class="icon"><i class="fa fa-database"></i></div>
                <h6>Total in DB</h6>
                <h2 id="stat-total">{{ $stats['total'] }}</h2>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 anim-2">
            <div class="stats-card success-card">
                <div class="icon"><i class="fa fa-circle-check"></i></div>
                <h6>Synced to Tally</h6>
                <h2 id="stat-success" style="color:#16a34a">{{ $stats['success'] }}</h2>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 anim-3">
            <div class="stats-card warning-card">
                <div class="icon"><i class="fa fa-clock"></i></div>
                <h6>In Queue</h6>
                <h2 id="stat-pending" style="color:#d97706">{{ $stats['pending'] + $stats['processing'] }}</h2>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 anim-4">
            <div class="stats-card danger-card">
                <div class="icon"><i class="fa fa-circle-xmark"></i></div>
                <h6>Failed</h6>
                <h2 id="stat-failed" style="color:#dc2626">{{ $stats['failed'] }}</h2>
            </div>
        </div>
    </div>

    {{-- ══ MAIN GRID ══════════════════════════════════════════════ --}}
    <div class="row g-4 align-items-start">

        {{-- LEFT — CONTROLS ──────────────────────────────────── --}}
        <div class="col-lg-4 anim-2">

            <div class="main-card">

                <h3 class="main-card-title mb-1">📅 Date Range</h3>
                <p class="text-muted mb-4" style="font-size:13px">Select the period to sync orders from</p>

                <div class="mb-3">
                    <label class="bsp-label">From</label>
                    <input type="date" class="bsp-input" id="fromDate"
                        value="{{ now()->startOfMonth()->format('Y-m-d') }}">
                </div>

                <div class="mb-3">
                    <label class="bsp-label">To</label>
                    <input type="date" class="bsp-input" id="toDate"
                        value="{{ now()->format('Y-m-d') }}">
                </div>

                <div class="mb-4">
                    <label class="bsp-label">Order Status</label>
                    <select class="bsp-input" id="orderStatus">
                        <option value="all">All</option>
                        <option value="processing">Processing Only</option>
                        <option value="completed">Completed Only</option>
                        <option value="ready-to-ship">Ready to Ship Only</option>
                        <option value="shipped">Shipped Only</option>
                        <option value="delivered">Delivered Only</option>
                    </select>
                </div>

                <div class="mb-4">
                    <div class="section-label">Quick Select</div>
                    <div class="pill-wrap">
                        <span class="qpill" onclick="setRange('today',this)">Today</span>
                        <span class="qpill" onclick="setRange('week',this)">This Week</span>
                        <span class="qpill active" onclick="setRange('month',this)">This Month</span>
                        <span class="qpill" onclick="setRange('lastmonth',this)">Last Month</span>
                    </div>
                </div>

                <div class="d-flex flex-column gap-2">
                    <button class="btn-preview-orders" id="previewBtn" onclick="previewOrders()">
                        <i class="fa fa-magnifying-glass me-2"></i>Preview Orders
                    </button>
                    <button class="btn-start-sync" id="syncBtn" onclick="startSync()" disabled>
                        <i class="fa fa-rocket me-2"></i>Start Voucher Sync
                    </button>
                </div>

                <div id="previewResult" class="sum-box" style="display:none">
                    <div class="sum-row">
                        <span class="k">Total WC orders</span>
                        <span class="v" id="pTotal">—</span>
                    </div>
                    <div class="sum-row">
                        <span class="k">Already synced (skip)</span>
                        <span class="v g" id="pSynced">—</span>
                    </div>
                    <div class="sum-row">
                        <span class="k">Will be queued</span>
                        <span class="v b" id="pQueued">—</span>
                    </div>
                </div>

            </div>

            {{-- Retry strip --}}
            <div class="retry-strip" id="retryCard"
                style="{{ $stats['failed'] > 0 ? '' : 'display:none' }}">
                <div>
                    <div style="font-size:14px;font-weight:700;color:#b91c1c">
                        ⚠️ <span id="failedCount">{{ $stats['failed'] }}</span> Failed Orders
                    </div>
                    <div style="font-size:12px;color:#9ca3af;margin-top:3px">
                        These orders didn't sync — retry them
                    </div>
                </div>
                <button class="btn-do-retry" onclick="retryFailed()">
                    <i class="fa fa-rotate-right me-1"></i> Retry All
                </button>
            </div>

        </div>

        {{-- RIGHT — OUTPUT ────────────────────────────────────── --}}
        <div class="col-lg-8 anim-3">

            {{-- Alert area --}}
            <div id="alertArea"></div>

            {{-- Orders preview table --}}
            <div class="main-card mb-4" id="ordersCard" style="display:none">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <h3 class="main-card-title mb-0">Orders to Sync</h3>
                        <p class="text-muted mb-0" style="font-size:12px">Showing first 50 — all will be queued on sync</p>
                    </div>
                    <span class="bs-badge blue" id="ordersCount">0</span>
                </div>
                <div style="border-radius:14px;overflow:hidden;border:1px solid #f3f4f6;max-height:320px;overflow-y:auto">
                    <table class="bsp-table">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th style="text-align:right">Amount</th>
                            </tr>
                        </thead>
                        <tbody id="ordersBody"></tbody>
                    </table>
                </div>
            </div>

            {{-- Recently synced --}}
            <div class="main-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div>
                        <h3 class="main-card-title mb-0">✅ Recently Synced</h3>
                        <p class="text-muted mb-0" style="font-size:12px">Last 10 successfully synced orders</p>
                    </div>
                    <span class="bs-badge green" id="recentBadge">—</span>
                </div>

                {{-- Progress bar (visible when queue is active) --}}
                <div id="progressArea" style="display:none;margin-bottom:12px">
                    <div class="d-flex justify-content-between" style="font-size:12px;color:#6b7280;margin-bottom:4px">
                        <span><i class="fa fa-gear fa-spin me-1"></i>Queue is active — syncing...</span>
                        <span id="progText" style="font-weight:700;color:#16a34a">0%</span>
                    </div>
                    <div class="prog-track">
                        <div class="prog-fill" id="progBar" style="width:0%"></div>
                    </div>
                </div>

                <div id="recentList" style="border-radius:14px;overflow:hidden;border:1px solid #f3f4f6;max-height:380px;overflow-y:auto">
                    <div class="empty-st">
                        <span class="ei">🔄</span>
                        Click <strong>Refresh Stats</strong> to load recent activity
                    </div>
                </div>

            </div>
        </div>
    </div>

</div><!-- /container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>

/* ── Date helpers ──────────────────────────── */

function fmt(d){ return d.toISOString().split('T')[0]; }

function setRange(type, el){
    const t=new Date(); let from, to=fmt(t);
    if(type==='today'){ from=to; }
    else if(type==='week'){ const d=new Date(t); d.setDate(d.getDate()-d.getDay()+1); from=fmt(d); }
    else if(type==='month'){ from=fmt(new Date(t.getFullYear(),t.getMonth(),1)); }
    else if(type==='lastmonth'){
        from=fmt(new Date(t.getFullYear(),t.getMonth()-1,1));
        to  =fmt(new Date(t.getFullYear(),t.getMonth(),0));
    }
    document.getElementById('fromDate').value=from;
    document.getElementById('toDate').value=to;
    document.querySelectorAll('.qpill').forEach(p=>p.classList.remove('active'));
    el.classList.add('active');
    resetState();
}

function resetState(){
    document.getElementById('syncBtn').disabled=true;
    document.getElementById('previewResult').style.display='none';
    document.getElementById('ordersCard').style.display='none';
    document.getElementById('alertArea').innerHTML='';
}

/* ── Alert ─────────────────────────────────── */

function alert$(type,html){
    document.getElementById('alertArea').innerHTML=
        `<div class="bsp-alert ${type}">`+
        (type==='loading'?'<div class="spin-circle"></div>':'')+
        `<div>${html}</div></div>`;
}
function clearAlert(){ document.getElementById('alertArea').innerHTML=''; }

/* ── Preview ────────────────────────────────── */

async function previewOrders(){
    const btn=document.getElementById('previewBtn');
    btn.disabled=true;
    btn.innerHTML='<i class="fa fa-spinner fa-spin me-2"></i>Loading...';
    document.getElementById('syncBtn').disabled=true;
    clearAlert();

    try{
        const r=await post('/admin/bulk-sync/preview', formData());
        const d=await r.json();
        if(!d.success){ alert$('error','⚠️ '+d.message); return; }

        document.getElementById('pTotal').textContent  = d.total_orders;
        document.getElementById('pSynced').textContent = d.already_synced;
        document.getElementById('pQueued').textContent = d.to_be_queued;
        document.getElementById('previewResult').style.display='block';

        if(d.orders.length) renderTable(d.orders, d.to_be_queued);

        if(d.to_be_queued>0){
            document.getElementById('syncBtn').disabled=false;
        } else {
            alert$('info','<i class="fa fa-circle-check me-2"></i>All orders in this range are already synced to Tally.');
        }
    }catch(e){
        alert$('error','⚠️ Network error: '+e.message);
    }finally{
        btn.disabled=false;
        btn.innerHTML='<i class="fa fa-magnifying-glass me-2"></i>Preview Orders';
    }
}

function renderTable(orders, total){
    document.getElementById('ordersCount').textContent =
        total+(total>50?' (showing 50)':'');

    document.getElementById('ordersBody').innerHTML = orders.map(o=>`
        <tr>
            <td><strong>#${o.number}</strong></td>
            <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                ${o.customer||'—'}
            </td>
            <td style="color:#9ca3af;font-size:12px">${o.date}</td>
            <td>
                <span class="stag ${o.status==='completed'?'comp':o.status==='ready-to-ship'?'ship':'proc'}">
                    ${o.status}
                </span>
            </td>
            <td style="text-align:right;font-weight:700">
                ₹${parseFloat(o.total).toLocaleString('en-IN',{minimumFractionDigits:2})}
            </td>
        </tr>
    `).join('');

    document.getElementById('ordersCard').style.display='block';
}

/* ── Sync ───────────────────────────────────── */

async function startSync(){
    const n=document.getElementById('pQueued').textContent;
    if(!confirm(`Start sync for ${n} orders? They will be queued for Tally voucher creation.`)) return;

    document.getElementById('syncBtn').disabled=true;
    document.getElementById('previewBtn').disabled=true;
    alert$('loading','Fetching orders from WooCommerce and adding to queue...');

    try{
        const r=await post('/admin/bulk-sync/sync', formData());
        const d=await r.json();
        clearAlert();
        if(d.success){
            alert$('success',
                `<strong>✅ Sync started successfully!</strong><br>`+
                `${d.queued} orders queued &nbsp;·&nbsp; `+
                `${d.skipped} already synced (skipped) &nbsp;·&nbsp; `+
                `${d.failed} failed<br>`+
                `<span style="font-size:12px;opacity:.7">`+
                `Queue worker is creating vouchers in Tally. Refresh Stats to track live progress.`+
                `</span>`
            );
            startAutoRefresh();
            setTimeout(loadProgress,2000);
        } else {
            alert$('error','⚠️ '+d.message);
        }
    }catch(e){
        clearAlert();
        alert$('error','⚠️ Network error: '+e.message);
    }finally{
        document.getElementById('previewBtn').disabled=false;
    }
}

/* ── Progress ───────────────────────────────── */

async function loadProgress(){
    const btn=document.getElementById('refreshBtn');
    btn.innerHTML='<i class="fa fa-spinner fa-spin me-2"></i>Loading...';
    btn.disabled=true;

    try{
        const r=await fetch('/admin/bulk-sync/progress');
        const d=await r.json();

        animNum('stat-total',   d.stats.total);
        animNum('stat-success', d.stats.success);
        animNum('stat-pending', parseInt(d.stats.pending)+parseInt(d.stats.processing));
        animNum('stat-failed',  d.stats.failed);

        if(d.stats.failed>0){
            document.getElementById('failedCount').textContent=d.stats.failed;
            document.getElementById('retryCard').style.display='flex';
        } else {
            document.getElementById('retryCard').style.display='none';
        }

        const total   = parseInt(d.stats.total)||0;
        const success = parseInt(d.stats.success)||0;
        const pending = parseInt(d.stats.pending)+parseInt(d.stats.processing);
        if(pending>0 && total>0){
            const pct=Math.round((success/total)*100);
            document.getElementById('progressArea').style.display='block';
            document.getElementById('progBar').style.width=pct+'%';
            document.getElementById('progText').textContent=pct+'%';
        } else {
            document.getElementById('progressArea').style.display='none';
        }

        renderRecent(d.recent_success);

    }catch(e){ console.error(e); }
    finally{
        btn.innerHTML='<i class="fas fa-rotate-right me-2"></i>Refresh Stats';
        btn.disabled=false;
    }
}

function renderRecent(orders){
    document.getElementById('recentBadge').textContent=orders.length;
    const list=document.getElementById('recentList');
    if(!orders.length){
        list.innerHTML=`<div class="empty-st"><span class="ei">📭</span>No synced orders yet</div>`;
        return;
    }
    list.innerHTML=orders.map(o=>`
        <div class="ri">
            <div>
                <div class="ri-num">#${o.order_number}</div>
                <div class="ri-name">${o.customer_name||'—'}</div>
            </div>
            <div>
                <div class="ri-amt">₹${parseFloat(o.amount).toLocaleString('en-IN',{minimumFractionDigits:2})}</div>
                <div class="ri-time">${o.synced_at?fmtT(o.synced_at):'—'}</div>
            </div>
        </div>
    `).join('');
}

function fmtT(ts){
    return new Date(ts).toLocaleString('en-IN',{day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit'});
}

/* ── Retry ──────────────────────────────────── */

async function retryFailed(){
    const n=document.getElementById('failedCount').textContent;
    if(!confirm(`Retry all ${n} failed orders?`)) return;
    try{
        const r=await post('/admin/bulk-sync/retry-failed',{});
        const d=await r.json();
        alert$(d.success?'success':'error', d.message);
        setTimeout(loadProgress,1000);
    }catch(e){ alert$('error',e.message); }
}

/* ── Helpers ─────────────────────────────────── */

function formData(){
    return {
        from:   document.getElementById('fromDate').value,
        to:     document.getElementById('toDate').value,
        status: document.getElementById('orderStatus').value,
    };
}

function post(url,body){
    return fetch(url,{
        method:'POST',
        headers:{
            'Content-Type':'application/json',
            'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content
        },
        body:JSON.stringify(body)
    });
}

function animNum(id,target){
    const el=document.getElementById(id);
    const start=parseInt(el.textContent)||0;
    const end=parseInt(target)||0;
    if(start===end) return;
    const dur=600; const t0=performance.now();
    function step(t){
        const pct=Math.min((t-t0)/dur,1);
        const ease=1-Math.pow(1-pct,3);
        el.textContent=Math.round(start+(end-start)*ease);
        if(pct<1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
}

/* ── Auto-refresh ──────────────────────────── */

let timer=null;
function startAutoRefresh(){
    if(timer) return;
    timer=setInterval(()=>{
        const p=parseInt(document.getElementById('stat-pending').textContent||'0');
        if(p>0){ loadProgress(); } else { clearInterval(timer); timer=null; }
    },5000);
}

document.addEventListener('DOMContentLoaded',()=>{
    loadProgress();
    if({{ $stats['pending'] + $stats['processing'] }}>0) startAutoRefresh();
});

</script>
</body>
</html>