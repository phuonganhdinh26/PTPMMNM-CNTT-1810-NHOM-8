<?php
/**
 * API Xử lý tài liệu, kế hoạch PDF và Quản trị hệ thống
 */

require __DIR__ . '/config.php';
require_login();

$userId = (int) $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$userLimits = get_user_limits();

/**
 * Xử lý đóng dấu Watermark chéo lên từng trang PDF phía Backend
 * Hỗ trợ thư viện TCPDF / FPDI theo đúng thiết kế:
 * - Sau khi import trang (useTemplate)
 * - Màu chữ xám mờ hoặc đỏ nhạt (SetTextColor hoặc SetAlpha 0.25)
 * - Cỡ chữ lớn 45 - 60pt, in đậm
 * - Xoay chữ chéo 45 độ ở chính giữa trang (StartTransform, Rotate, StopTransform)
 * - Watermark nằm đè lên nội dung
 */
function apply_server_pdf_watermark(string $pdfFilePath, string $watermarkText = 'BẢN SAO'): bool
{
    if (!class_exists('TCPDF')) {
        $tcpdfPath = 'C:/xampp/phpMyAdmin/vendor/tecnickcom/tcpdf/tcpdf.php';
        if (file_exists($tcpdfPath)) {
            require_once $tcpdfPath;
        }
    }

    if (class_exists('setasign\Fpdi\Tcpdf\Fpdi') || class_exists('FPDI')) {
        try {
            $class = class_exists('setasign\Fpdi\Tcpdf\Fpdi') ? 'setasign\Fpdi\Tcpdf\Fpdi' : 'FPDI';
            /** @var \TCPDF $pdf */
            $pdf = new $class();
            $pdf->SetMargins(0, 0, 0);
            $pdf->SetAutoPageBreak(false);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);

            $pageCount = $pdf->setSourceFile($pdfFilePath);

            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);

                $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';
                $pdf->AddPage($orientation, [$size['width'], $size['height']]);

                // 1. Import trang nội dung
                $pdf->useTemplate($templateId);

                // 2. Thiết lập trạng thái vẽ Watermark SAU KHI import trang (nằm đè lên nội dung)
                if (method_exists($pdf, 'SetAlpha')) {
                    $pdf->SetAlpha(0.25);
                }
                $pdf->SetTextColor(220, 38, 38);
                $pdf->SetFont('helvetica', 'B', 50);

                $cx = $size['width'] / 2;
                $cy = $size['height'] / 2;

                // Xoay 45 độ ở chính giữa trang
                if (method_exists($pdf, 'StartTransform') && method_exists($pdf, 'Rotate') && method_exists($pdf, 'StopTransform')) {
                    $pdf->StartTransform();
                    $pdf->Rotate(45, $cx, $cy);
                    $pdf->Text($cx - 50, $cy, $watermarkText);
                    $pdf->StopTransform();
                }

                if (method_exists($pdf, 'SetAlpha')) {
                    $pdf->SetAlpha(1.0);
                }
            }

            $pdf->Output($pdfFilePath, 'F');
            return true;
        } catch (Throwable $e) {
            error_log('apply_server_pdf_watermark: ' . $e->getMessage());
            return false;
        }
    }

    return false;
}

try {
    // 0. API Xuất / Ghép tệp trực tiếp (/api/export hoặc api.php?action=export)
    if ($action === 'export') {
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true) ?: $_POST;

        $watermarkEnabled = !empty($payload['watermark_enabled']);
        $watermarkText = trim((string)($payload['watermark_text'] ?? 'BẢN SAO')) ?: 'BẢN SAO';

        json_response([
            'ok'                => true,
            'message'           => 'Yêu cầu xuất PDF đã được tiếp nhận.',
            'watermark_enabled' => $watermarkEnabled,
            'watermark_text'    => $watermarkText,
        ]);
    }

    // 1. Lấy danh sách tài liệu, kế hoạch, tệp xuất và thông tin hạn mức
    if ($action === 'list') {
        $docsStmt = db()->prepare("
            SELECT id, original_name, file_size, page_count, status, created_at 
            FROM documents 
            WHERE user_id = ? AND status <> 'deleted' 
            ORDER BY id DESC
        ");
        $docsStmt->execute([$userId]);

        $plansStmt = db()->prepare("
            SELECT id, plan_name, version_no, updated_at 
            FROM edit_plans 
            WHERE user_id = ? 
            ORDER BY updated_at DESC
        ");
        $plansStmt->execute([$userId]);

        $outsStmt = db()->prepare("
            SELECT o.id, o.output_name, o.file_size, o.created_at, o.expires_at, o.page_map, j.status 
            FROM outputs o 
            JOIN export_jobs j ON j.id = o.job_id 
            WHERE o.user_id = ? 
            ORDER BY o.id DESC
        ");
        $outsStmt->execute([$userId]);

        $vipInfo = sync_user_vip_status($userId);
        $userLimits = get_user_limits($vipInfo['role']);

        json_response([
            'ok'        => true,
            'user'      => [
                'id'             => $userId,
                'username'       => $_SESSION['username'],
                'role'           => $vipInfo['role'],
                'is_pro'         => $vipInfo['is_pro'],
                'vip_expires_at' => $vipInfo['vip_expires_at'],
                'limits'         => $userLimits,
            ],
            'documents' => $docsStmt->fetchAll(),
            'plans'     => $plansStmt->fetchAll(),
            'outputs'   => $outsStmt->fetchAll(),
        ]);
    }

    // 2. Tải lên tệp PDF nguồn (kiểm tra giới hạn theo gói Role)
    if ($action === 'upload') {
        if (empty($_FILES['files'])) {
            json_response(['ok' => false, 'error' => 'Chưa chọn tệp.'], 400);
        }

        $files = $_FILES['files'];
        $count = is_array($files['name']) ? count($files['name']) : 1;

        if ($count > $userLimits['max_files']) {
            json_response([
                'ok'    => false,
                'error' => "Gói của bạn chỉ được tải tối đa {$userLimits['max_files']} tệp mỗi lượt."
            ], 400);
        }

        // Kiểm tra giới hạn số lượng tài liệu nguồn lưu trên đám mây
        if ($userLimits['max_docs'] < PHP_INT_MAX) {
            $countDocsStmt = db()->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND status <> 'deleted'");
            $countDocsStmt->execute([$userId]);
            $currentDocCount = (int) $countDocsStmt->fetchColumn();

            if ($currentDocCount + $count > $userLimits['max_docs']) {
                json_response([
                    'ok'    => false,
                    'error' => "Gói {$userLimits['label']} chỉ cho phép lưu tối đa {$userLimits['max_docs']} tài liệu nguồn (bạn hiện có {$currentDocCount} tệp). Hãy nâng cấp Pro để lưu không giới hạn hoặc xóa bớt tệp cũ."
                ], 400);
            }
        }

        $saved = [];
        for ($i = 0; $i < $count; $i++) {
            $name  = is_array($files['name']) ? $files['name'][$i] : $files['name'];
            $tmp   = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
            $size  = (int) (is_array($files['size']) ? $files['size'][$i] : $files['size']);
            $err   = (int) (is_array($files['error']) ? $files['error'][$i] : $files['error']);
            $pages = (int) ($_POST['page_counts'][$i] ?? 0);

            if ($err !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Tải lên thất bại với tệp ' . basename($name) . '.');
            }
            if ($size < 8 || $size > $userLimits['max_file_bytes']) {
                throw new RuntimeException("Tệp " . basename($name) . " rỗng hoặc vượt giới hạn {$userLimits['max_file_mb']} MB.");
            }

            $head = file_get_contents($tmp, false, null, 0, 5);
            if ($head !== '%PDF-') {
                throw new RuntimeException('Tệp ' . basename($name) . ' không có chữ ký PDF hợp lệ.');
            }
            if ($pages < 1 || $pages > $userLimits['max_pages']) {
                throw new RuntimeException("Số trang của " . basename($name) . " không hợp lệ hoặc vượt {$userLimits['max_pages']} trang.");
            }

            $dir = safe_storage_dir('uploads', $userId);
            $stored = bin2hex(random_bytes(16)) . '.pdf';

            if (!move_uploaded_file($tmp, $dir . '/' . $stored)) {
                throw new RuntimeException('Không thể lưu tệp ' . basename($name) . '.');
            }

            $cleanName = mb_substr(basename(str_replace('\\', '/', $name)), 0, 255);
            $docStmt = db()->prepare("
                INSERT INTO documents (user_id, original_name, stored_name, file_size, page_count, status) 
                VALUES (?, ?, ?, ?, ?, 'ready')
            ");
            $docStmt->execute([$userId, $cleanName, $stored, $size, $pages]);
            $docId = (int) db()->lastInsertId();

            $pageStmt = db()->prepare("
                INSERT INTO source_pages (document_id, user_id, source_page_number, label) 
                VALUES (?, ?, ?, ?)
            ");
            for ($n = 1; $n <= $pages; $n++) {
                $pageStmt->execute([$docId, $userId, $n, $cleanName . ' · trang ' . $n]);
            }

            $saved[] = [
                'id'    => $docId,
                'name'  => $cleanName,
                'size'  => $size,
                'pages' => $pages,
            ];
        }

        json_response(['ok' => true, 'saved' => $saved]);
    }

    // 3. Đọc dữ liệu file nguồn (phục vụ render PDF.js)
    if ($action === 'file') {
        $id = (int) ($_GET['id'] ?? 0);
        $stmt = db()->prepare("SELECT original_name, stored_name FROM documents WHERE id = ? AND user_id = ? AND status = 'ready'");
        $stmt->execute([$id, $userId]);
        $doc = $stmt->fetch();

        if (!$doc) {
            http_response_code(404);
            exit('Không tìm thấy tài liệu.');
        }

        $path = safe_storage_dir('uploads', $userId) . '/' . $doc['stored_name'];
        if (!is_file($path)) {
            http_response_code(404);
            exit('Tệp không còn tồn tại.');
        }

        header('Content-Type: application/pdf');
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: inline; filename="document.pdf"');
        readfile($path);
        exit;
    }

    // 4. Lưu kế hoạch chỉnh sửa (kiểm tra hạn mức kế hoạch)
    if ($action === 'save_plan') {
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload) || !isset($payload['pages']) || !is_array($payload['pages'])) {
            json_response(['ok' => false, 'error' => 'Kế hoạch không hợp lệ.'], 400);
        }

        $id = (int) ($payload['id'] ?? 0);

        // Kiểm tra số lượng kế hoạch tối đa theo gói
        if (!$id && $userLimits['max_plans'] < PHP_INT_MAX) {
            $countPlans = db()->prepare("SELECT COUNT(*) FROM edit_plans WHERE user_id = ?");
            $countPlans->execute([$userId]);
            if ((int) $countPlans->fetchColumn() >= $userLimits['max_plans']) {
                json_response([
                    'ok'    => false,
                    'error' => "Bạn đã đạt giới hạn tối đa {$userLimits['max_plans']} kế hoạch của gói hiện tại."
                ], 400);
            }
        }

        if (count($payload['pages']) > $userLimits['max_pages'] * $userLimits['max_files']) {
            json_response(['ok' => false, 'error' => 'Kế hoạch vượt giới hạn số trang.'], 400);
        }

        foreach ($payload['pages'] as $pg) {
            $doc = (int) ($pg['documentId'] ?? 0);
            $src = (int) ($pg['sourcePage'] ?? 0);
            $rot = (int) ($pg['rotation'] ?? 0);

            if (!in_array($rot, [0, 90, 180, 270], true) || $src < 1 || $doc < 1) {
                json_response(['ok' => false, 'error' => 'Trang hoặc góc xoay không hợp lệ.'], 400);
            }

            $check = db()->prepare("SELECT id FROM documents WHERE id = ? AND user_id = ? AND status = 'ready'");
            $check->execute([$doc, $userId]);
            if (!$check->fetch()) {
                json_response(['ok' => false, 'error' => 'Kế hoạch chứa tài liệu không thuộc tài khoản này.'], 403);
            }
        }

        $name = trim((string) ($payload['name'] ?? 'Kế hoạch PDF'));
        $name = mb_substr($name, 0, 190);
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($id) {
            $q = db()->prepare('SELECT id, version_no FROM edit_plans WHERE id = ? AND user_id = ?');
            $q->execute([$id, $userId]);
            $old = $q->fetch();

            if (!$old) {
                json_response(['ok' => false, 'error' => 'Không tìm thấy kế hoạch.'], 404);
            }

            $update = db()->prepare('UPDATE edit_plans SET plan_name = ?, plan_json = ?, version_no = version_no + 1 WHERE id = ? AND user_id = ?');
            $update->execute([$name, $json, $id, $userId]);
            $version = (int) $old['version_no'] + 1;
        } else {
            $insert = db()->prepare('INSERT INTO edit_plans (user_id, plan_name, plan_json) VALUES (?, ?, ?)');
            $insert->execute([$userId, $name, $json]);
            $id = (int) db()->lastInsertId();
            $version = 1;
        }

        json_response(['ok' => true, 'id' => $id, 'version' => $version]);
    }

    // 5. Tải lại kế hoạch đã lưu
    if ($action === 'load_plan') {
        $id = (int) ($_GET['id'] ?? 0);
        $stmt = db()->prepare('SELECT id, plan_name, plan_json, version_no FROM edit_plans WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $plan = $stmt->fetch();

        if (!$plan) {
            json_response(['ok' => false, 'error' => 'Không tìm thấy kế hoạch.'], 404);
        }

        json_response([
            'ok'   => true,
            'plan' => [
                'id'      => (int) $plan['id'],
                'name'    => $plan['plan_name'],
                'version' => (int) $plan['version_no'],
                'data'    => json_decode($plan['plan_json'], true),
            ],
        ]);
    }

    // 6. Bắt đầu tác vụ xuất
    if ($action === 'begin_export') {
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload) || empty($payload['pages']) || !is_array($payload['pages'])) {
            json_response(['ok' => false, 'error' => 'Không có trang để xuất.'], 400);
        }

        $planId = (int) ($payload['planId'] ?? 0);
        if ($planId) {
            $q = db()->prepare('SELECT id FROM edit_plans WHERE id = ? AND user_id = ?');
            $q->execute([$planId, $userId]);
            if (!$q->fetch()) {
                json_response(['ok' => false, 'error' => 'Kế hoạch không thuộc tài khoản này.'], 403);
            }
        }

        // Đọc watermark_enabled và watermark_text từ payload request
        $watermarkEnabled = !empty($payload['watermark_enabled']);
        $watermarkText    = trim((string)($payload['watermark_text'] ?? 'BẢN SAO'));
        if ($watermarkText === '') {
            $watermarkText = 'BẢN SAO';
        }
        $payload['watermark_enabled'] = $watermarkEnabled;
        $payload['watermark_text']    = $watermarkText;

        $snapshot = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stmt = db()->prepare("INSERT INTO export_jobs (user_id, plan_id, plan_snapshot, status) VALUES (?, ?, ?, 'processing')");
        $stmt->execute([$userId, $planId ?: null, $snapshot]);

        json_response([
            'ok'                => true,
            'jobId'             => (int) db()->lastInsertId(),
            'watermark_enabled' => $watermarkEnabled,
            'watermark_text'    => $watermarkText,
        ]);
    }

    // 7. Hoàn tất tác vụ xuất và lưu tệp kết quả
    if ($action === 'finish_export') {
        $jobId    = (int) ($_POST['job_id'] ?? 0);
        $filename = trim((string) ($_POST['filename'] ?? 'result.pdf'));
        $pageMap  = $_POST['page_map'] ?? '[]';
        $status   = $_POST['status'] ?? 'completed';
        $err      = trim((string) ($_POST['error_message'] ?? ''));

        // Đọc cấu hình Watermark từ POST hoặc fallback từ job snapshot
        $watermarkEnabled = !empty($_POST['watermark_enabled']) && $_POST['watermark_enabled'] !== '0' && $_POST['watermark_enabled'] !== 'false';
        $watermarkText    = trim((string)($_POST['watermark_text'] ?? ''));
        if ($watermarkText === '') {
            $watermarkText = 'BẢN SAO';
        }

        $q = db()->prepare('SELECT id, status, plan_snapshot FROM export_jobs WHERE id = ? AND user_id = ?');
        $q->execute([$jobId, $userId]);
        $job = $q->fetch();

        if (!$job || $job['status'] !== 'processing') {
            json_response(['ok' => false, 'error' => 'Công việc không hợp lệ hoặc đã kết thúc.'], 403);
        }

        // Nếu client chưa gửi trong POST, kiểm tra trong snapshot
        if (!$watermarkEnabled && !empty($job['plan_snapshot'])) {
            $snap = json_decode($job['plan_snapshot'], true);
            if (!empty($snap['watermark_enabled'])) {
                $watermarkEnabled = true;
                $watermarkText    = trim((string)($snap['watermark_text'] ?? 'BẢN SAO')) ?: 'BẢN SAO';
            }
        }

        if ($status === 'failed') {
            $failStmt = db()->prepare("UPDATE export_jobs SET status = 'failed', error_message = ?, completed_at = NOW() WHERE id = ? AND user_id = ?");
            $failStmt->execute([mb_substr($err, 0, 2000), $jobId, $userId]);
            json_response(['ok' => true]);
        }

        if (empty($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Thiếu tệp kết quả PDF.');
        }

        $f = $_FILES['pdf'];
        if ($f['size'] < 8 || $f['size'] > $userLimits['max_file_bytes'] * $userLimits['max_files']) {
            throw new RuntimeException('Tệp kết quả rỗng hoặc vượt giới hạn.');
        }

        if (file_get_contents($f['tmp_name'], false, null, 0, 5) !== '%PDF-') {
            throw new RuntimeException('Tệp kết quả không phải PDF hợp lệ.');
        }

        $dir        = safe_storage_dir('outputs', $userId);
        $stored     = bin2hex(random_bytes(16)) . '.pdf';
        $targetPath = $dir . '/' . $stored;

        if (!move_uploaded_file($f['tmp_name'], $targetPath)) {
            throw new RuntimeException('Không thể lưu kết quả xuất.');
        }

        // Nếu watermark_enabled là true, thực thi hàm đóng dấu backend nếu có FPDI / TCPDF
        if ($watermarkEnabled) {
            apply_server_pdf_watermark($targetPath, $watermarkText);
            clearstatcache(true, $targetPath);
            $f['size'] = filesize($targetPath);
        }

        $filename = mb_substr(basename($filename), 0, 255);
        $map      = json_decode($pageMap, true);
        if (!is_array($map)) {
            $map = [];
        }

        // Lưu thông tin watermark vào metadata của bản đồ trang
        $map['watermark'] = [
            'enabled' => $watermarkEnabled,
            'text'    => $watermarkText,
        ];

        // Tính toán hạn lưu trữ (User: 7 ngày, Pro/Admin: vĩnh viễn)
        $expiresAt = null;
        if ($userLimits['output_expiry_days'] > 0) {
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$userLimits['output_expiry_days']} days"));
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $outStmt = $pdo->prepare('INSERT INTO outputs (job_id, user_id, output_name, stored_name, file_size, page_map, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $outStmt->execute([$jobId, $userId, $filename, $stored, (int) $f['size'], json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $expiresAt]);
            $outputId = (int) $pdo->lastInsertId(); // Lưu lại ID ngay sau khi INSERT

            $jobStmt = $pdo->prepare("UPDATE export_jobs SET status = 'completed', completed_at = NOW() WHERE id = ? AND user_id = ?");
            $jobStmt->execute([$jobId, $userId]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            @unlink($targetPath);
            throw $e;
        }

        json_response([
            'ok'                => true,
            'outputId'          => $outputId,
            'watermark_enabled' => $watermarkEnabled,
            'watermark_text'    => $watermarkText,
        ]);
    }

    // 8. Tải về tệp PDF đã xuất
    if ($action === 'download') {
        $id = (int) ($_GET['id'] ?? 0);
        if (is_admin()) {
            $stmt = db()->prepare('SELECT user_id, output_name, stored_name, expires_at FROM outputs WHERE id = ?');
            $stmt->execute([$id]);
        } else {
            $stmt = db()->prepare('SELECT user_id, output_name, stored_name, expires_at FROM outputs WHERE id = ? AND user_id = ?');
            $stmt->execute([$id, $userId]);
        }
        $out = $stmt->fetch();

        if (!$out) {
            http_response_code(404);
            exit('Không tìm thấy tệp.');
        }

        // Kiểm tra xem tệp có bị hết hạn lưu trữ (7 ngày) không
        if (!is_admin() && !empty($out['expires_at']) && strtotime($out['expires_at']) < time()) {
            http_response_code(410);
            exit('Tệp xuất đã hết hạn lưu trữ (7 ngày). Vui lòng thực hiện xuất lại hoặc nâng cấp Pro để lưu trữ vĩnh viễn.');
        }

        $fileOwnerId = (int) $out['user_id'];
        $path = safe_storage_dir('outputs', $fileOwnerId) . '/' . $out['stored_name'];
        if (!is_file($path)) {
            http_response_code(404);
            exit('Tệp không còn tồn tại trên máy chủ.');
        }

        header('Content-Type: application/pdf');
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: attachment; filename="' . rawurlencode($out['output_name']) . '"');
        readfile($path);
        exit;
    }

    // 9. Xóa tài liệu nguồn
    if ($action === 'delete_document') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = db()->prepare("SELECT stored_name FROM documents WHERE id = ? AND user_id = ? AND status = 'ready'");
        $stmt->execute([$id, $userId]);
        $doc = $stmt->fetch();

        if (!$doc) {
            json_response(['ok' => false, 'error' => 'Không tìm thấy tài liệu.'], 404);
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $update = $pdo->prepare("UPDATE documents SET status = 'deleted' WHERE id = ? AND user_id = ?");
            $update->execute([$id, $userId]);

            $deletePages = $pdo->prepare('DELETE FROM source_pages WHERE document_id = ? AND user_id = ?');
            $deletePages->execute([$id, $userId]);

            $pdo->commit();
            @unlink(safe_storage_dir('uploads', $userId) . '/' . $doc['stored_name']);
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        json_response(['ok' => true]);
    }

    // =========================================================================
    // KHU VỰC DÀNH CHO QUẢN TRỊ VIÊN (ADMIN ONLY)
    // =========================================================================

    // 10. Admin: Thống kê toàn hệ thống
    if ($action === 'admin_stats') {
        if (!is_admin()) {
            json_response(['ok' => false, 'error' => 'Yêu cầu quyền Quản trị viên.'], 403);
        }

        $userCount = (int) db()->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $docCount  = (int) db()->query("SELECT COUNT(*) FROM documents WHERE status <> 'deleted'")->fetchColumn();
        $outCount  = (int) db()->query("SELECT COUNT(*) FROM outputs")->fetchColumn();
        $failCount = (int) db()->query("SELECT COUNT(*) FROM export_jobs WHERE status = 'failed'")->fetchColumn();

        // Tính dung lượng thư mục lưu trữ
        $calcDirSize = function (string $dir): int {
            if (!is_dir($dir)) return 0;
            $size = 0;
            try {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST,
                    RecursiveIteratorIterator::CATCH_GET_CHILD
                );
                foreach ($iterator as $f) {
                    if ($f->isFile()) $size += $f->getSize();
                }
            } catch (Throwable $e) {
                // Không để lỗi quyền truy cập file làm chết API
            }
            return $size;
        };

        $uploadBytes = $calcDirSize(STORAGE_ROOT . '/uploads');
        $outputBytes = $calcDirSize(STORAGE_ROOT . '/outputs');

        json_response([
            'ok'    => true,
            'stats' => [
                'users'        => $userCount,
                'documents'    => $docCount,
                'outputs'      => $outCount,
                'failed_jobs'  => $failCount,
                'upload_bytes' => $uploadBytes,
                'output_bytes' => $outputBytes,
                'total_bytes'  => $uploadBytes + $outputBytes,
            ]
        ]);
    }

    // 11. Admin: Lấy danh sách thành viên
    if ($action === 'admin_users') {
        if (!is_admin()) {
            json_response(['ok' => false, 'error' => 'Yêu cầu quyền Quản trị viên.'], 403);
        }

        $query = db()->query("
            SELECT 
                u.id, 
                u.username, 
                u.email, 
                u.role, 
                u.status, 
                u.vip_expires_at,
                u.created_at,
                (SELECT COUNT(*) FROM documents d WHERE d.user_id = u.id AND d.status <> 'deleted') AS doc_count,
                (SELECT COUNT(*) FROM outputs o WHERE o.user_id = u.id) AS out_count
            FROM users u
            ORDER BY u.id DESC
        ");

        json_response([
            'ok'    => true,
            'users' => $query->fetchAll(),
        ]);
    }

    // 12. Admin: Cập nhật Role và Trạng thái người dùng
    if ($action === 'admin_update_user') {
        if (!is_admin()) {
            json_response(['ok' => false, 'error' => 'Yêu cầu quyền Quản trị viên.'], 403);
        }

        $targetId = (int) ($_POST['user_id'] ?? 0);
        $newRole   = $_POST['role'] ?? '';
        $newStatus = $_POST['status'] ?? '';

        if (!in_array($newRole, ['user', 'pro', 'admin'], true)) {
            json_response(['ok' => false, 'error' => 'Quyền (Role) không hợp lệ.'], 400);
        }
        if (!in_array($newStatus, ['active', 'banned'], true)) {
            json_response(['ok' => false, 'error' => 'Trạng thái không hợp lệ.'], 400);
        }

        // Không cho phép tự khóa tài khoản của chính mình
        if ($targetId === $userId && $newStatus === 'banned') {
            json_response(['ok' => false, 'error' => 'Bạn không thể tự khóa tài khoản của chính mình.'], 400);
        }

        $stmt = db()->prepare("UPDATE users SET role = ?, status = ? WHERE id = ?");
        $stmt->execute([$newRole, $newStatus, $targetId]);

        json_response(['ok' => true]);
    }

    // 12.1. Admin: Đăng nhập với tư cách người dùng (Impersonate)
    if ($action === 'admin_impersonate_user') {
        if (!is_admin()) {
            json_response(['ok' => false, 'error' => 'Yêu cầu quyền Quản trị viên.'], 403);
        }

        $targetId = (int) ($_POST['user_id'] ?? 0);
        if ($targetId <= 0) {
            json_response(['ok' => false, 'error' => 'Mã người dùng không hợp lệ.'], 400);
        }

        if ($targetId === $userId) {
            json_response(['ok' => false, 'error' => 'Bạn đang đăng nhập bằng chính tài khoản này.'], 400);
        }

        $stmt = db()->prepare("SELECT id, username, email, role, status FROM users WHERE id = ?");
        $stmt->execute([$targetId]);
        $targetUser = $stmt->fetch();

        if (!$targetUser) {
            json_response(['ok' => false, 'error' => 'Người dùng không tồn tại.'], 404);
        }

        if ($targetUser['status'] === 'banned') {
            json_response(['ok' => false, 'error' => 'Không thể mạo danh tài khoản đang bị khóa.'], 400);
        }

        // Lưu thông tin Admin gốc vào Session để có thể quay lại
        $_SESSION['admin_impersonator_id']   = $userId;
        $_SESSION['admin_impersonator_name'] = $_SESSION['username'] ?? 'Admin';

        // Gán session sang tài khoản người dùng đích
        $_SESSION['user_id']  = (int) $targetUser['id'];
        $_SESSION['username'] = $targetUser['username'];
        $_SESSION['role']     = $targetUser['role'] ?? 'user';

        json_response([
            'ok'       => true,
            'message'  => "Đã chuyển sang tài khoản {$targetUser['username']}.",
            'redirect' => 'editor.php',
        ]);
    }

    // 12.2. Thoát chế độ mạo danh và quay lại tài khoản Quản trị viên
    if ($action === 'exit_impersonate') {
        if (empty($_SESSION['admin_impersonator_id'])) {
            json_response(['ok' => false, 'error' => 'Không có phiên mạo danh nào đang diễn ra.'], 400);
        }

        $adminOrigId = (int) $_SESSION['admin_impersonator_id'];
        $stmt = db()->prepare("SELECT id, username, role, status FROM users WHERE id = ? AND role = 'admin'");
        $stmt->execute([$adminOrigId]);
        $adminUser = $stmt->fetch();

        if (!$adminUser || $adminUser['status'] === 'banned') {
            json_response(['ok' => false, 'error' => 'Tài khoản Quản trị viên gốc không còn hợp lệ.'], 403);
        }

        $_SESSION['user_id']  = (int) $adminUser['id'];
        $_SESSION['username'] = $adminUser['username'];
        $_SESSION['role']     = 'admin';
        unset($_SESSION['admin_impersonator_id'], $_SESSION['admin_impersonator_name']);

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            header('Location: admin.php');
            exit;
        }

        json_response([
            'ok'       => true,
            'message'  => 'Đã quay trở lại tài khoản Quản trị viên.',
            'redirect' => 'admin.php',
        ]);
    }

    // 12.3. Admin: Lấy danh sách tệp nguồn & tệp xuất của một người dùng
    if ($action === 'admin_get_user_files') {
        if (!is_admin()) {
            json_response(['ok' => false, 'error' => 'Yêu cầu quyền Quản trị viên.'], 403);
        }

        $targetId = (int) ($_GET['user_id'] ?? 0);
        if ($targetId <= 0) {
            json_response(['ok' => false, 'error' => 'Mã người dùng không hợp lệ.'], 400);
        }

        $stmt = db()->prepare("SELECT id, username, email, role, status, created_at FROM users WHERE id = ?");
        $stmt->execute([$targetId]);
        $targetUser = $stmt->fetch();

        if (!$targetUser) {
            json_response(['ok' => false, 'error' => 'Người dùng không tồn tại.'], 404);
        }

        // Tệp nguồn đã tải lên
        $docsStmt = db()->prepare("
            SELECT id, original_name, stored_name, file_size, page_count, status, created_at
            FROM documents
            WHERE user_id = ?
            ORDER BY id DESC
        ");
        $docsStmt->execute([$targetId]);
        $documents = $docsStmt->fetchAll();

        // Tệp kết quả đã xuất
        $outsStmt = db()->prepare("
            SELECT o.id, o.output_name, o.stored_name, o.file_size, o.page_count, o.status, o.expires_at, o.created_at
            FROM outputs o
            WHERE o.user_id = ?
            ORDER BY o.id DESC
        ");
        $outsStmt->execute([$targetId]);
        $outputs = $outsStmt->fetchAll();

        json_response([
            'ok'        => true,
            'user'      => $targetUser,
            'documents' => $documents,
            'outputs'   => $outputs,
        ]);
    }

    // 13. Admin: Dọn dẹp tác vụ lỗi
    if ($action === 'admin_cleanup') {
        if (!is_admin()) {
            json_response(['ok' => false, 'error' => 'Yêu cầu quyền Quản trị viên.'], 403);
        }

        // Xóa các tác vụ thất bại
        $delJobs = db()->exec("DELETE FROM export_jobs WHERE status = 'failed'");

        json_response([
            'ok'      => true,
            'cleaned' => (int) $delJobs,
        ]);
    }

    // 13.1. Admin: Giám sát danh sách tác vụ (Jobs)
    if ($action === 'admin_jobs') {
        if (!is_admin()) {
            json_response(['ok' => false, 'error' => 'Yêu cầu quyền Quản trị viên.'], 403);
        }

        $jobsStmt = db()->query("
            SELECT 
                j.id, 
                j.user_id, 
                u.username, 
                u.email, 
                j.plan_id, 
                j.plan_snapshot, 
                j.status, 
                j.error_message, 
                j.created_at, 
                j.completed_at
            FROM export_jobs j
            LEFT JOIN users u ON u.id = j.user_id
            ORDER BY j.id DESC
            LIMIT 100
        ");
        $rawJobs = $jobsStmt->fetchAll();

        $jobs = [];
        foreach ($rawJobs as $job) {
            $plan = json_decode($job['plan_snapshot'] ?? '{}', true);
            $pages = is_array($plan['pages'] ?? null) ? $plan['pages'] : [];

            // Phân tích loại tác vụ từ cấu trúc snapshot
            $docIds = [];
            $hasRotation = false;
            foreach ($pages as $p) {
                if (isset($p['docId'])) {
                    $docIds[$p['docId']] = true;
                }
                if (!empty($p['rotation']) && (int) $p['rotation'] !== 0) {
                    $hasRotation = true;
                }
            }
            $docCount = count($docIds);

            if ($docCount > 1) {
                $jobType = 'merge';
                $jobTypeLabel = 'Ghép PDF';
            } elseif ($hasRotation) {
                $jobType = 'rotate';
                $jobTypeLabel = 'Xoay trang';
            } elseif (!empty($pages) && count($pages) > 0) {
                $jobType = 'split';
                $jobTypeLabel = 'Tách / Trích trang';
            } else {
                $jobType = 'export';
                $jobTypeLabel = 'Xuất tài liệu';
            }

            $jobs[] = [
                'id'             => (int) $job['id'],
                'user_id'        => (int) $job['user_id'],
                'username'       => $job['username'] ?? 'Người dùng đã xóa',
                'email'          => $job['email'] ?? '—',
                'job_type'       => $jobType,
                'job_type_label' => $jobTypeLabel,
                'status'         => $job['status'],
                'error_message'  => $job['error_message'],
                'page_count'     => count($pages),
                'created_at'     => $job['created_at'],
                'completed_at'   => $job['completed_at'],
            ];
        }

        json_response([
            'ok'   => true,
            'jobs' => $jobs,
        ]);
    }

    // 13.2. Admin: Thử lại tác vụ xuất bị lỗi (Retry Job)
    if ($action === 'admin_retry_job') {
        if (!is_admin()) {
            json_response(['ok' => false, 'error' => 'Yêu cầu quyền Quản trị viên.'], 403);
        }

        $jobId = (int) ($_POST['job_id'] ?? 0);
        if ($jobId <= 0) {
            json_response(['ok' => false, 'error' => 'Mã tác vụ không hợp lệ.'], 400);
        }

        $stmt = db()->prepare("
            UPDATE export_jobs 
            SET status = 'pending', error_message = NULL, completed_at = NULL 
            WHERE id = ?
        ");
        $stmt->execute([$jobId]);

        json_response([
            'ok'      => true,
            'message' => "Đã đưa tác vụ #{$jobId} về trạng thái chờ xử lý lại.",
        ]);
    }

    // 13.3. Admin: Lấy cấu hình hệ thống
    if ($action === 'admin_get_settings') {
        if (!is_admin()) {
            json_response(['ok' => false, 'error' => 'Yêu cầu quyền Quản trị viên.'], 403);
        }

        json_response([
            'ok'       => true,
            'settings' => get_all_system_settings(),
        ]);
    }

    // 13.4. Admin: Lưu cấu hình hệ thống
    if ($action === 'admin_save_settings') {
        if (!is_admin()) {
            json_response(['ok' => false, 'error' => 'Yêu cầu quyền Quản trị viên.'], 403);
        }

        $maxFileMb        = (int) ($_POST['max_file_mb'] ?? 0);
        $maxPages         = (int) ($_POST['max_pages'] ?? 0);
        $executionTimeout = (int) ($_POST['execution_timeout'] ?? 0);
        $freeDailyExports = (int) ($_POST['free_daily_exports'] ?? 0);

        // Kiểm tra tính hợp lệ dữ liệu (Validation)
        if ($maxFileMb < 1 || $maxFileMb > 500) {
            json_response(['ok' => false, 'error' => 'Giới hạn dung lượng tải lên tối đa phải từ 1 đến 500 MB.'], 400);
        }
        if ($maxPages < 10 || $maxPages > 2000) {
            json_response(['ok' => false, 'error' => 'Số trang tối đa mỗi lần xử lý phải từ 10 đến 2000 trang.'], 400);
        }
        if ($executionTimeout < 10 || $executionTimeout > 600) {
            json_response(['ok' => false, 'error' => 'Thời gian timeout xử lý phải từ 10 đến 600 giây.'], 400);
        }
        if ($freeDailyExports < 1 || $freeDailyExports > 100) {
            json_response(['ok' => false, 'error' => 'Giới hạn lượt xuất miễn phí trong ngày phải từ 1 đến 100 lượt.'], 400);
        }

        set_system_setting('max_file_mb', $maxFileMb);
        set_system_setting('max_pages', $maxPages);
        set_system_setting('execution_timeout', $executionTimeout);
        set_system_setting('free_daily_exports', $freeDailyExports);

        json_response([
            'ok'       => true,
            'message'  => 'Lưu cấu hình hệ thống thành công!',
            'settings' => get_all_system_settings(),
        ]);
    }

    // 13.5. Admin: Dọn dẹp tệp tạm / tệp rác (> 24h) an toàn
    if ($action === 'admin_cleanup_storage') {
        if (!is_admin()) {
            json_response(['ok' => false, 'error' => 'Yêu cầu quyền Quản trị viên.'], 403);
        }

        $cutoffTime   = time() - 86400; // Đã quá 24 giờ
        $freedBytes   = 0;
        $deletedFiles = 0;

        // 1. Lấy danh sách tên tệp nguồn hợp lệ (status <> 'deleted') để TUYỆT ĐỐI KHÔNG XÓA NHẦM
        $activeDocRows = db()->query("SELECT stored_name FROM documents WHERE status <> 'deleted'")->fetchAll(PDO::FETCH_COLUMN);
        $activeDocsMap = array_flip($activeDocRows);

        // 2. Lấy danh sách tên tệp kết quả hợp lệ (chưa hết hạn)
        $activeOutRows = db()->query("SELECT stored_name FROM outputs WHERE expires_at IS NULL OR expires_at > NOW()")->fetchAll(PDO::FETCH_COLUMN);
        $activeOutsMap = array_flip($activeOutRows);

        // 3. Quét và dọn dẹp thư mục storage/outputs (Tệp kết quả đã hết hạn > 24h)
        $outputsDir = STORAGE_ROOT . '/outputs';
        if (is_dir($outputsDir)) {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($outputsDir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST,
                RecursiveIteratorIterator::CATCH_GET_CHILD
            );
            foreach ($it as $file) {
                if ($file->isFile()) {
                    $filename = $file->getFilename();
                    $mtime    = $file->getMTime();
                    // Nếu tệp không còn trong danh sách active outputs VÀ đã qua 24 giờ
                    if (!isset($activeOutsMap[$filename]) && $mtime < $cutoffTime) {
                        $freedBytes += $file->getSize();
                        if (@unlink($file->getPathname())) {
                            $deletedFiles++;
                        }
                    }
                }
            }
        }

        // 4. Quét và dọn dẹp thư mục storage/uploads (Tệp nguồn bị đánh dấu xóa > 24h)
        $uploadsDir = STORAGE_ROOT . '/uploads';
        if (is_dir($uploadsDir)) {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($uploadsDir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST,
                RecursiveIteratorIterator::CATCH_GET_CHILD
            );
            foreach ($it as $file) {
                if ($file->isFile()) {
                    $filename = $file->getFilename();
                    $mtime    = $file->getMTime();
                    // Nếu tệp không còn nằm trong active documents VÀ đã quá 24h
                    if (!isset($activeDocsMap[$filename]) && $mtime < $cutoffTime) {
                        $freedBytes += $file->getSize();
                        if (@unlink($file->getPathname())) {
                            $deletedFiles++;
                        }
                    }
                }
            }
        }

        // 5. Đồng bộ xóa các bản ghi outputs đã hết hạn quá 24h trong CSDL
        db()->exec("DELETE FROM outputs WHERE expires_at IS NOT NULL AND expires_at < (NOW() - INTERVAL 1 DAY)");

        // 6. Xóa các tác vụ xuất thất bại cũ quá 24h
        db()->exec("DELETE FROM export_jobs WHERE status = 'failed' AND created_at < (NOW() - INTERVAL 1 DAY)");

        json_response([
            'ok'            => true,
            'deleted_files' => $deletedFiles,
            'freed_bytes'   => $freedBytes,
            'freed_mb'      => round($freedBytes / (1024 * 1024), 2),
            'message'       => "Đã giải phóng thành công {$deletedFiles} tệp tạm (" . round($freedBytes / (1024 * 1024), 2) . " MB).",
        ]);
    }

    // 14. Tạo đơn hàng nâng cấp Pro (VIP)
    if ($action === 'create_order') {
        if (!is_payment_configured()) {
            json_response([
                'ok'    => false,
                'error' => 'Tính năng thanh toán tạm thời chưa khả dụng. Vui lòng liên hệ quản trị viên.',
            ], 503);
        }
        $planType = $_POST['plan_type'] ?? 'monthly'; // 'monthly' hoặc 'yearly'
        $paymentMethod = strtoupper(trim($_POST['payment_method'] ?? 'PAYOS'));

        if (!in_array($paymentMethod, ['PAYOS', 'SEPAY'], true)) {
            $paymentMethod = 'PAYOS';
        }

        $plansConfig = [
            'monthly' => [
                'name'          => 'Gói Pro 1 Tháng',
                'amount'        => 99000,
                'duration_days' => 30,
            ],
            'yearly' => [
                'name'          => 'Gói Pro 1 Năm (Tiết kiệm 25%)',
                'amount'        => 899000,
                'duration_days' => 365,
            ],
        ];

        if (!isset($plansConfig[$planType])) {
            json_response(['ok' => false, 'error' => 'Gói dịch vụ không hợp lệ.'], 400);
        }

        $selectedPlan = $plansConfig[$planType];
        
        // Sinh order_code dạng số nguyên dương duy nhất (chuẩn PayOS & SePay)
        $orderCode = (int) (time() . rand(100, 999));

        $stmt = db()->prepare("
            INSERT INTO orders (order_code, user_id, amount, duration_days, plan_name, status, payment_method)
            VALUES (?, ?, ?, ?, ?, 'PENDING', ?)
        ");
        $stmt->execute([
            $orderCode,
            $userId,
            $selectedPlan['amount'],
            $selectedPlan['duration_days'],
            $selectedPlan['name'],
            $paymentMethod,
        ]);

        $orderId = (int) db()->lastInsertId();

        // Tạo link ảnh VietQR để quét thanh toán tự động
        $transferContent = 'PDF' . $orderCode;
        $vietQrUrl = sprintf(
            'https://img.vietqr.io/image/%s-%s-compact2.png?amount=%d&addInfo=%s&accountName=%s',
            urlencode(PAYMENT_BANK_ID),
            urlencode(PAYMENT_ACCOUNT_NO),
            $selectedPlan['amount'],
            urlencode($transferContent),
            urlencode(PAYMENT_ACCOUNT_NAME)
        );

        json_response([
            'ok'    => true,
            'order' => [
                'id'               => $orderId,
                'order_code'       => $orderCode,
                'plan_name'        => $selectedPlan['name'],
                'amount'           => $selectedPlan['amount'],
                'amount_formatted' => number_format($selectedPlan['amount'], 0, ',', '.') . ' đ',
                'duration_days'    => $selectedPlan['duration_days'],
                'payment_method'   => $paymentMethod,
                'bank_id'          => PAYMENT_BANK_ID,
                'account_no'       => PAYMENT_ACCOUNT_NO,
                'account_name'     => PAYMENT_ACCOUNT_NAME,
                'transfer_content' => $transferContent,
                'qr_url'           => $vietQrUrl,
            ],
        ]);
    }

    // 15. Kiểm tra trạng thái đơn hàng (Polling từ frontend)
    if ($action === 'check_order') {
        $orderCode = trim($_GET['order_code'] ?? $_POST['order_code'] ?? '');
        if (empty($orderCode)) {
            json_response(['ok' => false, 'error' => 'Thiếu mã đơn hàng.'], 400);
        }

        $stmt = db()->prepare("
            SELECT id, order_code, user_id, amount, duration_days, plan_name, status, payment_method, paid_at, created_at
            FROM orders 
            WHERE order_code = ? AND user_id = ? 
            LIMIT 1
        ");
        $stmt->execute([$orderCode, $userId]);
        $order = $stmt->fetch();

        if (!$order) {
            json_response(['ok' => false, 'error' => 'Không tìm thấy đơn hàng.'], 404);
        }

        // Nếu đơn hàng đã thành công, đồng bộ lại VIP session cho user
        if ($order['status'] === 'SUCCESS') {
            sync_user_vip_status($userId);
        }

        json_response([
            'ok'             => true,
            'status'         => $order['status'],
            'is_success'     => ($order['status'] === 'SUCCESS'),
            'paid_at'        => $order['paid_at'],
            'vip_expires_at' => $_SESSION['vip_expires_at'] ?? null,
            'role'           => get_user_role(),
        ]);
    }

    // 16. Lịch sử đơn hàng của người dùng
    if ($action === 'order_history') {
        $stmt = db()->prepare("
            SELECT id, order_code, amount, duration_days, plan_name, status, payment_method, paid_at, created_at
            FROM orders 
            WHERE user_id = ? 
            ORDER BY id DESC 
            LIMIT 50
        ");
        $stmt->execute([$userId]);

        json_response([
            'ok'     => true,
            'orders' => $stmt->fetchAll(),
        ]);
    }

    json_response(['ok' => false, 'error' => 'Thao tác không được hỗ trợ.'], 400);

} catch (Throwable $e) {
    json_response([
        'ok'    => false,
        'error' => ($e instanceof PDOException)
            ? 'Lỗi cơ sở dữ liệu. Hãy kiểm tra schema.sql và config.php.'
            : $e->getMessage()
    ], 500);
}
