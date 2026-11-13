<?php
require __DIR__ . '/config.php';

$isLoggedIn = !empty($_SESSION['user_id']);
$role       = $isLoggedIn ? get_user_role() : 'guest';
$limits     = get_user_limits($role);
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Trình xử lý PDF · PDF Studio</title>
    <link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__ . '/assets/style.css') ?>">
    <!-- Thư viện hiển thị PDF.js và thư viện xử lý & mã hóa pdf-lib-plus-encrypt -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script src="https://unpkg.com/pdf-lib-plus-encrypt@1.1.0/dist/pdf-lib-plus-encrypt.min.js"></script>
    <script>
        window.APP_USER = {
            isLoggedIn: <?= $isLoggedIn ? 'true' : 'false' ?>,
            role: <?= json_encode($role) ?>,
            limits: <?= json_encode($limits) ?>,
        };
    </script>
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
        <a class="brand small" href="<?= $isLoggedIn ? 'dashboard.php' : 'editor.php' ?>">
            <span class="brand-icon">P</span>
            <span>PDF<span class="brand-light">Studio</span></span>
        </a>
        <div class="top-actions">
            <?php if ($isLoggedIn): ?>
                <a href="dashboard.php" class="btn ghost">← Bảng điều khiển</a>
                <span class="user-pill">
                    ● <?= htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8') ?>
                    <span class="badge-tag <?= htmlspecialchars($limits['badge_class']) ?>">
                        <?= htmlspecialchars($limits['label']) ?>
                    </span>
                </span>
                <a class="btn ghost" href="logout.php">Đăng xuất</a>
            <?php else: ?>
                <span class="user-pill">
                    ● Khách vãng lai
                    <span class="badge-tag guest-badge">GUEST</span>
                </span>
                <a href="index.php" class="btn ghost">Đăng nhập</a>
                <a href="register.php" class="btn primary small-btn">Đăng ký</a>
            <?php endif; ?>
        </div>
    </header>

    <main class="editor-container">
        <!-- Tiêu đề và lưu kế hoạch -->
        <section class="editor-heading">
            <div>
                <p class="eyebrow">PDF WORKSPACE</p>
                <h1>Sắp xếp và ghép PDF</h1>
                <p class="muted">
                    Gói tài khoản: <strong><?= htmlspecialchars($limits['label']) ?></strong> · 
                    Kéo thả trang để đổi thứ tự, xoay góc hoặc tách / ghép nhiều tệp.
                </p>
            </div>
            <div class="save-group">
                <input id="planName" value="Hồ sơ PDF mới" aria-label="Tên kế hoạch">
                <button class="btn secondary" id="savePlan">
                    <?= $isLoggedIn ? 'Lưu kế hoạch' : 'Lưu kế hoạch (Cần đăng nhập)' ?>
                </button>
            </div>
        </section>

        <!-- Khu vực kéo thả tệp tải lên -->
        <section class="upload-zone" id="dropZone">
            <div class="upload-symbol">↑</div>
            <h2>Kéo thả PDF vào đây</h2>
            <p class="muted">
                Hoặc chọn tệp từ thiết bị · Tối đa <?= $limits['max_files'] ?> tệp, 
                <?= is_numeric($limits['max_file_mb']) ? $limits['max_file_mb'] . ' MB/tệp' : $limits['max_file_mb'] ?>, 
                <?= $limits['max_pages'] ?> trang/tệp
                <?php if (!$isLoggedIn): ?>
                    <br><strong style="color: #dc2626;">* Chế độ Khách: Tệp được xử lý tức thì trên máy và tải ngay (không lưu trữ đám mây).</strong>
                <?php endif; ?>
            </p>
            <label class="btn primary file-button">
                Chọn tệp PDF
                <input id="fileInput" type="file" accept="application/pdf,.pdf" multiple hidden>
            </label>
            <div id="uploadStatus" class="status-line"></div>
        </section>

        <!-- Thanh công cụ xử lý trang -->
        <section class="toolbar panel">
            <div class="toolbar-left">
                <span class="selected-count" id="pageCount">0 trang</span>
                <button class="btn ghost" id="rotateBtn">↻ Xoay 90°</button>
                <button class="btn ghost" id="removeBtn">⌫ Bỏ trang</button>
                <button class="btn ghost" id="undoBtn" disabled>↶ Hoàn tác</button>
            </div>
            <div class="toolbar-right">
                <button class="btn secondary" id="splitBtn">Tách trang đã chọn</button>
                <button class="btn primary" id="exportBtn">Ghép & xuất PDF</button>
            </div>
        </section>

        <!-- Bảng tùy chọn nâng cao (Watermark, Đánh số trang, Đặt mật khẩu) -->
        <section class="panel">
            <div class="section-head" style="margin-bottom: 6px;">
                <div>
                    <h3 style="display:flex; align-items:center; gap:8px;">
                        ⚙ Tùy chọn xuất PDF nâng cao
                        <?php if ($limits['allow_watermark']): ?>
                            <span class="badge-tag pro-badge">ĐÃ MỞ KHÓA</span>
                        <?php else: ?>
                            <span class="badge-tag pro-badge">DÀNH CHO PRO / ADMIN</span>
                        <?php endif; ?>
                    </h3>
                    <p class="muted">Đánh số trang, đóng dấu bản quyền và mã hóa bảo mật bằng mật khẩu.</p>
                </div>
            </div>

            <div class="pro-options-grid">
                <!-- 1. Đánh số trang -->
                <label class="option-card <?= !$limits['allow_numbering'] ? 'disabled-pro' : '' ?>" id="optNumberingLabel">
                    <input type="checkbox" id="optNumbering" <?= !$limits['allow_numbering'] ? 'disabled' : '' ?>>
                    <div>
                        <b>Đánh số trang tự động</b>
                        <p class="muted">Thêm định dạng "Trang X / Y" căn giữa chân trang.</p>
                    </div>
                </label>

                <!-- 2. Watermark -->
                <label class="option-card <?= !$limits['allow_watermark'] ? 'disabled-pro' : '' ?>" id="optWatermarkLabel">
                    <input type="checkbox" id="optWatermark" <?= !$limits['allow_watermark'] ? 'disabled' : '' ?>>
                    <div>
                        <b>Đóng dấu Watermark chéo</b>
                        <input id="watermarkText" placeholder="Nội dung: vd BẢN SAO, MẬT" value="BẢN SAO" 
                               <?= !$limits['allow_watermark'] ? 'disabled' : '' ?>
                               onclick="event.stopPropagation();"
                               style="margin-top: 6px; width: 100%; padding: 6px 10px; border: 1px solid var(--line); border-radius: 6px; font-size: 11px;">
                    </div>
                </label>

                <!-- 3. Mật khẩu -->
                <label class="option-card <?= !$limits['allow_password'] ? 'disabled-pro' : '' ?>" id="optPasswordLabel">
                    <input type="checkbox" id="optPassword" <?= !$limits['allow_password'] ? 'disabled' : '' ?>>
                    <div>
                        <b>Đặt mật khẩu bảo vệ file</b>
                        <input id="pdfPassword" type="password" placeholder="Mật khẩu mở file..." 
                               <?= !$limits['allow_password'] ? 'disabled' : '' ?>
                               onclick="event.stopPropagation();"
                               style="margin-top: 6px; width: 100%; padding: 6px 10px; border: 1px solid var(--line); border-radius: 6px; font-size: 11px;">
                    </div>
                </label>
            </div>
        </section>

        <!-- Khu vực làm việc chính & danh sách tài liệu -->
        <section class="workspace">
            <div class="page-area">
                <div class="section-head">
                    <div>
                        <h2>Trang trong hồ sơ</h2>
                        <p class="muted">Chọn một trang để thao tác. Kéo thẻ để sắp xếp thứ tự.</p>
                    </div>
                    <button class="text-link" id="selectAll">Chọn tất cả</button>
                </div>
                <div id="pages" class="page-grid">
                    <div class="empty large-empty">Tải lên một hoặc nhiều PDF để bắt đầu.</div>
                </div>
            </div>

            <aside class="side-panel">
                <h3>Tài liệu đã tải</h3>
                <div id="sourceList" class="source-list">
                    <p class="muted">Chưa có tệp.</p>
                </div>
                <div class="side-note">
                    <b>Quy tắc xuất</b>
                    <p>Mỗi trang giữ thông tin tệp nguồn, số trang gốc và góc xoay. Tệp gốc không bị ghi đè.</p>
                </div>
                <div class="side-note">
                    <b>Hạn lưu trữ file xuất</b>
                    <p>
                        <?= $role === 'guest' 
                            ? 'Khách: Tải ngay về máy (không lưu máy chủ).' 
                            : ($role === 'user' ? 'Thành viên Free: Tệp xuất lưu trong 7 ngày.' : 'Thành viên Pro: Lưu trữ vĩnh viễn.') 
                        ?>
                    </p>
                </div>
            </aside>
        </section>

        <!-- Bảng thông báo kết quả xuất -->
        <section id="resultPanel" class="panel result-panel" hidden>
            <div class="section-head">
                <div>
                    <h2>Kết quả xuất</h2>
                    <p class="muted" id="resultMessage"></p>
                </div>
                <?php if ($isLoggedIn): ?>
                    <a href="dashboard.php" class="text-link">Xem lịch sử →</a>
                <?php endif; ?>
            </div>
            <div id="resultLinks" class="list-stack"></div>
        </section>
    </main>

    <!-- Modal 1: Giới thiệu & Mua gói Pro -->
    <div id="proFeatureModal" class="custom-modal-backdrop" style="display: none;" onclick="handleProModalBackdropClick(event)">
        <div class="custom-modal-box">
            <button type="button" class="custom-modal-close-btn" onclick="closeProFeatureModal()" aria-label="Đóng">✕</button>
            <div class="pro-modal-icon-wrap">
                <span>👑</span>
            </div>
            <h3 class="pro-modal-title">Tính năng dành riêng cho Thành viên Pro</h3>
            <p class="pro-modal-desc">
                Các tùy chọn nâng cao như Đánh số trang tự động, Đóng dấu Watermark và Cài mật khẩu bảo vệ file chỉ khả dụng cho tài khoản Pro và Quản trị viên.
            </p>
            <div class="pro-benefits-list">
                <div class="pro-benefit-item">
                    <span class="pro-check-icon">✓</span>
                    <span>Đóng dấu bản quyền & Đánh số trang tự động</span>
                </div>
                <div class="pro-benefit-item">
                    <span class="pro-check-icon">✓</span>
                    <span>Mật khẩu bảo vệ tài liệu</span>
                </div>
                <div class="pro-benefit-item">
                    <span class="pro-check-icon">✓</span>
                    <span>Không giới hạn số trang và dung lượng xử lý</span>
                </div>
            </div>
            <div style="display: flex; flex-direction: column; gap: 8px;">
                <a href="<?= $isLoggedIn ? 'dashboard.php?action=upgrade' : 'register.php?upgrade=pro' ?>" class="btn pro-upgrade-btn">
                    <span>⭐ Nâng cấp gói Pro ngay</span>
                </a>
                <button type="button" class="btn ghost small-btn" onclick="closeProFeatureModal()" style="color: #64748b; font-weight: 600;">
                    Để sau
                </button>
            </div>
        </div>
    </div>

    <script src="assets/editor.js?v=<?= filemtime(__DIR__ . '/assets/editor.js') ?>"></script>
</body>
</html>
