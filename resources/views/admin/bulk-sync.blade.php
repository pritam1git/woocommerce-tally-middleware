{{-- resources/views/admin/bulk-sync.blade.php --}}

@extends('layouts.app')

@section('title', 'Bulk Sync — Tally')

@section('content')

<div class="container-fluid py-4 px-4" style="max-width:1100px">

    {{-- HEADER --}}
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-1 fw-bold">WooCommerce → Tally Sync</h4>
            <p class="text-muted mb-0 small">Date range select karo, preview dekho, phir voucher create karo</p>
        </div>
        <button
            class="btn btn-outline-secondary btn-sm"
            onclick="loadProgress()"
            id="refreshBtn"
            style="color:#fff !important;"
        >
            ↻ Refresh Status
        </button>
    </div>

    {{-- STATS ROW --}}
    <div class="row g-3 mb-4" id="statsRow">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center py-3">
                    <div class="fs-2 fw-bold text-dark" id="stat-total">{{ $stats['total'] }}</div>
                    <div class="small text-muted">Total Orders</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100" style="border-left:3px solid #198754!important">
                <div class="card-body text-center py-3">
                    <div class="fs-2 fw-bold text-success" id="stat-success">{{ $stats['success'] }}</div>
                    <div class="small text-muted">Synced ✓</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center py-3">
                    <div class="fs-2 fw-bold text-warning" id="stat-pending">{{ $stats['pending'] + $stats['processing'] }}</div>
                    <div class="small text-muted">In Queue</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center py-3">
                    <div class="fs-2 fw-bold text-danger" id="stat-failed">{{ $stats['failed'] }}</div>
                    <div class="small text-muted">Failed</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">

        {{-- LEFT — SYNC FORM --}}
        <div class="col-lg-5">

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">

                    <h6 class="fw-bold mb-3">📅 Date Range Select Karo</h6>

                    {{-- DATE FROM --}}
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">From</label>
                        <input
                            type="date"
                            class="form-control"
                            id="fromDate"
                            value="{{ now()->startOfMonth()->format('Y-m-d') }}"
                            style="color:#000 !important;"
                        >
                    </div>

                    {{-- DATE TO --}}
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">To</label>
                        <input
                            type="date"
                            class="form-control"
                            id="toDate"
                            value="{{ now()->format('Y-m-d') }}"
                            style="color:#000 !important;"
                        >
                    </div>

                    {{-- STATUS --}}
                    <div class="mb-4">
                        <label class="form-label small fw-semibold">Order Status</label>
                        <select class="form-select" id="orderStatus">
                            <option value="all">Processing + Completed (All)</option>
                            <option value="processing">Processing Only</option>
                            <option value="completed">Completed Only</option>
                        </select>
                    </div>

                    {{-- QUICK SELECT BUTTONS --}}
                    <div class="mb-4">
                        <div class="small fw-semibold mb-2 text-muted">Quick Select</div>
                        <div class="d-flex flex-wrap gap-2">
                            <button class="btn btn-outline-secondary btn-sm" onclick="setRange('today')">Today</button>
                            <button class="btn btn-outline-secondary btn-sm" onclick="setRange('week')">This Week</button>
                            <button class="btn btn-outline-secondary btn-sm" onclick="setRange('month')">This Month</button>
                            <button class="btn btn-outline-secondary btn-sm" onclick="setRange('lastmonth')">Last Month</button>
                        </div>
                    </div>

                    {{-- BUTTONS --}}
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-primary" onclick="previewOrders()" id="previewBtn">
                            🔍 Preview Orders
                        </button>
                        <button
                            class="btn btn-success"
                            onclick="startSync()"
                            id="syncBtn"
                            disabled
                        >
                            🚀 Start Voucher Sync
                        </button>
                    </div>

                    {{-- PREVIEW RESULT --}}
                    <div id="previewResult" class="mt-3" style="display:none">
                        <hr>
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted">Total WC orders</span>
                            <strong id="previewTotal">—</strong>
                        </div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted">Already synced (skip)</span>
                            <strong class="text-success" id="previewSynced">—</strong>
                        </div>
                        <div class="d-flex justify-content-between small">
                            <span class="text-muted">Will be queued</span>
                            <strong class="text-primary" id="previewQueued">—</strong>
                        </div>
                    </div>

                </div>
            </div>

            {{-- RETRY FAILED --}}
            <div class="card border-0 shadow-sm mt-3" id="retryCard" style="{{ $stats['failed'] > 0 ? '' : 'display:none' }}">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="fw-semibold small">Failed Orders</div>
                            <div class="text-muted" style="font-size:12px"><span id="failedCount">{{ $stats['failed'] }}</span> orders failed — retry karo</div>
                        </div>
                        <button class="btn btn-danger btn-sm" onclick="retryFailed()">
                            ↺ Retry All
                        </button>
                    </div>
                </div>
            </div>

        </div>

        {{-- RIGHT — LIVE STATUS + ORDER LIST --}}
        <div class="col-lg-7">

            {{-- SYNC STATUS --}}
            <div id="syncStatus" style="display:none" class="mb-3">
                <div class="alert alert-info d-flex align-items-center gap-2 mb-0">
                    <div class="spinner-border spinner-border-sm" role="status"></div>
                    <span id="syncStatusText">Syncing orders...</span>
                </div>
            </div>

            {{-- SYNC RESULT --}}
            <div id="syncResult" style="display:none" class="mb-3"></div>

            {{-- ORDERS PREVIEW TABLE --}}
            <div class="card border-0 shadow-sm" id="ordersCard" style="display:none">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="mb-0 fw-bold">Orders to Sync <span class="badge bg-primary ms-1" id="ordersCount">0</span></h6>
                </div>
                <div class="card-body p-0">
                    <div style="max-height:380px;overflow-y:auto">
                        <table class="table table-sm mb-0 table-hover">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th class="ps-3 small">Order #</th>
                                    <th class="small">Customer</th>
                                    <th class="small">Date</th>
                                    <th class="small text-end pe-3">Amount</th>
                                </tr>
                            </thead>
                            <tbody id="ordersTableBody">
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- RECENT SUCCESS --}}
            <div class="card border-0 shadow-sm mt-3">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold">Recently Synced</h6>
                    <span class="badge bg-success" id="recentCount">—</span>
                </div>
                <div class="card-body p-0">
                    <div id="recentSyncedList" style="max-height:260px;overflow-y:auto">
                        <div class="text-center text-muted py-4 small">Refresh karo to load</div>
                    </div>
                </div>
            </div>

        </div>
    </div>

</div>

@endsection

@push('scripts')
<script>

// ─── Quick date range helpers ───────────────────────────────────────────────

function setRange(type) {
    const today = new Date();
    let from, to;

    to = today.toISOString().split('T')[0];

    if (type === 'today') {
        from = to;
    } else if (type === 'week') {
        const d = new Date(today);
        d.setDate(d.getDate() - d.getDay() + 1);
        from = d.toISOString().split('T')[0];
    } else if (type === 'month') {
        from = new Date(today.getFullYear(), today.getMonth(), 1)
            .toISOString().split('T')[0];
    } else if (type === 'lastmonth') {
        const first = new Date(today.getFullYear(), today.getMonth() - 1, 1);
        const last  = new Date(today.getFullYear(), today.getMonth(), 0);
        from = first.toISOString().split('T')[0];
        to   = last.toISOString().split('T')[0];
    }

    document.getElementById('fromDate').value = from;
    document.getElementById('toDate').value   = to;
    document.getElementById('syncBtn').disabled = true;
    document.getElementById('previewResult').style.display = 'none';
    document.getElementById('ordersCard').style.display    = 'none';
}

// ─── Preview ────────────────────────────────────────────────────────────────

async function previewOrders() {
    const btn = document.getElementById('previewBtn');
    console.log(btn);
    btn.disabled = true;
    btn.textContent = '⏳ Fetching...';

    document.getElementById('syncBtn').disabled = true;
    document.getElementById('syncResult').style.display = 'none';

    try {
        const res = await fetch('/admin/bulk-sync/preview', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({
                from:   document.getElementById('fromDate').value,
                to:     document.getElementById('toDate').value,
                status: document.getElementById('orderStatus').value,
            }),
        });

        const data = await res.json();

        if (!data.success) {
            alert('Error: ' + data.message);
            return;
        }

        // Show summary
        document.getElementById('previewTotal').textContent  = data.total_orders;
        document.getElementById('previewSynced').textContent = data.already_synced;
        document.getElementById('previewQueued').textContent = data.to_be_queued;
        document.getElementById('previewResult').style.display = 'block';

        // Show orders table
        if (data.orders.length > 0) {
            renderOrdersTable(data.orders, data.to_be_queued);
        }

        // Enable sync button if there's something to sync
        if (data.to_be_queued > 0) {
            document.getElementById('syncBtn').disabled = false;
        }

    } catch (e) {
        alert('Network error: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.textContent = '🔍 Preview Orders';
    }
}

function renderOrdersTable(orders, total) {
    const tbody = document.getElementById('ordersTableBody');
    const card  = document.getElementById('ordersCard');
    const count = document.getElementById('ordersCount');

    count.textContent = total + (total > 50 ? ' (showing 50)' : '');

    tbody.innerHTML = orders.map(o => `
        <tr>
            <td class="ps-3 small fw-semibold">#${o.number}</td>
            <td class="small text-truncate" style="max-width:140px">${o.customer || '—'}</td>
            <td class="small text-muted">${o.date}</td>
            <td class="small text-end pe-3">₹${parseFloat(o.total).toLocaleString('en-IN', {minimumFractionDigits:2})}</td>
        </tr>
    `).join('');

    card.style.display = 'block';
}

// ─── Sync ────────────────────────────────────────────────────────────────────

async function startSync() {
    if (!confirm('Sync shuru kare? Sabhi pending orders queue mein daal diye jayenge.')) return;

    const btn    = document.getElementById('syncBtn');
    const status = document.getElementById('syncStatus');
    const result = document.getElementById('syncResult');

    btn.disabled = true;
    document.getElementById('previewBtn').disabled = true;
    status.style.display = 'block';
    result.style.display = 'none';
    document.getElementById('syncStatusText').textContent = 'Orders fetch ho rahe hain WooCommerce se...';

    try {
        const res = await fetch('/admin/bulk-sync/sync', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({
                from:   document.getElementById('fromDate').value,
                to:     document.getElementById('toDate').value,
                status: document.getElementById('orderStatus').value,
            }),
        });

        const data = await res.json();

        status.style.display = 'none';

        if (data.success) {
            result.innerHTML = `
                <div class="alert alert-success mb-0">
                    <strong>✅ Sync Complete!</strong><br>
                    <span class="small">
                        ${data.queued} orders queued •
                        ${data.skipped} skipped (already synced) •
                        ${data.failed} failed
                    </span>
                    <div class="mt-2 small text-muted">
                        Queue worker chal raha hai toh vouchers automatically create ho jayenge.<br>
                        Status check karne ke liye <strong>Refresh Status</strong> click karo.
                    </div>
                </div>
            `;
        } else {
            result.innerHTML = `<div class="alert alert-danger mb-0">❌ ${data.message}</div>`;
        }

        result.style.display = 'block';

        // Refresh stats after sync
        setTimeout(loadProgress, 2000);

    } catch (e) {
        status.style.display = 'none';
        result.innerHTML = `<div class="alert alert-danger mb-0">❌ Network error: ${e.message}</div>`;
        result.style.display = 'block';
    } finally {
        document.getElementById('previewBtn').disabled = false;
    }
}

// ─── Progress / Stats ────────────────────────────────────────────────────────

async function loadProgress() {
    const btn = document.getElementById('refreshBtn');
    btn.textContent = '⏳ Loading...';
    btn.disabled = true;

    try {
        const res  = await fetch('/admin/bulk-sync/progress');
        const data = await res.json();

        // Update stat cards
        document.getElementById('stat-total').textContent   = data.stats.total;
        document.getElementById('stat-success').textContent = data.stats.success;
        document.getElementById('stat-pending').textContent = data.stats.pending + data.stats.processing;
        document.getElementById('stat-failed').textContent  = data.stats.failed;

        // Retry card
        const retryCard = document.getElementById('retryCard');
        if (data.stats.failed > 0) {
            document.getElementById('failedCount').textContent = data.stats.failed;
            retryCard.style.display = 'block';
        } else {
            retryCard.style.display = 'none';
        }

        // Recent synced list
        renderRecentSynced(data.recent_success);

    } catch (e) {
        console.error('Progress load error:', e);
    } finally {
        btn.textContent = '↻ Refresh Status';
        btn.disabled = false;
    }
}

function renderRecentSynced(orders) {
    const list = document.getElementById('recentSyncedList');
    const count = document.getElementById('recentCount');

    count.textContent = orders.length;

    if (!orders.length) {
        list.innerHTML = '<div class="text-center text-muted py-3 small">No synced orders yet</div>';
        return;
    }

    list.innerHTML = orders.map(o => `
        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
            <div>
                <div class="small fw-semibold">#${o.order_number}</div>
                <div class="text-muted" style="font-size:11px">${o.customer_name}</div>
            </div>
            <div class="text-end">
                <div class="small fw-semibold text-success">₹${parseFloat(o.amount).toLocaleString('en-IN', {minimumFractionDigits:2})}</div>
                <div class="text-muted" style="font-size:11px">${o.synced_at ? new Date(o.synced_at).toLocaleString('en-IN') : '—'}</div>
            </div>
        </div>
    `).join('');
}

// ─── Retry Failed ────────────────────────────────────────────────────────────

async function retryFailed() {
    if (!confirm('Saare failed orders retry karein?')) return;

    try {
        const res  = await fetch('/admin/bulk-sync/retry-failed', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
        });
        const data = await res.json();
        alert(data.message);
        loadProgress();
    } catch (e) {
        alert('Error: ' + e.message);
    }
}

// ─── Auto-refresh while jobs pending ────────────────────────────────────────

let autoRefreshTimer = null;

function startAutoRefresh() {
    if (autoRefreshTimer) return;
    autoRefreshTimer = setInterval(() => {
        const pending = parseInt(document.getElementById('stat-pending').textContent);
        if (pending > 0) {
            loadProgress();
        } else {
            clearInterval(autoRefreshTimer);
            autoRefreshTimer = null;
        }
    }, 5000); // every 5 seconds
}

// Load progress on page load
document.addEventListener('DOMContentLoaded', () => {
    loadProgress();
    startAutoRefresh();
});

</script>
@endpush
