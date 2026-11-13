<?php
/**
 * Cấu hình hệ thống PDF Studio
 * Quản lý kết nối cơ sở dữ liệu, phân quyền và thư mục lưu trữ
 */

// Thông tin kết nối MySQL / MariaDB
const DB_HOST = '127.0.0.1';
const DB_PORT = 3306; // Cổng MariaDB XAMPP (mặc định 3306)
const DB_NAME = 'pdf_tool_db';
const DB_USER = 'root';
const DB_PASS = '';

// Giới hạn mặc định (cho người dùng thường)
const MAX_FILE_BYTES = 20971520; // 20 MB / tệp
const MAX_PAGES = 200;           // Tối đa 200 trang / tệp
const MAX_FILES_PER_UPLOAD = 10; // Tối đa 10 tệp mỗi lần tải

// Thư mục lưu trữ an toàn (nằm ngoài tầm truy cập trực tiếp qua web)
const STORAGE_ROOT = __DIR__ . '/storage';

// Cấu hình cổng thanh toán Webhook (PayOS / SePay)
const PAYOS_CLIENT_ID = 'your_payos_client_id';
const PAYOS_API_KEY = 'your_payos_api_key';
const PAYOS_CHECKSUM_KEY = 'your_payos_checksum_key'; // Khóa Checksum từ PayOS Dashboard
const SEPAY_API_KEY = 'your_sepay_api_key';           // API Key từ SePay Dashboard

// Thông tin tài khoản ngân hàng nhận tiền quét VietQR
// ⚠️ BẮT BUỘC điền tài khoản THẬT của bạn. Để trống => hệ thống sẽ KHÔNG tạo mã QR.
const PAYMENT_BANK_ID = '';                   // Mã ngân hàng: MB, VCB, TCB, ACB, TPB, BIDV, ICB (VietinBank)...
const PAYMENT_ACCOUNT_NO = '';        // Số tài khoản nhận tiền của bạn
const PAYMENT_ACCOUNT_NAME = ''; // Tên chủ tài khoản, IN HOA KHÔNG DẤU

/**
 * Kiểm tra đã cấu hình tài khoản nhận tiền thật hay chưa
 */
function is_payment_configured(): bool
{
    return trim(PAYMENT_BANK_ID) !== ''
        && preg_match('/^\d{6,20}$/', trim(PAYMENT_ACCOUNT_NO)) === 1
        && trim(PAYMENT_ACCOUNT_NAME) !== '';
}

// Khởi tạo phiên làm việc (Session)
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * Khởi tạo hoặc tái sử dụng kết nối cơ sở dữ liệu PDO
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    return $pdo;
}

/**
 * Đồng bộ và kiểm tra thời hạn VIP của người dùng (tự động hạ cấp nếu hết hạn)
 */
function sync_user_vip_status(?int $userId = null): array
{
    $userId = $userId ?? (int) ($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        return ['role' => 'guest', 'is_pro' => false, 'vip_expires_at' => null];
    }

    try {
        $stmt = db()->prepare('SELECT id, role, vip_expires_at FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['role' => 'guest', 'is_pro' => false, 'vip_expires_at' => null];
        }

        $role = $user['role'];
        $vipExpiresAt = $user['vip_expires_at'];

        // Nếu là role pro nhưng có ngày hết hạn và đã qua thời hạn -> tự động hạ cấp về user
        if ($role === 'pro' && !empty($vipExpiresAt)) {
            $expiryTime = strtotime($vipExpiresAt);
            if ($expiryTime !== false && $expiryTime < time()) {
                $role = 'user';
                $update = db()->prepare("UPDATE users SET role = 'user' WHERE id = ?");
                $update->execute([$userId]);
            }
        }

        // Cập nhật session nếu là user đang đăng nhập
        if (!empty($_SESSION['user_id']) && (int) $_SESSION['user_id'] === $userId) {
            $_SESSION['role'] = $role;
            $_SESSION['vip_expires_at'] = $vipExpiresAt;
        }

        return [
            'role' => $role,
            'is_pro' => in_array($role, ['pro', 'admin'], true),
            'vip_expires_at' => $vipExpiresAt,
        ];
    } catch (Throwable $e) {
        return [
            'role' => $_SESSION['role'] ?? 'user',
            'is_pro' => in_array($_SESSION['role'] ?? 'user', ['pro', 'admin'], true),
            'vip_expires_at' => $_SESSION['vip_expires_at'] ?? null,
        ];
    }
}

/**
 * Lấy vai trò (Role) của người dùng hiện tại
 */
function get_user_role(): string
{
    if (!empty($_SESSION['user_id'])) {
        // Đồng bộ định kỳ hoặc lấy từ session
        return $_SESSION['role'] ?? 'user';
    }
    return 'guest';
}

/**
 * Kiểm tra xem người dùng hiện tại có phải Admin không
 */
function is_admin(): bool
{
    return get_user_role() === 'admin';
}

/**
 * Kiểm tra xem người dùng hiện tại có phải Pro / VIP không
 */
function is_pro(): bool
{
    return in_array(get_user_role(), ['pro', 'admin'], true);
}

/**
 * Yêu cầu người dùng phải đăng nhập trước khi truy cập
 */
function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: index.php');
        exit;
    }
}

/**
 * Bắt buộc quyền Quản trị viên (Admin), chặn truy cập nếu không đủ quyền
 */
function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        exit('Bạn không có quyền truy cập trang quản trị hệ thống.');
    }
}

/**
 * Lấy cấu hình hệ thống từ bảng system_settings
 */
function get_system_setting(string $key, mixed $default = null): mixed
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            db()->exec("
                CREATE TABLE IF NOT EXISTS system_settings (
                  setting_key VARCHAR(100) PRIMARY KEY,
                  setting_value TEXT NOT NULL,
                  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB
            ");
            $rows = db()->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll();
            foreach ($rows as $r) {
                $cache[$r['setting_key']] = $r['setting_value'];
            }
        } catch (Throwable $e) {
            // Sử dụng mặc định nếu CSDL chưa kết nối
        }
    }

    return $cache[$key] ?? $default;
}

/**
 * Lưu một cấu hình hệ thống vào CSDL
 */
function set_system_setting(string $key, mixed $value): void
{
    $valStr = is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);
    $stmt = db()->prepare("
        INSERT INTO system_settings (setting_key, setting_value) 
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    $stmt->execute([$key, $valStr]);
}

/**
 * Lấy toàn bộ cấu hình hệ thống hiện tại
 */
function get_all_system_settings(): array
{
    return [
        'max_file_mb'        => (int) get_system_setting('max_file_mb', 20),
        'max_pages'          => (int) get_system_setting('max_pages', 200),
        'execution_timeout'  => (int) get_system_setting('execution_timeout', 60),
        'free_daily_exports' => (int) get_system_setting('free_daily_exports', 5),
    ];
}

/**
 * Trả về giới hạn tài nguyên dựa theo Role của tài khoản
 */
function get_user_limits(?string $role = null): array
{
    $role = $role ?? get_user_role();
    $sysSettings = get_all_system_settings();

    return match ($role) {
        'admin' => [
            'role'               => 'admin',
            'label'              => 'Quản trị viên',
            'badge_class'        => 'admin-badge',
            'max_file_bytes'     => 500 * 1024 * 1024,
            'max_file_mb'        => 'Không giới hạn',
            'max_pages'          => 2000,
            'max_files'          => 100,
            'max_docs'           => PHP_INT_MAX,
            'max_plans'          => PHP_INT_MAX,
            'allow_watermark'    => true,
            'allow_numbering'    => true,
            'allow_password'     => true,
            'output_expiry_days' => 0, // Vĩnh viễn
            'can_manage_system'  => true,
        ],
        'pro' => [
            'role'               => 'pro',
            'label'              => 'Thành viên Pro',
            'badge_class'        => 'pro-badge',
            'max_file_bytes'     => 100 * 1024 * 1024, // 100 MB
            'max_file_mb'        => '100 MB',
            'max_pages'          => 500,
            'max_files'          => 30,
            'max_docs'           => PHP_INT_MAX, // Không giới hạn
            'max_plans'          => PHP_INT_MAX, // Không giới hạn
            'allow_watermark'    => true,
            'allow_numbering'    => true,
            'allow_password'     => true,
            'output_expiry_days' => 0, // Vĩnh viễn
            'can_manage_system'  => false,
        ],
        'user' => [
            'role'               => 'user',
            'label'              => 'Thành viên Miễn phí',
            'badge_class'        => 'user-badge',
            'max_file_bytes'     => $sysSettings['max_file_mb'] * 1024 * 1024,
            'max_file_mb'        => $sysSettings['max_file_mb'] . ' MB',
            'max_pages'          => $sysSettings['max_pages'],
            'max_files'          => 10,
            'max_docs'           => 10,  // Tối đa 10 file nguồn
            'max_plans'          => 10,  // Tối đa 10 bản nháp
            'daily_exports'      => $sysSettings['free_daily_exports'],
            'allow_watermark'    => false,
            'allow_numbering'    => false,
            'allow_password'     => false,
            'output_expiry_days' => 7, // 7 ngày
            'can_manage_system'  => false,
        ],
        default => [
            'role'               => 'guest',
            'label'              => 'Khách vãng lai',
            'badge_class'        => 'guest-badge',
            'max_file_bytes'     => 10 * 1024 * 1024,  // 10 MB
            'max_file_mb'        => '10 MB',
            'max_pages'          => 50,
            'max_files'          => 5,
            'max_docs'           => 0,  // Không lưu trữ đám mây
            'max_plans'          => 0,  // Không lưu bản nháp
            'allow_watermark'    => false,
            'allow_numbering'    => false,
            'allow_password'     => false,
            'output_expiry_days' => -1, // Tải ngay (không lưu server)
            'can_manage_system'  => false,
        ],
    };
}

/**
 * Trả về phản hồi JSON chuẩn và kết thúc kịch bản
 */
function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Tạo và trả về đường dẫn thư mục lưu trữ an toàn theo người dùng
 */
function safe_storage_dir(string $kind, int $userId): string
{
    $dir = STORAGE_ROOT . '/' . $kind . '/' . $userId;
    if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
        throw new RuntimeException('Không thể tạo thư mục lưu trữ: ' . $dir);
    }
    return $dir;
}
