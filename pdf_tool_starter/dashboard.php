<?php
require __DIR__ . '/config.php';
require_login();

$vipInfo = sync_user_vip_status();
$role = $vipInfo['role'];
$limits = get_user_limits($role);
$vipExpiresAt = $vipInfo['vip_expires_at'];
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PDF Studio · Bảng điều khiển</title>
    <link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__ . '/assets/style.css') ?>">
    <link rel="stylesheet" href="assets/vip-checkout.css?v=<?= filemtime(__DIR__ . '/assets/vip-checkout.css') ?>">
    <style>
        .container.compact-container {
            max-width: 1260px;
            padding: 16px clamp(16px, 3vw, 36px) 30px;
        }
        .hero.compact-hero {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 16px;
        }
        .hero-title-group h1 {
            font-size: 22px;
            font-weight: 800;
            margin: 0;
            letter-spacing: -0.02em;
        }
        .hero-title-group .hero-meta {
            font-size: 12.5px;
            color: var(--muted);
            margin: 4px 0 0;
        }
        .hero-actions-group {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }
        
        /* 3 Thẻ thống kê kiêm Navigation Tabs */
        .dashboard-tabs-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 16px;
        }
        .dashboard-tab-card {
            background: #ffffff;
            border: 1.5px solid var(--line);
            border-radius: 14px;
            padding: 12px 18px;
            display: flex;
            align-items: center;
            gap: 14px;
            cursor: pointer;
            position: relative;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            user-select: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
        }
        .dashboard-tab-card:hover {
            border-color: #fca5a5;
            background: #fffafa;
            transform: translateY(-2px);
            box-shadow: 0 4px 14px rgba(220, 38, 38, 0.08);
        }
        .dashboard-tab-card.active {
            border-color: #dc2626;
            background: #ffffff;
            box-shadow: 0 4px 18px rgba(220, 38, 38, 0.12);
        }
        .dashboard-tab-card .tab-indicator {
            position: absolute;
            bottom: -1.5px;
            left: 20px;
            right: 20px;
            height: 3px;
            border-radius: 3px 3px 0 0;
            background: transparent;
            transition: background 0.2s ease;
        }
        .dashboard-tab-card.active .tab-indicator {
            background: #dc2626;
        }
        .tab-card-info p {
            font-size: 12px;
            color: var(--muted);
            margin: 0 0 2px;
            font-weight: 600;
        }
        .tab-card-info h2 {
            font-size: 22px;
            margin: 0;
            font-weight: 800;
        }
        .tab-icon-red {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fee2e2;
        }
        .tab-icon-blue {
            background: #eff6ff;
            color: #2563eb;
            border: 1px solid #dbeafe;
        }
        .tab-icon-green {
            background: #f0fdf4;
            color: #16a34a;
            border: 1px solid #dcfce7;
        }

        /* Panel chính & thanh cuộn gọn gàng */
        .compact-main-panel {
            padding: 18px 20px;
            margin-bottom: 0;
            border-radius: 16px;
        }
        .compact-section-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--line);
        }
        .compact-section-head h2 {
            font-size: 16px;
            margin: 0;
        }
        .compact-section-head p {
            font-size: 11.5px;
            margin: 2px 0 0;
        }
        .compact-table-scroll {
            max-height: 380px;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 #f8fafc;
        }
        .compact-table-scroll::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        .compact-table-scroll::-webkit-scrollbar-track {
            background: #f8fafc;
        }
        .compact-table-scroll::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        .compact-table-scroll::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Bảng dữ liệu Compact */
        table.compact-table {
            border-collapse: collapse;
            width: 100%;
            text-align: left;
        }
        table.compact-table th {
            padding: 9px 12px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            background: #fbfcfe;
            position: sticky;
            top: 0;
            z-index: 2;
            border-bottom: 1px solid var(--line);
        }
        table.compact-table td {
            padding: 9px 12px;
            font-size: 12.5px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        table.compact-table tr:hover td {
            background: #fafbfe;
        }
        table.compact-table tr:last-child td {
            border-bottom: 0;
        }

        /* Cụm Action Buttons */
        .compact-action-btn {
            width: 30px;
            height: 30px;
            border-radius: 7px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--line);
            background: #ffffff;
            color: #475569;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .compact-action-btn:hover {
            border-color: #cbd5e1;
            background: #f8fafc;
            color: #0f172a;
            transform: translateY(-1px);
        }
        .compact-action-btn.view:hover {
            color: #0284c7;
            border-color: #bae6fd;
            background: #f0f9ff;
        }
        .compact-action-btn.download:hover {
            color: #16a34a;
            border-color: #bbf7d0;
            background: #f0fdf4;
        }
        .compact-action-btn.delete {
            color: #dc2626;
            border-color: #fee2e2;
            background: #fef2f2;
        }
        .compact-action-btn.delete:hover {
            color: #ffffff;
            border-color: #dc2626;
            background: #dc2626;
        }

        @media (max-width: 768px) {
            .hero.compact-hero {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
            .dashboard-tabs-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php if (!empty($_SESSION['admin_impersonator_id'])): ?>
        <div style="background: #dc2626; color: #ffffff; padding: 9px 20px; font-size: 13px; font-weight: 600; display: flex; align-items: center; justify-content: space-between; gap: 12px; position: sticky; top: 0; z-index: 99999; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
            <span>⚠️ Đang truy cập với tư cách người dùng: <strong><?= htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong> (Admin: <?= htmlspecialchars($_SESSION['admin_impersonator_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8') ?>)</span>
            <a href="api.php?action=exit_impersonate" class="btn small-btn" style="background: #ffffff; color: #b91c1c; font-weight: 700; text-decoration: none; padding: 4px 12px; border-radius: 6px; border: none;">
                🔙 Thoát mạo danh & Về Admin
            </a>
        </div>
    <?php endif; ?>
    <header class="topbar">
        <a class="brand small" href="dashboard.php">
            <span class="brand-icon">P</span>
            <span>PDF<span class="brand-light">Studio</span></span>
        </a>
        <div class="top-actions">
            <?php if (is_admin()): ?>
                <a class="btn secondary" href="admin.php">⚙ Quản trị hệ thống</a>
            <?php endif; ?>

            <?php if (!is_pro()): ?>
                <button class="btn vip-btn" onclick="openPaymentModal()">⭐ Nâng cấp VIP (Pro)</button>
            <?php else: ?>
                <?php if (!empty($vipExpiresAt)): ?>
                    <span class="user-pill" title="Thời hạn gói Pro">
                        👑 VIP đến: <?= date('d/m/Y', strtotime($vipExpiresAt)) ?>
                    </span>
                    <button class="btn ghost" onclick="openPaymentModal()" style="font-size:12px; padding:6px 10px;">Gia hạn</button>
                <?php endif; ?>
            <?php endif; ?>

            <span class="user-pill">
                ● <?= htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8') ?>
                <span class="badge-tag <?= htmlspecialchars($limits['badge_class']) ?>">
                    <?= htmlspecialchars($limits['label']) ?>
                </span>
            </span>
            <a class="btn ghost" href="logout.php">Đăng xuất</a>
        </div>
    </header>

    <main class="container compact-container">
        <!-- Header & Gom cụm hành động nhanh -->
        <section class="hero compact-hero">
            <div class="hero-title-group">
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <h1>Quản lý tài liệu PDF</h1>
                    <span class="user-pill" style="padding: 3px 10px; font-size: 11px;">
                        <?= htmlspecialchars($limits['label']) ?>
                    </span>
                </div>
                <p class="hero-meta">
                    Gói tài khoản: <strong><?= htmlspecialchars($limits['label']) ?></strong> · 
                    Giới hạn: <strong><?= $limits['max_file_mb'] ?> MB/tệp</strong> · 
                    <strong><?= $limits['max_pages'] ?> trang/tệp</strong> · 
                    <strong><?= $limits['max_files'] ?> tệp/lần tải</strong>.
                </p>
            </div>
            <div class="hero-actions-group">
                <a href="editor.php" class="btn secondary small-btn" style="padding: 8px 14px; font-size: 12.5px;">
                    ↑ Tải tệp PDF
                </a>
                <a href="editor.php" class="btn primary small-btn" style="padding: 8px 16px; font-size: 12.5px;">
                    ＋ Mở trình xử lý PDF
                </a>
            </div>
        </section>

        <!-- 3 Thẻ thống kê kiêm Navigation Tabs chuyển đổi -->
        <section class="dashboard-tabs-grid">
            <div class="dashboard-tab-card active" id="tabCard_docs" onclick="switchDashboardTab('docs')">
                <span class="stat-icon tab-icon-red">📥</span>
                <div class="tab-card-info">
                    <p>Tài liệu nguồn</p>
                    <h2 id="docCount">—</h2>
                </div>
                <span class="tab-indicator"></span>
            </div>

            <div class="dashboard-tab-card" id="tabCard_plans" onclick="switchDashboardTab('plans')">
                <span class="stat-icon tab-icon-blue">↗</span>
                <div class="tab-card-info">
                    <p>Kế hoạch đã lưu</p>
                    <h2 id="planCount">—</h2>
                </div>
                <span class="tab-indicator"></span>
            </div>

            <div class="dashboard-tab-card" id="tabCard_outputs" onclick="switchDashboardTab('outputs')">
                <span class="stat-icon tab-icon-green">✓</span>
                <div class="tab-card-info">
                    <p>Tệp đã xuất</p>
                    <h2 id="outCount">—</h2>
                </div>
                <span class="tab-indicator"></span>
            </div>
        </section>

        <!-- Khung nội dung duy nhất theo Tab đang chọn -->
        <section class="panel compact-main-panel">
            <!-- TAB 1: Tài liệu nguồn -->
            <div id="tabContent_docs" class="dashboard-tab-content">
                <div class="compact-section-head">
                    <div>
                        <h2>Tài liệu nguồn</h2>
                        <p class="muted">Tệp PDF gốc được lưu trữ riêng theo tài khoản và không bị ghi đè.</p>
                    </div>
                    <a href="editor.php" class="text-link">Mở vào Workspace xử lý →</a>
                </div>
                <div id="documents" class="compact-table-scroll">
                    <p class="muted">Đang tải dữ liệu…</p>
                </div>
            </div>

            <!-- TAB 2: Kế hoạch đã lưu -->
            <div id="tabContent_plans" class="dashboard-tab-content" style="display: none;">
                <div class="compact-section-head">
                    <div>
                        <h2>Kế hoạch đã lưu</h2>
                        <p class="muted">Lưu trạng thái sắp xếp, xoay trang và ghép để tiếp tục làm việc bất cứ lúc nào.</p>
                    </div>
                    <a href="editor.php" class="text-link">Tạo kế hoạch mới →</a>
                </div>
                <div id="plans" class="compact-table-scroll">
                    <p class="muted">Đang tải dữ liệu…</p>
                </div>
            </div>

            <!-- TAB 3: Tệp đã xuất -->
            <div id="tabContent_outputs" class="dashboard-tab-content" style="display: none;">
                <div class="compact-section-head">
                    <div>
                        <h2>Tệp đã xuất</h2>
                        <p class="muted">Lịch sử các lần kết xuất PDF kèm hạn lưu trữ theo gói tài khoản.</p>
                    </div>
                    <a href="editor.php" class="text-link">Xuất tệp mới →</a>
                </div>
                <div id="outputs" class="compact-table-scroll">
                    <p class="muted">Đang tải dữ liệu…</p>
                </div>
            </div>
        </section>
    </main>

    <script>
        function esc(str) {
            return String(str ?? '').replace(/[&<>"']/g, char => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            }[char]));
        }

        function formatDateTime(dateString) {
            if (!dateString) return '';
            const date = new Date(String(dateString).replace(/-/g, '/'));
            if (isNaN(date.getTime())) return String(dateString);
            const time = date.toLocaleTimeString('vi-VN', { hour12: false });
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const year = date.getFullYear();
            return `${time} ${day}/${month}/${year}`;
        }

        function switchDashboardTab(tabName) {
            document.querySelectorAll('.dashboard-tab-card').forEach(c => c.classList.remove('active'));
            const activeCard = document.getElementById(`tabCard_${tabName}`);
            if (activeCard) activeCard.classList.add('active');

            document.querySelectorAll('.dashboard-tab-content').forEach(c => {
                c.style.display = 'none';
            });
            const activeContent = document.getElementById(`tabContent_${tabName}`);
            if (activeContent) {
                activeContent.style.display = 'block';
            }
        }

        async function loadDashboardData() {
            try {
                const response = await fetch('api.php?action=list');
                const data = await response.json();

                if (!data.ok) {
                    throw new Error(data.error);
                }

                // Cập nhật số đếm thống kê
                document.getElementById('docCount').textContent = data.documents.length;
                document.getElementById('planCount').textContent = data.plans.length;
                document.getElementById('outCount').textContent = data.outputs.length;

                // 1. Hiển thị danh sách tài liệu nguồn
                const docContainer = document.getElementById('documents');
                if (data.documents.length) {
                    docContainer.innerHTML = `
                        <table class="compact-table">
                            <thead>
                                <tr>
                                    <th style="width: 44%;">Tên tệp gốc</th>
                                    <th style="width: 13%;">Số trang</th>
                                    <th style="width: 14%;">Dung lượng</th>
                                    <th style="width: 17%;">Ngày tải</th>
                                    <th style="width: 12%; text-align: right;">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${data.documents.map(item => `
                                    <tr>
                                        <td>
                                            <span class="file-mark">PDF</span>
                                            <b style="font-size: 13px;" title="${esc(item.original_name)}">${esc(item.original_name)}</b>
                                        </td>
                                        <td><span class="badge-tag" style="background:#f1f5f9; color:#475569; margin:0;">${item.page_count} trang</span></td>
                                        <td>${(item.file_size / 1024 / 1024).toFixed(2)} MB</td>
                                        <td><small class="muted">${esc(formatDateTime(item.created_at))}</small></td>
                                        <td>
                                            <div style="display: flex; align-items: center; justify-content: flex-end; gap: 5px;">
                                                <a href="api.php?action=file&id=${item.id}" target="_blank" rel="noopener noreferrer" class="compact-action-btn view" title="Xem trước PDF trong tab mới">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                                </a>
                                                <a href="api.php?action=file&id=${item.id}" download="${esc(item.original_name)}" class="compact-action-btn download" title="Tải về tệp nguồn">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                                </a>
                                                <button type="button" class="compact-action-btn delete" onclick="deleteDocument(${item.id})" title="Xóa tài liệu">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    `;
                } else {
                    docContainer.innerHTML = '<p class="empty" style="padding: 36px 12px;">Chưa có tài liệu nguồn. Nhấn <b>"Tải tệp PDF"</b> hoặc <b>"Mở trình xử lý"</b> để bắt đầu tải PDF đầu tiên.</p>';
                }

                // 2. Hiển thị danh sách kế hoạch đã lưu
                const planContainer = document.getElementById('plans');
                if (data.plans.length) {
                    planContainer.innerHTML = `
                        <table class="compact-table">
                            <thead>
                                <tr>
                                    <th style="width: 50%;">Tên kế hoạch</th>
                                    <th style="width: 15%;">Phiên bản</th>
                                    <th style="width: 20%;">Cập nhật lần cuối</th>
                                    <th style="width: 15%; text-align: right;">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${data.plans.map(item => `
                                    <tr>
                                        <td>
                                            <span class="file-mark purple-mark">PL</span>
                                            <b style="font-size: 13px;" title="${esc(item.plan_name)}">${esc(item.plan_name)}</b>
                                        </td>
                                        <td><span class="badge-tag" style="background:#eff6ff; color:#1d4ed8; margin:0;">v${item.version_no}</span></td>
                                        <td><small class="muted">${esc(formatDateTime(item.updated_at))}</small></td>
                                        <td style="text-align: right;">
                                            <a class="btn primary small-btn" href="editor.php?plan=${item.id}" style="padding: 5px 12px; font-size: 12px;">
                                                Mở tiếp tục →
                                            </a>
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    `;
                } else {
                    planContainer.innerHTML = '<p class="empty" style="padding: 36px 12px;">Chưa có kế hoạch nào được lưu. Bạn có thể lưu kế hoạch sắp xếp trang khi đang thao tác trong Workspace.</p>';
                }

                // 3. Hiển thị danh sách tệp đã xuất (kèm trạng thái hạn lưu trữ)
                const outContainer = document.getElementById('outputs');
                if (data.outputs.length) {
                    outContainer.innerHTML = `
                        <table class="compact-table">
                            <thead>
                                <tr>
                                    <th style="width: 42%;">Tên tệp xuất</th>
                                    <th style="width: 13%;">Dung lượng</th>
                                    <th style="width: 18%;">Thời gian xuất</th>
                                    <th style="width: 15%;">Hạn lưu trữ</th>
                                    <th style="width: 12%; text-align: right;">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${data.outputs.map(item => {
                                    let expiryTag = '<span style="color:#16855f; font-weight:700;">✓ Lưu vĩnh viễn</span>';
                                    let isExpired = false;

                                    if (item.expires_at) {
                                        const expDate = new Date(String(item.expires_at).replace(/-/g, '/'));
                                        const now = new Date();
                                        const diffHours = Math.round((expDate - now) / (1000 * 60 * 60));
                                        if (diffHours <= 0) {
                                            expiryTag = '<span style="color:#c34545; font-weight:700;">Đã hết hạn</span>';
                                            isExpired = true;
                                        } else {
                                            const days = Math.ceil(diffHours / 24);
                                            expiryTag = `<span style="color:#d97706; font-weight:700;">⏳ Còn ${days} ngày</span>`;
                                        }
                                    }

                                    return `
                                        <tr>
                                            <td>
                                                <span class="file-mark green-mark">PDF</span>
                                                <b style="font-size: 13px;" title="${esc(item.output_name)}">${esc(item.output_name)}</b>
                                            </td>
                                            <td>${(item.file_size / 1024 / 1024).toFixed(2)} MB</td>
                                            <td><small class="muted">${esc(formatDateTime(item.created_at))}</small></td>
                                            <td><small>${expiryTag}</small></td>
                                            <td>
                                                <div style="display: flex; align-items: center; justify-content: flex-end; gap: 5px;">
                                                    ${isExpired 
                                                        ? '<span class="muted" style="font-size:12px;">Hết hạn</span>' 
                                                        : `
                                                            <a href="api.php?action=download&id=${item.id}" target="_blank" class="compact-action-btn view" title="Xem trước trong tab mới">
                                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                                            </a>
                                                            <a href="api.php?action=download&id=${item.id}" class="compact-action-btn download" title="Tải xuống tệp PDF">
                                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                                            </a>
                                                        `
                                                    }
                                                </div>
                                            </td>
                                        </tr>
                                    `;
                                }).join('')}
                            </tbody>
                        </table>
                    `;
                } else {
                    outContainer.innerHTML = '<p class="empty" style="padding: 36px 12px;">Chưa có tệp xuất. Sau khi ghép hoặc xuất tệp trong Workspace, lịch sử tệp xuất sẽ lưu tại đây.</p>';
                }
            } catch (err) {
                const errHtml = '<p class="alert error" style="margin: 12px 0;">Không tải được dữ liệu. Vui lòng kiểm tra kết nối MySQL.</p>';
                document.getElementById('documents').innerHTML = errHtml;
                document.getElementById('plans').innerHTML = errHtml;
                document.getElementById('outputs').innerHTML = errHtml;
            }
        }

        let pendingDeleteDocId = null;

        function deleteDocument(id) {
            pendingDeleteDocId = id;
            const m = document.getElementById('confirmDeleteModal');
            if (m) {
                m.style.display = 'flex';
                requestAnimationFrame(() => m.classList.add('open'));
            }
        }

        function closeConfirmDeleteModal() {
            pendingDeleteDocId = null;
            const m = document.getElementById('confirmDeleteModal');
            if (m) {
                m.classList.remove('open');
                setTimeout(() => {
                    if (!m.classList.contains('open')) m.style.display = 'none';
                }, 200);
            }
        }

        function handleDeleteModalBackdropClick(e) {
            if (e.target && e.target.id === 'confirmDeleteModal') {
                closeConfirmDeleteModal();
            }
        }

        async function executeDeleteDocument() {
            if (!pendingDeleteDocId) return;
            const id = pendingDeleteDocId;
            const btn = document.getElementById('confirmDeleteSubmitBtn');
            const originalText = btn ? btn.textContent : '';
            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Đang xóa...';
            }

            try {
                const formData = new FormData();
                formData.append('id', id);

                const response = await fetch('api.php?action=delete_document', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                closeConfirmDeleteModal();
                if (!result.ok) {
                    alert(result.error || 'Có lỗi xảy ra khi xóa tài liệu.');
                }
                loadDashboardData();
            } catch (err) {
                closeConfirmDeleteModal();
                alert('Không thể kết nối đến máy chủ.');
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = originalText;
                }
            }
        }

        // Tự động tải dữ liệu ban đầu
        loadDashboardData();

        // Tự động mở modal thanh toán nếu có tham số ?action=upgrade hoặc ?upgrade=1
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('action') === 'upgrade' || urlParams.get('upgrade') === '1') {
            setTimeout(openPaymentModal, 200);
        }

        // ==========================================
        // Smallpdf-style Checkout Modal & Payment Polling
        // ==========================================
        let activeOrderCode = null;
        let pollTimer = null;

        function openPaymentModal() {
            document.getElementById('vipModalBackdrop').classList.add('open');
            selectPlan('yearly'); // Mặc định chọn gói năm tiết kiệm 25% giống Smallpdf
        }

        function closePaymentModal() {
            document.getElementById('vipModalBackdrop').classList.remove('open');
            if (pollTimer) {
                clearInterval(pollTimer);
                pollTimer = null;
            }
        }

        async function selectPlan(type) {
            document.querySelectorAll('.plan-option-card').forEach(el => el.classList.remove('active'));
            const card = document.getElementById('planCard_' + type);
            if (card) {
                card.classList.add('active');
                card.querySelector('input[type="radio"]').checked = true;
            }

            const qrContainer = document.getElementById('qrSection');
            qrContainer.innerHTML = '<div class="vc-state"><span class="vc-spinner"></span>Đang tạo mã thanh toán VietQR...</div>';

            if (pollTimer) {
                clearInterval(pollTimer);
                pollTimer = null;
            }

            try {
                const formData = new FormData();
                formData.append('plan_type', type);
                formData.append('payment_method', 'PAYOS');

                const res = await fetch('api.php?action=create_order', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (!data.ok) {
                    qrContainer.innerHTML = `<div class="vc-state"><div class="vc-error">${esc(data.error)}</div></div>`;
                    return;
                }

                const order = data.order;
                activeOrderCode = order.order_code;

                qrContainer.innerHTML = `
                    <p class="qr-heading">Quét mã để thanh toán</p>
                    <p class="qr-subheading">Dùng app ngân hàng bất kỳ hỗ trợ VietQR</p>

                    <div class="qr-box">
                        <img src="${order.qr_url}" alt="Mã VietQR thanh toán">
                    </div>

                    <div class="transfer-info-card">
                        <div class="transfer-row">
                            <span class="transfer-label">Ngân hàng</span>
                            <span class="transfer-val">${esc(order.bank_id)}</span>
                        </div>
                        <div class="transfer-row">
                            <span class="transfer-label">Chủ tài khoản</span>
                            <span class="transfer-val">${esc(order.account_name)}</span>
                        </div>
                        <div class="transfer-row">
                            <span class="transfer-label">Số tài khoản</span>
                            <span class="transfer-val">
                                ${esc(order.account_no)}
                                <button type="button" class="copy-badge" onclick="copyText(this, '${esc(order.account_no)}')">Copy</button>
                            </span>
                        </div>
                        <div class="transfer-row">
                            <span class="transfer-label">Số tiền</span>
                            <span class="transfer-val is-amount">${esc(order.amount_formatted)}</span>
                        </div>
                        <div class="transfer-row">
                            <span class="transfer-label">Nội dung</span>
                            <span class="transfer-val is-content">
                                ${esc(order.transfer_content)}
                                <button type="button" class="copy-badge" onclick="copyText(this, '${esc(order.transfer_content)}')">Copy</button>
                            </span>
                        </div>
                    </div>

                    <div class="polling-indicator" id="pollStatus">
                        <span class="pulse-dot"></span>
                        Đang chờ xác nhận thanh toán...
                    </div>
                    <p class="checkout-note">Vui lòng giữ nguyên nội dung chuyển khoản để hệ thống tự động kích hoạt.</p>
                `;

                // Bắt đầu Polling kiểm tra trạng thái đơn hàng mỗi 2.5 giây
                startPolling(activeOrderCode);

            } catch (err) {
                qrContainer.innerHTML = '<div class="vc-state"><div class="vc-error">Không kết nối được máy chủ. Vui lòng thử lại.</div></div>';
            }
        }

        function copyText(btn, str) {
            navigator.clipboard.writeText(str).then(() => {
                btn.textContent = 'Đã chép ✓';
                btn.classList.add('copied');
                setTimeout(() => {
                    btn.textContent = 'Copy';
                    btn.classList.remove('copied');
                }, 1500);
            });
        }

        function startPolling(orderCode) {
            if (pollTimer) clearInterval(pollTimer);

            pollTimer = setInterval(async () => {
                try {
                    const res = await fetch(`api.php?action=check_order&order_code=${orderCode}`);
                    const data = await res.json();

                    if (data.ok && data.is_success) {
                        clearInterval(pollTimer);
                        pollTimer = null;

                        const qrContainer = document.getElementById('qrSection');
                        qrContainer.innerHTML = `
                            <div class="success-card">
                                <span class="success-icon-big">✓</span>
                                <h3>Thanh toán thành công!</h3>
                                <p>Tài khoản của bạn đã được nâng cấp lên <strong>Pro</strong>.</p>
                                <button class="vc-btn-primary" onclick="window.location.reload()">Bắt đầu sử dụng</button>
                            </div>
                        `;
                    }
                } catch (e) {
                    console.error('Polling error', e);
                }
            }, 2500);
        }
    </script>

    <!-- Smallpdf-style VIP Checkout Modal -->
    <div id="vipModalBackdrop" class="modal-backdrop">
        <div class="vip-checkout-modal">
            <button class="modal-close-btn" onclick="closePaymentModal()" title="Đóng">&times;</button>
            
            <div class="checkout-left">
                <span class="checkout-eyebrow">★ PDF STUDIO PRO</span>
                <h2>Nâng cấp tài khoản Pro</h2>
                <p class="sub">Mở khóa toàn bộ tính năng cao cấp và xử lý không giới hạn.</p>

                <div class="plan-selection-group">
                    <label class="plan-option-card active" id="planCard_yearly">
                        <div class="plan-option-left">
                            <input type="radio" name="plan" value="yearly" checked onchange="selectPlan('yearly')">
                            <div>
                                <div class="plan-title">Gói 1 Năm <span class="plan-badge">TIẾT KIỆM 25%</span></div>
                                <div class="plan-desc">Chỉ ~74.900 đ / tháng</div>
                            </div>
                        </div>
                        <div class="plan-price">
                            899.000 đ
                            <div class="plan-price-sub">/ năm</div>
                        </div>
                    </label>

                    <label class="plan-option-card" id="planCard_monthly">
                        <div class="plan-option-left">
                            <input type="radio" name="plan" value="monthly" onchange="selectPlan('monthly')">
                            <div>
                                <div class="plan-title">Gói 1 Tháng</div>
                                <div class="plan-desc">Linh hoạt từng tháng</div>
                            </div>
                        </div>
                        <div class="plan-price">
                            99.000 đ
                            <div class="plan-price-sub">/ tháng</div>
                        </div>
                    </label>
                </div>

                <ul class="pro-features-list">
                    <li><span class="check">✓</span> Lưu trữ tệp xuất <strong>vĩnh viễn</strong> (Free: 7 ngày)</li>
                    <li><span class="check">✓</span> Dung lượng tải lên tới <strong>100 MB / tệp</strong> (Free: 20 MB)</li>
                    <li><span class="check">✓</span> Xử lý tài liệu tới <strong>500 trang / tệp</strong></li>
                    <li><span class="check">✓</span> Đánh số trang tự động, Watermark bản quyền & Đặt mật khẩu</li>
                    <li><span class="check">✓</span> Hỗ trợ thanh toán tự động qua <strong>VietQR / PayOS / SePay</strong></li>
                </ul>
            </div>

            <div class="checkout-right">
                <div id="qrSection">
                    <!-- Render mã QR & thông tin chuyển khoản tại đây -->
                </div>
            </div>
        </div>
    </div>

    <!-- Custom Modal: Xác nhận xóa tài liệu nguồn -->
    <div id="confirmDeleteModal" class="custom-modal-backdrop" style="display: none;" onclick="handleDeleteModalBackdropClick(event)">
        <div class="custom-modal-box delete-modal-box">
            <button type="button" class="custom-modal-close-btn" onclick="closeConfirmDeleteModal()" aria-label="Đóng">✕</button>
            <div class="delete-modal-icon-wrap">
                <span>🗑️</span>
            </div>
            <h3 class="delete-modal-title">Xác nhận xóa tài liệu nguồn?</h3>
            <p class="delete-modal-desc">
                Hành động này sẽ xóa vĩnh viễn tệp gốc khỏi danh sách tài liệu của bạn. Các bản PDF đã xuất trước đó vẫn được giữ nguyên và không bị ảnh hưởng.
            </p>
            <div class="modal-actions-row">
                <button type="button" class="btn ghost" onclick="closeConfirmDeleteModal()">Hủy bỏ</button>
                <button type="button" id="confirmDeleteSubmitBtn" class="btn danger" onclick="executeDeleteDocument()">Xóa tài liệu</button>
            </div>
        </div>
    </div>

    <script>
        // Hỗ trợ đóng modal khi bấm phím ESC
        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closePaymentModal();
                closeConfirmDeleteModal();
            }
        });
    </script>
</body>
</html>
