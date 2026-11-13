<?php
require __DIR__ . '/config.php';

// Nếu đã đăng nhập, chuyển hướng sang trang bảng điều khiển
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

if (!empty($_GET['registered'])) {
    $success = 'Tạo tài khoản thành công! Bạn có thể đăng nhập ngay bây giờ.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = db()->prepare('SELECT id, username, password_hash, role, status FROM users WHERE username = ? OR email = ? LIMIT 1');
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            if (($user['status'] ?? 'active') === 'banned') {
                $error = 'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ quản trị viên.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id']  = (int) $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']     = $user['role'] ?? 'user';

                header('Location: dashboard.php');
                exit;
            }
        } else {
            $error = 'Tên đăng nhập/email hoặc mật khẩu không đúng.';
        }
    } catch (Throwable $e) {
        $error = 'Không kết nối được CSDL. Hãy nhập schema.sql vào MySQL và kiểm tra config.php.';
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PDF Studio · Đăng nhập</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-bg">
    <main class="auth-wrap">
        <div class="brand">
            <span class="brand-icon">P</span>
            <span>PDF<span class="brand-light">Studio</span></span>
        </div>

        <section class="auth-card">
            <p class="eyebrow">CÔNG CỤ PDF TRỰC TUYẾN</p>
            <h1>Chào mừng trở lại</h1>
            <p class="muted">Đăng nhập để quản lý tài liệu và kế hoạch xử lý.</p>

            <?php if ($success): ?>
                <div class="alert success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="post" action="index.php">
                <label for="username">Tên đăng nhập hoặc email</label>
                <input id="username" name="username" autocomplete="username" required maxlength="190" placeholder="vd: nguyenvana">

                <label for="password">Mật khẩu</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>

                <button class="btn primary full" type="submit">Đăng nhập</button>
            </form>

            <p class="auth-foot">Chưa có tài khoản? <a href="register.php">Tạo tài khoản</a></p>
        </section>

        <p class="tiny center">Tệp riêng tư theo tài khoản · Tệp gốc không bị ghi đè</p>
    </main>
</body>
</html>
