<?php
declare(strict_types=1);

$pageTitle = 'Dashboard - Shopee';
$scripts = [
    'assets/js/data.js?v=4',
    'assets/js/api.js?v=4',
    'assets/js/app.js?v=4',
    'assets/js/pages/dashboard.js?v=4',
];

require __DIR__ . '/includes/layout/admin_top.php';
?>

<div class="dash-hero">
          <div class="dash-hero-copy">
            <span class="dash-eyebrow">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 17l5-5 4 3 7-8"/><path d="M15 7h5v5"/></svg>
              Tổng quan cửa hàng
            </span>
            <h1>Bảng điều khiển Shopee</h1>
            <p>Số liệu đồng bộ trực tiếp từ cơ sở dữ liệu MySQL qua API — doanh thu không tính đơn đã hủy.</p>
          </div>
          <div class="page-actions">
            <a class="btn" href="reports.php">Xem báo cáo</a>
            <button class="btn btn-primary" id="btnRefresh">
              <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11a8 8 0 1 0-.8 4.5"/><path d="M20 4.5V11h-6.5"/></svg>
              Làm mới dữ liệu
            </button>
          </div>
        </div>

        <div class="stat-grid" id="statGrid"></div>

        <div class="grid-2" id="chartRow">
          <div class="card">
            <div class="card-header">
              <div>
                <div class="card-title">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 20v-6.5M10 20V8M15.5 20v-9M21 20V4.5"/><path d="M2.5 20h19"/></svg>
                  Doanh thu theo ngày
                </div>
                <p class="card-sub">Đơn đã xác nhận trở đi · 14 ngày gần nhất</p>
              </div>
              <span class="badge badge-green">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 17l6-6 4 3 6-7"/><path d="M15 7h5v5"/></svg>
                Trực tiếp
              </span>
            </div>
            <div class="card-body">
              <div id="revenueChart"></div>
            </div>
          </div>
          <div class="card">
            <div class="card-header">
              <div>
                <div class="card-title">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 3v9l6 4"/></svg>
                  Cơ cấu trạng thái đơn
                </div>
                <p class="card-sub">Toàn bộ dữ liệu đơn hàng</p>
              </div>
              <span class="badge badge-orange">Tất cả</span>
            </div>
            <div class="card-body">
              <div id="statusDonut"></div>
            </div>
          </div>
        </div>

        <div class="card" style="margin-top:20px">
          <div class="card-header">
            <div>
              <div class="card-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 10h6M9 14h4"/></svg>
                Đơn hàng gần đây
              </div>
              <p class="card-sub">5 đơn mới nhất trong hệ thống</p>
            </div>
            <a class="btn btn-sm" href="orders.php">Xem tất cả
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h13"/><path d="M13 6l6 6-6 6"/></svg>
            </a>
          </div>
          <div class="card-body flush">
            <div class="table-wrap">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Mã đơn</th>
                    <th>Khách hàng</th>
                    <th>Ngày đặt</th>
                    <th style="text-align:right">Tổng tiền</th>
                    <th>Trạng thái</th>
                  </tr>
                </thead>
                <tbody id="recentOrdersBody"></tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="card" style="margin-top:20px">
          <div class="card-header">
            <div>
              <div class="card-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M18 9a6 6 0 1 0-12 0c0 6-2.5 7-2.5 7h17S18 15 18 9"/><path d="M13.8 20a2 2 0 0 1-3.6 0"/></svg>
                Cần xử lý
              </div>
              <p class="card-sub">Các việc đang chờ thao tác của nhân viên.</p>
            </div>
            <span class="badge badge-orange"><span class="dot" style="width:6px;height:6px;border-radius:50%;background:currentColor"></span> Ưu tiên</span>
          </div>
          <div class="card-body">
            <div class="quick-list" id="pendingList"></div>
          </div>
        </div>

<?php require __DIR__ . '/includes/layout/admin_bottom.php'; ?>
