<?php
require __DIR__ . '/config.php';
require_admin();

$currentUser = $_SESSION['username'] ?? 'Admin';
$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quản trị hệ thống · PDF Studio</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        @keyframes spin-anim {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .spin-icon {
            display: inline-block;
            animation: spin-anim 0.8s linear infinite;
        }
        .btn.loading {
            cursor: wait;
            opacity: 0.85;
            pointer-events: none;
        }

        /* 4 Thẻ thống kê */
        .stats-grid.four-cols {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
        @media (max-width: 992px) {
            .stats-grid.four-cols {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (max-width: 540px) {
            .stats-grid.four-cols {
                grid-template-columns: 1fr;
            }
        }
        .stat-icon.red {
            background: #fef2f2;
            color: #dc2626;
        }

        /* Sub-tabs điều hướng phụ */
        .sub-tabs-nav {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 24px;
            border-bottom: 1px solid var(--line);
            padding-bottom: 12px;
            overflow-x: auto;
        }
        .sub-tab-btn {
            background: transparent;
            border: 1px solid transparent;
            border-radius: 10px;
            padding: 9px 16px;
            font-size: 13.5px;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            white-space: nowrap;
        }
        .sub-tab-btn:hover {
            color: #1e293b;
            background: #f1f5f9;
        }
        .sub-tab-btn.active {
            color: #dc2626;
            background: #fef2f2;
            border-color: #fecaca;
            box-shadow: 0 1px 3px rgba(220, 38, 38, 0.1);
        }
        .tab-badge {
            background: #ef4444;
            color: #ffffff;
            font-size: 11px;
            font-weight: 700;
            padding: 1px 7px;
            border-radius: 999px;
            margin-left: 2px;
        }

        /* Tab Content Panel Transition */
        .tab-content-section {
            display: none;
            animation: fadeInTab 0.25s ease both;
        }
        .tab-content-section.active {
            display: block;
        }
        @keyframes fadeInTab {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Badge loại tác vụ (Job Type) */
        .job-type-badge {
            font-size: 11.5px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .job-type-merge { background: #e0e7ff; color: #4338ca; }
        .job-type-split { background: #ffedd5; color: #c2410c; }
        .job-type-rotate { background: #ccfbf1; color: #0f766e; }
        .job-type-export { background: #f1f5f9; color: #475569; }

        /* Badge trạng thái (Job Status) */
        .job-status-badge {
            font-size: 11px;
            font-weight: 700;
            padding: 3px 9px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .job-status-pending { background: #fef3c7; color: #b45309; }
        .job-status-processing { background: #dbeafe; color: #1d4ed8; }
        .job-status-completed { background: #dcfce7; color: #15803d; }
        .job-status-failed { background: #fee2e2; color: #b91c1c; }

        .pulse-job-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
            animation: pulse-dot-anim 1.5s infinite;
        }
        @keyframes pulse-dot-anim {
            0% { opacity: 0.4; }
            50% { opacity: 1; }
            100% { opacity: 0.4; }
        }

        /* Nút hành động trong bảng */
        .btn-action-group {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        /* Modal chi tiết lỗi */
        .admin-modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            padding: 16px;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease;
        }
        .admin-modal-backdrop.open {
            opacity: 1;
            pointer-events: auto;
        }
        .admin-modal-box {
            background: #ffffff;
            border-radius: 18px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            max-width: 560px;
            width: 100%;
            padding: 28px;
            position: relative;
            transform: scale(0.96);
            transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .admin-modal-backdrop.open .admin-modal-box {
            transform: scale(1);
        }
        .error-log-pre {
            background: #f8fafc;
            border: 1px solid #fee2e2;
            border-left: 4px solid #ef4444;
            border-radius: 8px;
            padding: 12px 14px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 12.5px;
            color: #b91c1c;
            max-height: 220px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-break: break-all;
            margin: 14px 0 20px;
        }

        /* Thẻ Cấu hình hệ thống */
        .config-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 20px;
        }
        .config-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            margin-top: 12px;
        }
        .config-table th, .config-table td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--line);
            text-align: left;
        }
        .config-table th {
            color: var(--muted);
            font-weight: 600;
            width: 240px;
            background: #fbfbfe;
        }
        .config-table code {
            background: #f1f5f9;
            padding: 2px 6px;
            border-radius: 4px;
            color: #475569;
            font-family: monospace;
        }

        /* Toast Notification System */
        .admin-toast-container {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 99999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            pointer-events: none;
        }
        .admin-toast {
            pointer-events: auto;
            min-width: 320px;
            max-width: 440px;
            background: #ffffff;
            color: #1e293b;
            border-radius: 12px;
            padding: 14px 18px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            border-left: 5px solid #dc2626;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13.5px;
            font-weight: 500;
            opacity: 0;
            transform: translateY(20px) scale(0.96);
            animation: toastIn 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            transition: all 0.25s ease;
        }
        .admin-toast.success {
            border-left-color: #10b981;
        }
        .admin-toast.error {
            border-left-color: #ef4444;
        }
        .admin-toast.hiding {
            opacity: 0;
            transform: translateY(10px) scale(0.95);
        }
        @keyframes toastIn {
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        .toast-icon {
            font-size: 20px;
            flex-shrink: 0;
        }

        /* Settings Form Styling */
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 16px;
        }
        @media (max-width: 768px) {
            .settings-grid {
                grid-template-columns: 1fr;
            }
        }
        .settings-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .settings-label {
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .settings-input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }
        .settings-input {
            width: 100%;
            padding: 10px 58px 10px 14px;
            border-radius: 8px;
            border: 1px solid var(--line);
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            background: #ffffff;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .settings-input:focus {
            outline: none;
            border-color: #dc2626;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.15);
        }
        .settings-unit {
            position: absolute;
            right: 14px;
            font-size: 12px;
            font-weight: 700;
            color: #94a3b8;
            pointer-events: none;
        }
        .settings-hint {
            font-size: 12px;
            color: #64748b;
        }

        /* Safe guarantee badge in cleanup modal */
        .safe-guarantee-box {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            border-radius: 10px;
            padding: 12px 14px;
            margin: 14px 0;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 13px;
            color: #065f46;
        }

        /* User Action Dropdown Menu */
        .user-action-dropdown-wrap {
            position: relative;
            display: inline-block;
        }
        .user-dropdown-menu {
            position: absolute;
            right: 0;
            top: calc(100% + 4px);
            background: #ffffff;
            border: 1px solid var(--line);
            border-radius: 10px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            min-width: 240px;
            z-index: 1000;
            padding: 6px;
            display: none;
            animation: fadeInDropdown 0.15s ease-out;
        }
        .user-dropdown-menu.show {
            display: block;
        }
        @keyframes fadeInDropdown {
            from { opacity: 0; transform: translateY(-4px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .user-dropdown-item {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background: transparent;
            border: none;
            border-radius: 6px;
            font-size: 12.5px;
            font-weight: 500;
            color: #334155;
            text-align: left;
            cursor: pointer;
            transition: background 0.15s, color 0.15s;
        }
        .user-dropdown-item:hover {
            background: #f1f5f9;
            color: #0f172a;
        }
        .user-dropdown-item.text-danger {
            color: #dc2626;
        }
        .user-dropdown-item.text-danger:hover {
            background: #fee2e2;
        }
        .user-dropdown-item.text-green {
            color: #15803d;
        }
        .user-dropdown-item.text-green:hover {
            background: #dcfce7;
        }
        .user-dropdown-divider {
            height: 1px;
            background: var(--line);
            margin: 4px 0;
        }

        /* Pagination Styling */
        .table-pagination-wrap {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 18px;
            border-top: 1px solid var(--line);
            background: #fafafc;
            border-bottom-left-radius: 16px;
            border-bottom-right-radius: 16px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .pagination-info {
            font-size: 13px;
            color: #64748b;
        }
        .pagination-controls {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .page-btn {
            min-width: 34px;
            height: 34px;
            padding: 0 10px;
            border: 1px solid var(--line);
            border-radius: 7px;
            background: #ffffff;
            font-size: 13px;
            font-weight: 600;
            color: #475569;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.15s;
        }
        .page-btn:hover:not(:disabled) {
            background: #f1f5f9;
            color: #0f172a;
            border-color: #cbd5e1;
        }
        .page-btn.active {
            background: #dc2626;
            color: #ffffff;
            border-color: #dc2626;
            box-shadow: 0 1px 3px rgba(220, 38, 38, 0.25);
        }
        .page-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        /* Modal User Files Styling */
        .user-files-subtabs {
            display: flex;
            gap: 8px;
            border-bottom: 1px solid var(--line);
            margin-bottom: 16px;
            padding-bottom: 8px;
        }
        .user-files-tab-btn {
            background: transparent;
            border: 1px solid transparent;
            padding: 6px 14px;
            border-radius: 7px;
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            transition: all 0.15s;
        }
        .user-files-tab-btn:hover {
            color: #1e293b;
            background: #f1f5f9;
        }
        .user-files-tab-btn.active {
            background: #fef2f2;
            color: #dc2626;
            border-color: #fecaca;
        }
    </style>
</head>
<body>
    <header class="topbar">
        <a class="brand small" href="admin.php">
            <span class="brand-icon">P</span>
            <span>PDF<span class="brand-light">Studio</span> <span class="badge-tag admin-badge">ADMIN</span></span>
        </a>
        <div class="top-actions">
            <a href="dashboard.php" class="btn ghost">← Không gian làm việc</a>
            <span class="user-pill">● <?= htmlspecialchars($currentUser, ENT_QUOTES, 'UTF-8') ?> (Admin)</span>
            <a class="btn ghost" href="logout.php">Đăng xuất</a>
        </div>
    </header>

    <main class="container">
        <!-- Hero Header -->
        <section class="hero">
            <div>
                <p class="eyebrow">HỆ THỐNG QUẢN TRỊ</p>
                <h1>Bảng điều khiển Quản trị viên</h1>
                <p class="muted">Phân quyền thành viên (User / Pro / Admin), giám sát tác vụ xử lý PDF và cấu hình hệ thống.</p>
            </div>
            <button id="btnRefreshAdmin" class="btn secondary" onclick="loadAdminData(true)">
                <span id="refreshIcon">↻</span> <span id="refreshText">Làm mới dữ liệu</span>
            </button>
        </section>

        <!-- 4 Thẻ thống kê tổng quan -->
        <section class="stats-grid four-cols">
            <article class="stat-card">
                <span class="stat-icon purple">👥</span>
                <div>
                    <p>Tổng người dùng</p>
                    <h2 id="totalUsers">—</h2>
                </div>
            </article>

            <article class="stat-card">
                <span class="stat-icon blue">📁</span>
                <div>
                    <p>Tài liệu & Tệp xuất</p>
                    <h2 id="totalDocs">—</h2>
                </div>
            </article>

            <article class="stat-card">
                <span class="stat-icon green">💾</span>
                <div>
                    <p>Dung lượng lưu trữ</p>
                    <h2 id="totalStorage">—</h2>
                </div>
            </article>

            <article class="stat-card">
                <span class="stat-icon red">⚠️</span>
                <div>
                    <p>Tác vụ thất bại</p>
                    <h2 id="totalFailedJobs" style="color: #dc2626;">0</h2>
                </div>
            </article>
        </section>

        <!-- Thanh điều hướng phụ (Sub-tabs) -->
        <nav class="sub-tabs-nav">
            <button id="tabBtn_users" class="sub-tab-btn active" onclick="switchAdminTab('users')">
                <span>👥</span> Người dùng & Phân quyền
            </button>
            <button id="tabBtn_jobs" class="sub-tab-btn" onclick="switchAdminTab('jobs')">
                <span>⚡</span> Giám sát tác vụ (Jobs)
                <span id="tabJobsBadge" class="tab-badge" style="display: none;">0</span>
            </button>
            <button id="tabBtn_settings" class="sub-tab-btn" onclick="switchAdminTab('settings')">
                <span>⚙️</span> Cấu hình hệ thống
            </button>
        </nav>

        <!-- ============================================================== -->
        <!-- TAB 1: NGƯỜI DÙNG & PHÂN QUYỀN                                   -->
        <!-- ============================================================== -->
        <section id="tabSection_users" class="tab-content-section active">
            <section class="panel">
                <div class="section-head">
                    <div>
                        <h2>Danh sách người dùng & Phân quyền</h2>
                        <p class="muted">Thay đổi gói quyền hạn, quản lý thời hạn VIP hoặc khóa/mở khóa tài khoản.</p>
                    </div>
                    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <select id="userRoleFilter" class="admin-select" onchange="onUserFilterChange()" style="padding: 7px 12px; font-weight: 600;">
                            <option value="all">Tất cả vai trò</option>
                            <option value="user">👤 User (Miễn phí)</option>
                            <option value="pro">👑 Pro (VIP)</option>
                            <option value="admin">🛡️ Admin (Quản trị)</option>
                        </select>
                        <input id="userSearch" placeholder="Tìm kiếm theo tên / email…" oninput="onUserFilterChange()" style="padding: 7px 14px; border-radius: 8px; border: 1px solid var(--line); font-size: 13px; width: 240px;">
                    </div>
                </div>

                <div id="usersTableWrap" class="table-wrap">
                    <p class="muted">Đang tải danh sách người dùng…</p>
                </div>

                <!-- Thanh phân trang ở cuối bảng -->
                <div id="usersPagination" class="table-pagination-wrap" style="display: none;">
                    <div id="usersPaginationInfo" class="pagination-info">Hiển thị 0 – 0 trên tổng số 0 người dùng</div>
                    <div id="usersPaginationControls" class="pagination-controls"></div>
                </div>
            </section>
        </section>

        <!-- ============================================================== -->
        <!-- TAB 2: GIÁM SÁT TÁC VỤ (JOBS)                                    -->
        <!-- ============================================================== -->
        <section id="tabSection_jobs" class="tab-content-section">
            <section class="panel">
                <div class="section-head">
                    <div>
                        <h2>Giám sát tác vụ xử lý PDF (Export Jobs)</h2>
                        <p class="muted">Theo dõi tiến trình ghép, tách, xoay trang và trạng thái xử lý trên máy chủ.</p>
                    </div>
                    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <select id="jobStatusFilter" class="admin-select" onchange="filterJobsList()">
                            <option value="all">Tất cả trạng thái</option>
                            <option value="failed">❌ Thất bại (Cần xử lý)</option>
                            <option value="processing">⏳ Đang xử lý</option>
                            <option value="completed">✓ Thành công</option>
                            <option value="pending">⏸ Đang chờ</option>
                        </select>
                        <input id="jobSearch" placeholder="Mã Job / Tên người dùng…" oninput="filterJobsList()" style="padding: 6px 12px; border-radius: 7px; border: 1px solid var(--line); font-size: 12px; width: 200px;">
                        <button class="btn danger small-btn" onclick="cleanFailedJobs()">
                            🧹 Dọn dẹp lỗi
                        </button>
                    </div>
                </div>

                <div id="jobsTableWrap" class="table-wrap">
                    <p class="muted">Đang tải danh sách tác vụ…</p>
                </div>
            </section>
        </section>

        <!-- ============================================================== -->
        <!-- TAB 3: CẤU HÌNH HỆ THỐNG                                         -->
        <!-- ============================================================== -->
        <section id="tabSection_settings" class="tab-content-section">
            <!-- 1. Giao diện Cấu hình hệ thống (Form Settings) -->
            <section class="panel" style="margin-bottom: 24px;">
                <div class="section-head">
                    <div>
                        <h2>⚙️ Cấu hình hệ thống & Giới hạn xử lý PDF</h2>
                        <p class="muted">Thiết lập các ngưỡng tài nguyên, thời gian chờ và giới hạn lượt xuất trên toàn hệ thống.</p>
                    </div>
                    <span class="badge-tag admin-badge">Áp dụng tức thì</span>
                </div>

                <form id="systemSettingsForm" onsubmit="saveSystemSettings(event)">
                    <div class="settings-grid">
                        <!-- Giới hạn dung lượng tải lên tối đa -->
                        <div class="settings-field">
                            <label class="settings-label" for="setting_max_file_mb">
                                <span>📦 Giới hạn dung lượng tải lên tối đa</span>
                            </label>
                            <div class="settings-input-wrap">
                                <input type="number" id="setting_max_file_mb" name="max_file_mb" class="settings-input" min="1" max="500" placeholder="20" required>
                                <span class="settings-unit">MB</span>
                            </div>
                            <span class="settings-hint">Dung lượng tệp PDF tối đa mỗi lần tải lên máy chủ (1 – 500 MB).</span>
                        </div>

                        <!-- Số trang tối đa cho phép xử lý mỗi lần -->
                        <div class="settings-field">
                            <label class="settings-label" for="setting_max_pages">
                                <span>📄 Số trang tối đa cho phép xử lý</span>
                            </label>
                            <div class="settings-input-wrap">
                                <input type="number" id="setting_max_pages" name="max_pages" class="settings-input" min="10" max="2000" placeholder="200" required>
                                <span class="settings-unit">Trang</span>
                            </div>
                            <span class="settings-hint">Số trang tài liệu tối đa khi thực hiện ghép / tách / xoay (10 – 2.000 trang).</span>
                        </div>

                        <!-- Thời gian timeout cho tác vụ xử lý PDF -->
                        <div class="settings-field">
                            <label class="settings-label" for="setting_execution_timeout">
                                <span>⏱️ Thời gian timeout xử lý PDF</span>
                            </label>
                            <div class="settings-input-wrap">
                                <input type="number" id="setting_execution_timeout" name="execution_timeout" class="settings-input" min="10" max="600" placeholder="120" required>
                                <span class="settings-unit">Giây</span>
                            </div>
                            <span class="settings-hint">Thời gian thực thi tối đa cho mỗi tác vụ trên máy chủ (10 – 600 giây).</span>
                        </div>

                        <!-- Giới hạn lượt xuất file trong ngày đối với tài khoản Miễn phí -->
                        <div class="settings-field">
                            <label class="settings-label" for="setting_free_daily_exports">
                                <span>🎯 Lượt xuất file / ngày (Tài khoản Free)</span>
                            </label>
                            <div class="settings-input-wrap">
                                <input type="number" id="setting_free_daily_exports" name="free_daily_exports" class="settings-input" min="1" max="100" placeholder="10" required>
                                <span class="settings-unit">Lượt</span>
                            </div>
                            <span class="settings-hint">Số lượt xuất tệp miễn phí tối đa trong một ngày cho người dùng thường (1 – 100 lượt).</span>
                        </div>
                    </div>

                    <div style="display: flex; justify-content: flex-end; align-items: center; gap: 14px; margin-top: 24px; padding-top: 18px; border-top: 1px solid var(--line);">
                        <span id="settingsSaveStatus" class="muted" style="font-size: 13px;"></span>
                        <button type="submit" id="btnSaveSettings" class="btn primary">
                            <span id="saveSettingsIcon">💾</span> <span id="saveSettingsText">Lưu cấu hình</span>
                        </button>
                    </div>
                </form>
            </section>

            <div class="two-col">
                <!-- Thẻ 1: Cổng thanh toán & VietQR -->
                <section class="panel">
                    <div class="section-head">
                        <div>
                            <h2>Cổng thanh toán & VietQR</h2>
                            <p class="muted">Thông tin nhận tiền tự động và Webhook API.</p>
                        </div>
                    </div>
                    <table class="config-table">
                        <tbody>
                            <tr>
                                <th>Ngân hàng nhận tiền:</th>
                                <td><strong><?= htmlspecialchars(PAYMENT_BANK_ID ?: 'Chưa cấu hình') ?></strong></td>
                            </tr>
                            <tr>
                                <th>Số tài khoản:</th>
                                <td><code><?= htmlspecialchars(PAYMENT_ACCOUNT_NO ?: 'Chưa cấu hình') ?></code></td>
                            </tr>
                            <tr>
                                <th>Chủ tài khoản:</th>
                                <td><strong><?= htmlspecialchars(PAYMENT_ACCOUNT_NAME ?: 'Chưa cấu hình') ?></strong></td>
                            </tr>
                            <tr>
                                <th>Trạng thái VietQR:</th>
                                <td>
                                    <?php if (is_payment_configured()): ?>
                                        <span class="job-status-badge job-status-completed">✓ Đã kích hoạt</span>
                                    <?php else: ?>
                                        <span class="job-status-badge job-status-failed">Chưa cấu hình</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Webhook PayOS:</th>
                                <td><code>/webhook_payment.php?gateway=payos</code></td>
                            </tr>
                            <tr>
                                <th>Webhook SePay:</th>
                                <td><code>/webhook_payment.php?gateway=sepay</code></td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <!-- Thẻ 2: Lưu trữ & Bảo trì -->
                <section class="panel">
                    <div class="section-head">
                        <div>
                            <h2>Chi tiết lưu trữ & Bảo trì</h2>
                            <p class="muted">Dung lượng phân bổ trên máy chủ.</p>
                        </div>
                    </div>
                    <div class="list-stack" style="margin-bottom: 20px;">
                        <div class="list-item">
                            <div class="list-main">
                                <b>Tệp tải lên nguồn (storage/uploads)</b>
                                <small id="uploadStorageSize">Đang tính toán…</small>
                            </div>
                        </div>
                        <div class="list-item">
                            <div class="list-main">
                                <b>Tệp đã xuất kết quả (storage/outputs)</b>
                                <small id="outputStorageSize">Đang tính toán…</small>
                            </div>
                        </div>
                    </div>
                    
                    <div style="border-top: 1px dashed var(--line); padding-top: 16px; display: flex; flex-direction: column; gap: 14px;">
                        <div>
                            <p class="muted" style="font-size: 12.5px; margin-bottom: 8px;">
                                Dọn dẹp tệp kết quả tạm đã hết hạn hoặc tệp rác mồ côi quá 24h để giải phóng dung lượng đĩa:
                            </p>
                            <button id="btnOpenCleanup" class="btn danger" onclick="openCleanupConfirmModal()" style="display: inline-flex; align-items: center; gap: 6px;">
                                <span>🧹</span> Dọn dẹp tệp tạm / tệp rác (> 24h)
                            </button>
                        </div>

                        <div style="border-top: 1px dashed var(--line); padding-top: 12px;">
                            <p class="muted" style="font-size: 12px; margin-bottom: 8px;">
                                Dọn sạch lịch sử các tác vụ xuất bị lỗi trong CSDL để tối ưu bảng:
                            </p>
                            <button class="btn secondary small-btn" onclick="cleanFailedJobs()">
                                🧹 Dọn sạch tác vụ lỗi (<span id="failedJobsCount">0</span> lỗi)
                            </button>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Thẻ 3: Hạn mức theo gói Role -->
            <section class="panel" style="margin-top: 24px;">
                <div class="section-head">
                    <div>
                        <h2>Chính sách hạn mức tài nguyên (Role Quotas)</h2>
                        <p class="muted">Được quản lý tập trung và tích hợp cùng bảng Cấu hình hệ thống.</p>
                    </div>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Gói tài khoản</th>
                                <th>Dung lượng tối đa</th>
                                <th>Số trang / tệp</th>
                                <th>Tệp / lần tải</th>
                                <th>Thời hạn lưu tệp</th>
                                <th>Tính năng Pro</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="badge-tag user-badge">Thành viên Thường (User)</span></td>
                                <td>20 MB</td>
                                <td>200 trang</td>
                                <td>10 tệp</td>
                                <td>7 ngày</td>
                                <td>❌ Không</td>
                            </tr>
                            <tr>
                                <td><span class="badge-tag pro-badge">Thành viên Pro (VIP)</span></td>
                                <td>100 MB</td>
                                <td>500 trang</td>
                                <td>30 tệp</td>
                                <td>Vĩnh viễn</td>
                                <td>✓ Đánh số, Watermark, Đặt mật khẩu</td>
                            </tr>
                            <tr>
                                <td><span class="badge-tag admin-badge">Quản trị viên (Admin)</span></td>
                                <td>500 MB (Không giới hạn)</td>
                                <td>2000 trang</td>
                                <td>100 tệp</td>
                                <td>Vĩnh viễn</td>
                                <td>✓ Toàn quyền hệ thống</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </section>
    </main>

    <!-- Modal Xem Chi Tiết Lỗi Tác Vụ -->
    <div id="errorModalBackdrop" class="admin-modal-backdrop">
        <div class="admin-modal-box">
            <h3 style="color: #b91c1c; margin-bottom: 6px; display: flex; align-items: center; gap: 8px;">
                <span>⚠️</span> Chi tiết lỗi tác vụ #<span id="errModalJobId">—</span>
            </h3>
            <p class="muted" style="font-size: 13px;" id="errModalMeta">Người thực hiện: —</p>

            <div class="error-log-pre" id="errModalContent">Không có dữ liệu lỗi.</div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button class="btn secondary" onclick="closeErrorModal()">Đóng</button>
                <button id="btnRetryFromModal" class="btn primary" onclick="retryJobFromModal()">
                    ↻ Thử lại tác vụ này
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Xác Nhận Dọn Dẹp Tệp Tạm / Tệp Rác (> 24h) -->
    <div id="cleanupModalBackdrop" class="admin-modal-backdrop">
        <div class="admin-modal-box">
            <h3 style="color: #b91c1c; margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">
                <span>🧹</span> Xác nhận dọn dẹp bộ nhớ lưu trữ
            </h3>
            <p class="muted" style="font-size: 13.5px; line-height: 1.5;">
                Hệ thống sẽ quét các thư mục lưu trữ và dọn dẹp các tệp tạm không còn liên kết hoặc đã quá hạn 24 giờ.
            </p>

            <div class="safe-guarantee-box">
                <span style="font-size: 20px;">🛡️</span>
                <div>
                    <strong>Cơ chế bảo vệ an toàn:</strong><br>
                    Tuyệt đối <b>KHÔNG</b> xóa các tệp nguồn đang hoạt động (<code>status = 'active'</code>) của người dùng. Chỉ loại bỏ tệp kết quả hết hạn > 24h và tệp rác mồ côi.
                </div>
            </div>

            <ul style="font-size: 13px; color: #475569; margin: 12px 0 20px 20px; line-height: 1.6;">
                <li>Xóa tệp PDF xuất kết quả đã lưu trữ hơn 24 giờ.</li>
                <li>Xóa tệp rác không có bản ghi liên kết trong cơ sở dữ liệu.</li>
                <li>Đồng bộ dọn sạch các bản ghi hết hạn và tác vụ lỗi cũ.</li>
            </ul>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button class="btn secondary" onclick="closeCleanupModal()">Hủy bỏ</button>
                <button id="btnConfirmCleanup" class="btn danger" onclick="executeStorageCleanup()">
                    <span id="cleanupIcon">🗑️</span> <span id="cleanupText">Xác nhận dọn dẹp</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Xem Danh Sách Tệp Đã Tải & Đã Xuất Của User -->
    <div id="userFilesModalBackdrop" class="admin-modal-backdrop">
        <div class="admin-modal-box" style="max-width: 760px; width: 95%;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 14px;">
                <div>
                    <h3 style="margin-bottom: 4px; display: flex; align-items: center; gap: 8px;">
                        <span>📁</span> Tệp lưu trữ của: <span id="userFilesModalUsername" style="color: #dc2626;">—</span>
                    </h3>
                    <p class="muted" style="font-size: 13px;" id="userFilesModalEmail">Email: —</p>
                </div>
                <button class="btn ghost small-btn" onclick="closeUserFilesModal()" style="font-size: 18px; line-height: 1; padding: 4px 10px;">✕</button>
            </div>

            <!-- Sub-tabs bên trong Modal: Tệp nguồn & Tệp xuất -->
            <div class="user-files-subtabs">
                <button id="modalTabBtn_docs" class="user-files-tab-btn active" onclick="switchUserFilesModalTab('docs')">
                    📥 Tệp nguồn đã tải (<span id="userModalDocsCount">0</span>)
                </button>
                <button id="modalTabBtn_outs" class="user-files-tab-btn" onclick="switchUserFilesModalTab('outs')">
                    📤 Tệp kết quả đã xuất (<span id="userModalOutsCount">0</span>)
                </button>
            </div>

            <!-- Nội dung Tab 1: Tệp nguồn -->
            <div id="modalTabContent_docs" class="table-wrap" style="max-height: 340px; overflow-y: auto; margin-bottom: 16px;">
                <p class="muted">Đang tải dữ liệu tệp nguồn…</p>
            </div>

            <!-- Nội dung Tab 2: Tệp xuất -->
            <div id="modalTabContent_outs" class="table-wrap" style="max-height: 340px; overflow-y: auto; margin-bottom: 16px; display: none;">
                <p class="muted">Đang tải dữ liệu tệp đã xuất…</p>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid var(--line); padding-top: 14px;">
                <button class="btn secondary" onclick="closeUserFilesModal()">Đóng</button>
            </div>
        </div>
    </div>

    <!-- Container Toast thông báo -->
    <div id="adminToastContainer" class="admin-toast-container"></div>

    <script>
        const currentAdminUserId = <?= (int) $currentUserId ?>;
        let allUsers = [];
        let allJobs = [];
        let currentModalJobId = null;
        let userCurrentPage = 1;
        const userPageSize = 8;
        let currentUserFilesData = null;

        function esc(str) {
            return String(str ?? '').replace(/[&<>"']/g, c => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            }[c]));
        }

        function formatBytes(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
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

        // ==========================================
        // Chuyển Tab (Sub-tabs)
        // ==========================================
        function switchAdminTab(tabName) {
            // Cập nhật nút tab
            document.querySelectorAll('.sub-tab-btn').forEach(btn => btn.classList.remove('active'));
            const activeBtn = document.getElementById(`tabBtn_${tabName}`);
            if (activeBtn) activeBtn.classList.add('active');

            // Cập nhật nội dung tab
            document.querySelectorAll('.tab-content-section').forEach(sec => sec.classList.remove('active'));
            const activeSec = document.getElementById(`tabSection_${tabName}`);
            if (activeSec) activeSec.classList.add('active');

            // Nếu chuyển sang tab Jobs thì tải lại jobs nếu rỗng
            if (tabName === 'jobs' && (!allJobs || !allJobs.length)) {
                loadJobsData();
            }

            // Nếu chuyển sang tab Settings thì tải lại cấu hình hệ thống
            if (tabName === 'settings') {
                loadSystemSettings();
            }
        }

        // ==========================================
        // Tải toàn bộ dữ liệu quản trị
        // ==========================================
        async function loadAdminData(isManual = false) {
            const btn = document.getElementById('btnRefreshAdmin');
            const icon = document.getElementById('refreshIcon');
            const text = document.getElementById('refreshText');

            if (isManual && btn) {
                btn.disabled = true;
                btn.classList.add('loading');
                if (icon) icon.classList.add('spin-icon');
                if (text) text.textContent = 'Đang làm mới…';
            }

            try {
                // 1. Tải Thống kê
                const statsRes = await fetch('api.php?action=admin_stats');
                if (!statsRes.ok) throw new Error(`Lỗi máy chủ khi tải thống kê (HTTP ${statsRes.status})`);
                const statsData = await statsRes.json();
                if (!statsData.ok) throw new Error(statsData.error || 'Không tải được thống kê hệ thống');

                const s = statsData.stats;
                document.getElementById('totalUsers').textContent = s.users;
                document.getElementById('totalDocs').textContent = `${s.documents} nguồn / ${s.outputs} xuất`;
                document.getElementById('totalStorage').textContent = formatBytes(s.total_bytes);
                document.getElementById('uploadStorageSize').textContent = formatBytes(s.upload_bytes);
                document.getElementById('outputStorageSize').textContent = formatBytes(s.output_bytes);
                document.getElementById('failedJobsCount').textContent = s.failed_jobs;

                // Thẻ số 4: Tác vụ thất bại (hiển thị màu đỏ cảnh báo)
                const failedEl = document.getElementById('totalFailedJobs');
                failedEl.textContent = s.failed_jobs;
                if (s.failed_jobs > 0) {
                    failedEl.style.color = '#dc2626';
                    document.getElementById('tabJobsBadge').textContent = s.failed_jobs;
                    document.getElementById('tabJobsBadge').style.display = 'inline-block';
                } else {
                    failedEl.style.color = '#10b981';
                    document.getElementById('tabJobsBadge').style.display = 'none';
                }

                // 2. Tải Danh sách người dùng
                const usersRes = await fetch('api.php?action=admin_users');
                if (!usersRes.ok) throw new Error(`Lỗi máy chủ khi tải người dùng (HTTP ${usersRes.status})`);
                const usersData = await usersRes.json();
                if (!usersData.ok) throw new Error(usersData.error || 'Không tải được danh sách người dùng');

                allUsers = usersData.users;
                filterAndRenderUsers();

                // 3. Tải Danh sách tác vụ Jobs
                await loadJobsData();

                // Phản hồi thành công trên nút
                if (isManual && btn && text && icon) {
                    icon.classList.remove('spin-icon');
                    icon.textContent = '✓';
                    text.textContent = 'Đã làm mới!';
                    btn.style.borderColor = '#10b981';
                    btn.style.color = '#059669';

                    setTimeout(() => {
                        icon.textContent = '↻';
                        text.textContent = 'Làm mới dữ liệu';
                        btn.disabled = false;
                        btn.classList.remove('loading');
                        btn.style.borderColor = '';
                        btn.style.color = '';
                    }, 1200);
                }

            } catch (err) {
                alert('Không thể làm mới dữ liệu quản trị: ' + err.message);
                if (btn && text && icon) {
                    icon.classList.remove('spin-icon');
                    icon.textContent = '↻';
                    text.textContent = 'Làm mới dữ liệu';
                    btn.disabled = false;
                    btn.classList.remove('loading');
                    btn.style.borderColor = '';
                    btn.style.color = '';
                }
            }
        }

        // ==========================================
        // Render Bảng Người Dùng & Bộ Lọc, Phân Trang (Tab 1)
        // ==========================================

        function renderVipBadge(u) {
            // Chỉ hiển thị nhãn thời hạn VIP cho tài khoản Pro (VIP)
            if (u.role !== 'pro') {
                return '';
            }

            // Nếu không có ngày hết hạn, không hiển thị nhãn
            if (!u.vip_expires_at || u.vip_expires_at === '0000-00-00 00:00:00') {
                return '';
            }

            const expDate = new Date(u.vip_expires_at.replace(/-/g, '/'));
            const now = new Date();
            const diffMs = expDate.getTime() - now.getTime();
            const diffDays = Math.ceil(diffMs / (1000 * 3600 * 24));

            const d = String(expDate.getDate()).padStart(2, '0');
            const m = String(expDate.getMonth() + 1).padStart(2, '0');
            const y = expDate.getFullYear();
            const formatted = `${d}/${m}/${y}`;

            if (diffMs <= 0) {
                // Đã hết hạn -> Cảnh báo màu đỏ
                return `<div style="margin-top: 5px;"><span class="badge-tag" style="background:#fee2e2; color:#b91c1c; border:1px solid #fecaca; font-size:11px; font-weight:700; padding:2px 7px; border-radius:6px; display:inline-flex; align-items:center; gap:4px;">⚠️ Hết hạn: ${formatted}</span></div>`;
            } else if (diffDays <= 3) {
                // Sắp hết hạn (< 3 ngày) -> Đổi màu cảnh báo vàng cam / đỏ
                const countdown = diffDays === 1 ? '1 ngày' : `${diffDays} ngày`;
                return `<div style="margin-top: 5px;"><span class="badge-tag" style="background:#fff7ed; color:#c2410c; border:1px solid #fed7aa; font-size:11px; font-weight:700; padding:2px 7px; border-radius:6px; display:inline-flex; align-items:center; gap:4px;">⏰ Sắp hết hạn: ${formatted} (còn ${countdown})</span></div>`;
            } else {
                // Còn hạn bình thường
                return `<div style="margin-top: 5px;"><span class="badge-tag pro-badge" style="font-size:11px; padding:2px 7px; display:inline-flex; align-items:center; gap:4px;">👑 Hạn VIP: ${formatted}</span></div>`;
            }
        }

        function onUserFilterChange() {
            userCurrentPage = 1;
            filterAndRenderUsers();
        }

        function changeUserPage(newPage) {
            userCurrentPage = newPage;
            filterAndRenderUsers();
        }

        function filterAndRenderUsers() {
            const roleFilter = document.getElementById('userRoleFilter')?.value || 'all';
            const kw = (document.getElementById('userSearch')?.value || '').toLowerCase().trim();

            const filtered = allUsers.filter(u => {
                if (roleFilter !== 'all' && u.role !== roleFilter) return false;
                if (kw && !u.username.toLowerCase().includes(kw) && !u.email.toLowerCase().includes(kw)) return false;
                return true;
            });

            renderUsersTablePaginated(filtered);
        }

        function renderUsersTablePaginated(users) {
            const wrap = document.getElementById('usersTableWrap');
            const paginationWrap = document.getElementById('usersPagination');
            const paginationInfo = document.getElementById('usersPaginationInfo');
            const paginationControls = document.getElementById('usersPaginationControls');

            if (!users.length) {
                wrap.innerHTML = '<p class="empty" style="text-align: center; padding: 32px 16px; color: var(--muted);">Không có người dùng nào khớp với bộ lọc.</p>';
                if (paginationWrap) paginationWrap.style.display = 'none';
                return;
            }

            const total = users.length;
            const totalPages = Math.ceil(total / userPageSize) || 1;
            if (userCurrentPage > totalPages) userCurrentPage = totalPages;
            if (userCurrentPage < 1) userCurrentPage = 1;

            const startIdx = (userCurrentPage - 1) * userPageSize;
            const endIdx = Math.min(startIdx + userPageSize, total);
            const currentSlice = users.slice(startIdx, endIdx);

            wrap.innerHTML = `
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Người dùng</th>
                            <th>Email</th>
                            <th>Vai trò (Role)</th>
                            <th>Trạng thái</th>
                            <th>Tài nguyên</th>
                            <th>Ngày tham gia</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${currentSlice.map(u => {
                            const isSelf = (u.id === currentAdminUserId);
                            return `
                                <tr>
                                    <td>#${u.id}</td>
                                    <td><b>${esc(u.username)}</b> ${isSelf ? '<span class="badge-tag admin-badge" style="font-size:10px; padding:1px 5px;">Bạn</span>' : ''}</td>
                                    <td>${esc(u.email)}</td>
                                    <td>
                                        <select id="role_${u.id}" class="admin-select" style="font-weight: 600;">
                                            <option value="user" ${u.role === 'user' ? 'selected' : ''}>User (Miễn phí)</option>
                                            <option value="pro" ${u.role === 'pro' ? 'selected' : ''}>Pro (VIP)</option>
                                            <option value="admin" ${u.role === 'admin' ? 'selected' : ''}>Admin (Quản trị)</option>
                                        </select>
                                        ${renderVipBadge(u)}
                                    </td>
                                    <td>
                                        <select id="status_${u.id}" class="admin-select ${u.status === 'banned' ? 'status-banned' : ''}">
                                            <option value="active" ${u.status === 'active' ? 'selected' : ''}>Hoạt động</option>
                                            <option value="banned" ${u.status === 'banned' ? 'selected' : ''}>Bị khóa</option>
                                        </select>
                                    </td>
                                    <td>
                                        <small>${u.doc_count} tài liệu · ${u.out_count} bản xuất</small>
                                    </td>
                                    <td><small class="muted">${esc(formatDateTime(u.created_at))}</small></td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 6px; position: relative;">
                                            <button class="btn secondary small-btn" onclick="saveUser(${u.id})" title="Lưu thay đổi vai trò hoặc trạng thái">
                                                Lưu
                                            </button>

                                            <!-- Dropdown Thao tác nâng cao -->
                                            <div class="user-action-dropdown-wrap">
                                                <button class="btn ghost small-btn" onclick="toggleUserDropdown(event, ${u.id})" style="padding: 4px 8px; font-weight: 700; font-size: 13px;" title="Thao tác nâng cao">
                                                    ⚙️ ▾
                                                </button>
                                                <div id="userDropdown_${u.id}" class="user-dropdown-menu">
                                                    ${!isSelf ? `
                                                        <button class="user-dropdown-item" onclick="impersonateUser(${u.id}, '${esc(u.username)}')">
                                                            <span>👤</span> Đăng nhập vai trò này (Impersonate)
                                                        </button>
                                                    ` : `
                                                        <div class="user-dropdown-item muted" style="cursor: default; opacity: 0.6;">
                                                            <span>👤</span> Đang là tài khoản hiện tại
                                                        </div>
                                                    `}
                                                    <button class="user-dropdown-item" onclick="viewUserFiles(${u.id}, '${esc(u.username)}', '${esc(u.email)}')">
                                                        <span>📁</span> Xem danh sách tệp & bản xuất
                                                    </button>
                                                    <div class="user-dropdown-divider"></div>
                                                    ${!isSelf ? `
                                                        <button class="user-dropdown-item ${u.status === 'banned' ? 'text-green' : 'text-danger'}" onclick="quickToggleStatus(${u.id}, '${u.status}', '${esc(u.username)}')">
                                                            <span>${u.status === 'banned' ? '🔓' : '🔒'}</span> ${u.status === 'banned' ? 'Mở khóa tài khoản' : 'Khóa tài khoản nhanh'}
                                                        </button>
                                                    ` : ''}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            `;

            // Render thanh phân trang
            if (paginationWrap) {
                paginationWrap.style.display = 'flex';
                paginationInfo.textContent = `Hiển thị ${startIdx + 1} – ${endIdx} trên tổng số ${total} người dùng (Trang ${userCurrentPage}/${totalPages})`;

                let btnHtml = `
                    <button class="page-btn" onclick="changeUserPage(${userCurrentPage - 1})" ${userCurrentPage === 1 ? 'disabled' : ''}>
                        ‹ Trước
                    </button>
                `;

                for (let p = 1; p <= totalPages; p++) {
                    if (p === 1 || p === totalPages || (p >= userCurrentPage - 1 && p <= userCurrentPage + 1)) {
                        btnHtml += `
                            <button class="page-btn ${p === userCurrentPage ? 'active' : ''}" onclick="changeUserPage(${p})">
                                ${p}
                            </button>
                        `;
                    } else if (p === userCurrentPage - 2 || p === userCurrentPage + 2) {
                        btnHtml += `<span style="padding: 0 4px; color: #94a3b8;">…</span>`;
                    }
                }

                btnHtml += `
                    <button class="page-btn" onclick="changeUserPage(${userCurrentPage + 1})" ${userCurrentPage === totalPages ? 'disabled' : ''}>
                        Sau ›
                    </button>
                `;

                paginationControls.innerHTML = btnHtml;
            }
        }

        // ==========================================
        // Thao tác người dùng (Actions)
        // ==========================================
        function toggleUserDropdown(event, userId) {
            event.stopPropagation();
            const menu = document.getElementById(`userDropdown_${userId}`);
            const isCurrentlyShown = menu && menu.classList.contains('show');
            closeAllUserDropdowns();
            if (menu && !isCurrentlyShown) {
                menu.classList.add('show');
            }
        }

        function closeAllUserDropdowns() {
            document.querySelectorAll('.user-dropdown-menu').forEach(m => m.classList.remove('show'));
        }

        async function saveUser(id) {
            const role = document.getElementById(`role_${id}`).value;
            const status = document.getElementById(`status_${id}`).value;

            const fd = new FormData();
            fd.append('user_id', id);
            fd.append('role', role);
            fd.append('status', status);

            try {
                const res = await fetch('api.php?action=admin_update_user', {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();
                if (!data.ok) throw new Error(data.error);

                showToast('Cập nhật người dùng thành công!', 'success');
                loadAdminData();
            } catch (err) {
                showToast('Lỗi: ' + err.message, 'error');
            }
        }

        async function quickToggleStatus(id, currentStatus, username) {
            closeAllUserDropdowns();
            const newStatus = currentStatus === 'active' ? 'banned' : 'active';
            const actionText = newStatus === 'banned' ? 'KHÓA' : 'MỞ KHÓA';

            if (!confirm(`Bạn có chắc chắn muốn ${actionText} tài khoản của "${username}" không?`)) {
                return;
            }

            const roleSelect = document.getElementById(`role_${id}`);
            const userObj = allUsers.find(u => u.id === id);
            const role = roleSelect ? roleSelect.value : (userObj ? userObj.role : 'user');

            const fd = new FormData();
            fd.append('user_id', id);
            fd.append('role', role);
            fd.append('status', newStatus);

            try {
                const res = await fetch('api.php?action=admin_update_user', {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();
                if (!data.ok) throw new Error(data.error);

                showToast(`Đã ${actionText.toLowerCase()} tài khoản "${username}" thành công!`, 'success');
                loadAdminData();
            } catch (err) {
                showToast('Lỗi: ' + err.message, 'error');
            }
        }

        async function impersonateUser(id, username) {
            closeAllUserDropdowns();
            if (!confirm(`Bạn có muốn đăng nhập vào hệ thống làm việc với tư cách là "${username}" không?\n\nBạn có thể quay lại tài khoản Admin bất cứ lúc nào.`)) {
                return;
            }

            const fd = new FormData();
            fd.append('user_id', id);

            try {
                const res = await fetch('api.php?action=admin_impersonate_user', {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();
                if (!data.ok) throw new Error(data.error);

                showToast(data.message || 'Đang chuyển đổi tài khoản…', 'success');
                setTimeout(() => {
                    window.location.href = data.redirect || 'editor.php';
                }, 600);
            } catch (err) {
                showToast('Lỗi mạo danh: ' + err.message, 'error');
            }
        }

        // ==========================================
        // Modal Xem Danh Sách Tệp Của User
        // ==========================================
        async function viewUserFiles(id, username, email) {
            closeAllUserDropdowns();
            document.getElementById('userFilesModalUsername').textContent = username;
            document.getElementById('userFilesModalEmail').textContent = `Email: ${email} · ID #${id}`;

            document.getElementById('modalTabContent_docs').innerHTML = '<p class="muted" style="padding: 16px;">Đang tải danh sách tệp nguồn…</p>';
            document.getElementById('modalTabContent_outs').innerHTML = '<p class="muted" style="padding: 16px;">Đang tải danh sách tệp xuất…</p>';
            document.getElementById('userModalDocsCount').textContent = '…';
            document.getElementById('userModalOutsCount').textContent = '…';

            switchUserFilesModalTab('docs');
            document.getElementById('userFilesModalBackdrop').classList.add('open');

            try {
                const res = await fetch(`api.php?action=admin_get_user_files&user_id=${id}`);
                const data = await res.json();
                if (!data.ok) throw new Error(data.error);

                currentUserFilesData = data;
                renderUserFilesModalContent(data);
            } catch (err) {
                document.getElementById('modalTabContent_docs').innerHTML = `<p style="color:#b91c1c; padding: 16px;">Lỗi: ${esc(err.message)}</p>`;
            }
        }

        function renderUserFilesModalContent(data) {
            const docs = data.documents || [];
            const outs = data.outputs || [];

            document.getElementById('userModalDocsCount').textContent = docs.length;
            document.getElementById('userModalOutsCount').textContent = outs.length;

            // Render Documents Table
            const docsWrap = document.getElementById('modalTabContent_docs');
            if (!docs.length) {
                docsWrap.innerHTML = '<p class="empty" style="text-align: center; padding: 24px; color: var(--muted);">Người dùng chưa tải lên tệp PDF nào.</p>';
            } else {
                docsWrap.innerHTML = `
                    <table>
                        <thead>
                            <tr>
                                <th>#ID</th>
                                <th>Tên tệp gốc</th>
                                <th>Dung lượng</th>
                                <th>Số trang</th>
                                <th>Trạng thái</th>
                                <th>Ngày tải lên</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${docs.map(d => `
                                <tr>
                                    <td>#${d.id}</td>
                                    <td><b>${esc(d.original_name)}</b></td>
                                    <td>${formatBytes(d.file_size)}</td>
                                    <td>${d.page_count} trang</td>
                                    <td>
                                        <span class="job-status-badge ${d.status === 'active' ? 'job-status-completed' : 'job-status-failed'}">
                                            ${d.status === 'active' ? '✓ Khả dụng' : 'Đã xóa'}
                                        </span>
                                    </td>
                                    <td><small class="muted">${esc(formatDateTime(d.created_at))}</small></td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `;
            }

            // Render Outputs Table
            const outsWrap = document.getElementById('modalTabContent_outs');
            if (!outs.length) {
                outsWrap.innerHTML = '<p class="empty" style="text-align: center; padding: 24px; color: var(--muted);">Người dùng chưa có tệp xuất kết quả nào.</p>';
            } else {
                outsWrap.innerHTML = `
                    <table>
                        <thead>
                            <tr>
                                <th>#ID</th>
                                <th>Tên tệp xuất</th>
                                <th>Dung lượng</th>
                                <th>Số trang</th>
                                <th>Trạng thái</th>
                                <th>Hết hạn</th>
                                <th>Thời gian xuất</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${outs.map(o => `
                                <tr>
                                    <td>#${o.id}</td>
                                    <td><b>${esc(o.output_name)}</b></td>
                                    <td>${formatBytes(o.file_size)}</td>
                                    <td>${o.page_count} trang</td>
                                    <td>
                                        <span class="job-status-badge ${o.status === 'ready' ? 'job-status-completed' : 'job-status-pending'}">
                                            ${o.status === 'ready' ? '✓ Sẵn sàng' : esc(o.status)}
                                        </span>
                                    </td>
                                    <td><small class="muted">${o.expires_at ? esc(formatDateTime(o.expires_at)) : 'Vĩnh viễn'}</small></td>
                                    <td><small class="muted">${esc(formatDateTime(o.created_at))}</small></td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `;
            }
        }

        function switchUserFilesModalTab(tab) {
            document.querySelectorAll('.user-files-tab-btn').forEach(b => b.classList.remove('active'));
            const activeBtn = document.getElementById(`modalTabBtn_${tab}`);
            if (activeBtn) activeBtn.classList.add('active');

            const docsContent = document.getElementById('modalTabContent_docs');
            const outsContent = document.getElementById('modalTabContent_outs');

            if (tab === 'docs') {
                if (docsContent) docsContent.style.display = 'block';
                if (outsContent) outsContent.style.display = 'none';
            } else {
                if (docsContent) docsContent.style.display = 'none';
                if (outsContent) outsContent.style.display = 'block';
            }
        }

        function closeUserFilesModal() {
            document.getElementById('userFilesModalBackdrop').classList.remove('open');
            currentUserFilesData = null;
        }

        // ==========================================
        // Tải và Render Giám Sát Tác Vụ (Tab 2)
        // ==========================================
        async function loadJobsData() {
            try {
                const res = await fetch('api.php?action=admin_jobs');
                const data = await res.json();
                if (data.ok) {
                    allJobs = data.jobs;
                    filterJobsList();
                }
            } catch (e) {
                console.error('Lỗi tải jobs:', e);
            }
        }

        function filterJobsList() {
            const statusFilter = document.getElementById('jobStatusFilter')?.value || 'all';
            const kw = (document.getElementById('jobSearch')?.value || '').toLowerCase().trim();

            let filtered = allJobs;

            if (statusFilter !== 'all') {
                filtered = filtered.filter(j => j.status === statusFilter);
            }

            if (kw) {
                filtered = filtered.filter(j => 
                    String(j.id).includes(kw) ||
                    j.username.toLowerCase().includes(kw) ||
                    j.email.toLowerCase().includes(kw) ||
                    j.job_type_label.toLowerCase().includes(kw)
                );
            }

            renderJobsTable(filtered);
        }

        function renderJobsTable(jobs) {
            const wrap = document.getElementById('jobsTableWrap');
            if (!jobs.length) {
                wrap.innerHTML = '<p class="empty" style="padding: 30px; text-align: center;">Không có tác vụ nào khớp bộ lọc.</p>';
                return;
            }

            const typeBadges = {
                'merge': '<span class="job-type-badge job-type-merge">📑 Ghép PDF</span>',
                'split': '<span class="job-type-badge job-type-split">✂ Tách / Trích</span>',
                'rotate': '<span class="job-type-badge job-type-rotate">🔄 Xoay trang</span>',
                'export': '<span class="job-type-badge job-type-export">📄 Xuất PDF</span>',
            };

            const statusBadges = {
                'pending': '<span class="job-status-badge job-status-pending">⏸ Đang chờ</span>',
                'processing': '<span class="job-status-badge job-status-processing"><span class="pulse-job-dot"></span> Đang xử lý</span>',
                'completed': '<span class="job-status-badge job-status-completed">✓ Thành công</span>',
                'failed': '<span class="job-status-badge job-status-failed">✕ Thất bại</span>',
            };

            wrap.innerHTML = `
                <table>
                    <thead>
                        <tr>
                            <th>Mã Job</th>
                            <th>Người dùng thực hiện</th>
                            <th>Loại tác vụ</th>
                            <th>Trạng thái</th>
                            <th>Thời gian Bắt đầu / Hoàn thành</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${jobs.map(j => {
                            const typeBadge = typeBadges[j.job_type] || typeBadges['export'];
                            const statusBadge = statusBadges[j.status] || `<span class="job-status-badge">${j.status}</span>`;
                            const isFailed = j.status === 'failed';

                            let actions = '';
                            if (isFailed) {
                                actions = `
                                    <div class="btn-action-group">
                                        <button class="btn secondary small-btn" onclick="openErrorModal(${j.id})">
                                            🔍 Xem lỗi
                                        </button>
                                        <button class="btn primary small-btn" onclick="retryJob(${j.id})">
                                            ↻ Thử lại
                                        </button>
                                    </div>
                                `;
                            } else {
                                actions = `<span class="muted" style="font-size: 12px;">Bình thường</span>`;
                            }

                            return `
                                <tr>
                                    <td><b>#${j.id}</b></td>
                                    <td>
                                        <b>${esc(j.username)}</b>
                                        <br><small class="muted">${esc(j.email)}</small>
                                    </td>
                                    <td>
                                        ${typeBadge}
                                        <br><small class="muted">${j.page_count} trang</small>
                                    </td>
                                    <td>${statusBadge}</td>
                                    <td>
                                        <small>Bắt đầu: ${esc(formatDateTime(j.created_at))}</small>
                                        <br><small class="muted">Xong: ${esc(j.completed_at ? formatDateTime(j.completed_at) : '—')}</small>
                                    </td>
                                    <td>${actions}</td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            `;
        }

        // ==========================================
        // Modal Xem Chi Tiết Lỗi & Retry Job
        // ==========================================
        function openErrorModal(jobId) {
            const job = allJobs.find(j => j.id === jobId);
            if (!job) return;

            currentModalJobId = jobId;
            document.getElementById('errModalJobId').textContent = jobId;
            document.getElementById('errModalMeta').textContent = `Người thực hiện: ${job.username} (${job.email}) · Thời điểm: ${formatDateTime(job.completed_at || job.created_at)}`;
            document.getElementById('errModalContent').textContent = job.error_message || 'Không có chi tiết lỗi nào được ghi lại.';
            document.getElementById('errorModalBackdrop').classList.add('open');
        }

        function closeErrorModal() {
            document.getElementById('errorModalBackdrop').classList.remove('open');
            currentModalJobId = null;
        }

        async function retryJobFromModal() {
            if (currentModalJobId) {
                closeErrorModal();
                await retryJob(currentModalJobId);
            }
        }

        async function retryJob(jobId) {
            if (!confirm(`Bạn có muốn đưa tác vụ #${jobId} vào hàng đợi để thử lại không?`)) return;

            const fd = new FormData();
            fd.append('job_id', jobId);

            try {
                const res = await fetch('api.php?action=admin_retry_job', {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();
                if (!data.ok) throw new Error(data.error);

                alert(data.message || `Đã chuyển tác vụ #${jobId} sang trạng thái chờ xử lý lại.`);
                loadAdminData();
            } catch (err) {
                alert('Lỗi: ' + err.message);
            }
        }

        // ==========================================
        // Dọn Dẹp Tác Vụ Lỗi
        // ==========================================
        async function cleanFailedJobs() {
            if (!confirm('Bạn có chắc chắn muốn dọn sạch lịch sử các tác vụ xuất bị lỗi?')) return;

            try {
                const res = await fetch('api.php?action=admin_cleanup');
                const data = await res.json();
                if (!data.ok) throw new Error(data.error);

                alert(`Đã dọn dẹp ${data.cleaned} tác vụ lỗi.`);
                loadAdminData();
            } catch (err) {
                alert('Lỗi: ' + err.message);
            }
        }

        // ==========================================
        // Toast Notification System
        // ==========================================
        function showToast(message, type = 'success', duration = 4000) {
            const container = document.getElementById('adminToastContainer');
            if (!container) return;

            const toast = document.createElement('div');
            toast.className = `admin-toast ${type}`;
            
            const icon = type === 'success' ? '✓' : (type === 'error' ? '⚠️' : 'ℹ️');
            toast.innerHTML = `
                <span class="toast-icon">${icon}</span>
                <div style="flex: 1; word-break: break-word;">${esc(message)}</div>
            `;

            container.appendChild(toast);

            setTimeout(() => {
                toast.classList.add('hiding');
                setTimeout(() => toast.remove(), 250);
            }, duration);
        }

        // ==========================================
        // Cấu Hình Hệ Thống (Tab 3: Settings Form)
        // ==========================================
        async function loadSystemSettings() {
            try {
                const res = await fetch('api.php?action=admin_get_settings');
                const data = await res.json();
                if (data.ok && data.settings) {
                    const s = data.settings;
                    if (s.max_file_mb !== undefined) document.getElementById('setting_max_file_mb').value = s.max_file_mb;
                    if (s.max_pages !== undefined) document.getElementById('setting_max_pages').value = s.max_pages;
                    if (s.execution_timeout !== undefined) document.getElementById('setting_execution_timeout').value = s.execution_timeout;
                    if (s.free_daily_exports !== undefined) document.getElementById('setting_free_daily_exports').value = s.free_daily_exports;
                }
            } catch (err) {
                console.error('Lỗi tải cấu hình hệ thống:', err);
            }
        }

        async function saveSystemSettings(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSaveSettings');
            const icon = document.getElementById('saveSettingsIcon');
            const text = document.getElementById('saveSettingsText');
            const statusEl = document.getElementById('settingsSaveStatus');

            const maxFileMb = parseInt(document.getElementById('setting_max_file_mb').value, 10);
            const maxPages = parseInt(document.getElementById('setting_max_pages').value, 10);
            const executionTimeout = parseInt(document.getElementById('setting_execution_timeout').value, 10);
            const freeDailyExports = parseInt(document.getElementById('setting_free_daily_exports').value, 10);

            // Client-side validation
            if (isNaN(maxFileMb) || maxFileMb < 1 || maxFileMb > 500) {
                showToast('Giới hạn dung lượng tải lên tối đa phải từ 1 đến 500 MB.', 'error');
                return;
            }
            if (isNaN(maxPages) || maxPages < 10 || maxPages > 2000) {
                showToast('Số trang tối đa cho phép xử lý phải từ 10 đến 2.000 trang.', 'error');
                return;
            }
            if (isNaN(executionTimeout) || executionTimeout < 10 || executionTimeout > 600) {
                showToast('Thời gian timeout xử lý phải từ 10 đến 600 giây.', 'error');
                return;
            }
            if (isNaN(freeDailyExports) || freeDailyExports < 1 || freeDailyExports > 100) {
                showToast('Giới hạn lượt xuất miễn phí trong ngày phải từ 1 đến 100 lượt.', 'error');
                return;
            }

            btn.disabled = true;
            btn.classList.add('loading');
            icon.classList.add('spin-icon');
            text.textContent = 'Đang lưu…';
            statusEl.textContent = '';

            const fd = new FormData();
            fd.append('max_file_mb', maxFileMb);
            fd.append('max_pages', maxPages);
            fd.append('execution_timeout', executionTimeout);
            fd.append('free_daily_exports', freeDailyExports);

            try {
                const res = await fetch('api.php?action=admin_save_settings', {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();
                if (!data.ok) throw new Error(data.error || 'Không thể lưu cấu hình');

                showToast(data.message || 'Lưu cấu hình hệ thống thành công!', 'success');
                statusEl.textContent = '✓ Đã lưu lúc ' + new Date().toLocaleTimeString('vi-VN');
            } catch (err) {
                showToast(err.message, 'error');
                statusEl.textContent = '❌ Lỗi lưu cấu hình';
            } finally {
                btn.disabled = false;
                btn.classList.remove('loading');
                icon.classList.remove('spin-icon');
                text.textContent = 'Lưu cấu hình';
            }
        }

        // ==========================================
        // Dọn Dẹp Tệp Tạm / Tệp Rác (> 24h) An Toàn
        // ==========================================
        function openCleanupConfirmModal() {
            document.getElementById('cleanupModalBackdrop').classList.add('open');
        }

        function closeCleanupModal() {
            document.getElementById('cleanupModalBackdrop').classList.remove('open');
        }

        async function executeStorageCleanup() {
            const btn = document.getElementById('btnConfirmCleanup');
            const icon = document.getElementById('cleanupIcon');
            const text = document.getElementById('cleanupText');

            btn.disabled = true;
            btn.classList.add('loading');
            icon.classList.add('spin-icon');
            text.textContent = 'Đang dọn dẹp…';

            try {
                const res = await fetch('api.php?action=admin_cleanup_storage', {
                    method: 'POST'
                });
                const data = await res.json();
                if (!data.ok) throw new Error(data.error || 'Dọn dẹp bộ nhớ thất bại');

                closeCleanupModal();
                showToast(`🧹 Đã giải phóng thành công ${data.deleted_files} tệp tạm (${data.freed_mb} MB)!`, 'success', 5000);
                
                // Cập nhật lại số liệu dung lượng trên giao diện
                loadAdminData();
            } catch (err) {
                showToast('Lỗi dọn dẹp: ' + err.message, 'error', 5000);
            } finally {
                btn.disabled = false;
                btn.classList.remove('loading');
                icon.classList.remove('spin-icon');
                text.textContent = 'Xác nhận dọn dẹp';
            }
        }

        // Đóng dropdown thao tác người dùng khi click ra ngoài
        window.addEventListener('click', () => {
            closeAllUserDropdowns();
        });

        // Tự động tải dữ liệu khi mở trang
        loadAdminData();
        loadSystemSettings();
    </script>
</body>
</html>
