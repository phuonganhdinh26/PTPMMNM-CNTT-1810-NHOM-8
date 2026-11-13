<?php
require __DIR__ . '/config.php';

// Nếu đã đăng nhập, chuyển hướng sang trang bảng điều khiển
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $pass     = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if (!preg_match('/^[A-Za-z0-9_.-]{3,60}$/', $username)) {
        $error = 'Tên đăng nhập cần 3–60 ký tự, chỉ gồm chữ, số, dấu chấm, gạch dưới hoặc gạch ngang.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email không hợp lệ.';
    } elseif (strlen($pass) < 8) {
        $error = 'Mật khẩu phải có ít nhất 8 ký tự.';
    } elseif ($pass !== $confirm) {
        $error = 'Mật khẩu nhập lại không khớp.';
    } else {
        try {
            // Tài khoản đầu tiên đăng ký trên hệ thống sẽ tự động là Quản trị viên (Admin)
            $countStmt = db()->query("SELECT COUNT(*) FROM users");
            $isFirstUser = ((int) $countStmt->fetchColumn() === 0);
            $initialRole = $isFirstUser ? 'admin' : 'user';

            $stmt = db()->prepare('INSERT INTO users(username, email, role, status, password_hash) VALUES(?, ?, ?, ?, ?)');
            $stmt->execute([$username, $email, $initialRole, 'active', password_hash($pass, PASSWORD_DEFAULT)]);
            header('Location: index.php?registered=1');
            exit;
        } catch (Throwable $e) {
            $error = 'Tên đăng nhập hoặc email đã tồn tại, hoặc CSDL chưa được cấu hình.';
        }
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tạo tài khoản · PDF Studio</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-bg">
    <main class="auth-wrap">
        <div class="brand">
            <span class="brand-icon">P</span>
            <span>PDF<span class="brand-light">Studio</span></span>
        </div>

        <section class="auth-card">
            <p class="eyebrow">BẮT ĐẦU MIỄN PHÍ</p>
            <h1>Tạo tài khoản</h1>
            <p class="muted">Mỗi tài khoản có vùng tài liệu riêng biệt.</p>

            <?php if ($error): ?>
                <div class="alert error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="post" action="register.php">
                <label for="username">Tên đăng nhập</label>
                <input id="username" name="username" required minlength="3" maxlength="60"
                       value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

                <label for="email">Email</label>
                <input id="email" type="email" name="email" required maxlength="190"
                       value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

                <label for="password">Mật khẩu (ít nhất 8 ký tự)</label>
                <input id="password" type="password" name="password" required minlength="8">

                <label for="confirm">Nhập lại mật khẩu</label>
                <input id="confirm" type="password" name="confirm" required minlength="8">

                <button class="btn primary full" type="submit">Tạo tài khoản</button>
            </form>

            <p class="auth-foot">Đã có tài khoản? <a href="index.php">Đăng nhập</a></p>
        </section>
    </main>
</body>
</html>
