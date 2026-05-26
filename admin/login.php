<?php
require_once __DIR__ . '/../config.php';

session_start();

$error = '';

// Rate limiting: max 5 failed login attempts per IP per 15 minutes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDb();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    // Auto-create rate_limits table if missing
    $db->exec("CREATE TABLE IF NOT EXISTS rate_limits (
        ip_address VARCHAR(45) NOT NULL,
        action_type VARCHAR(50) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ip_action (ip_address, action_type),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $stmt = $db->prepare("SELECT COUNT(*) FROM rate_limits WHERE ip_address = ? AND action_type = 'admin_login' AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $stmt->execute([$ip]);
    $attempts = (int)$stmt->fetchColumn();

    if ($attempts >= 5) {
        $error = 'Zu viele fehlgeschlagene Anmeldeversuche. Bitte warte 15 Minuten.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        $stmt = $db->prepare("SELECT id, username, password_hash FROM admin_users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_user'] = $user['username'];
            header('Location: index.php');
            exit;
        }

        $db->prepare("INSERT INTO rate_limits (ip_address, action_type) VALUES (?, 'admin_login')")->execute([$ip]);
        $error = 'Ungültiger Benutzername oder Passwort.';
    }
}
?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login – Oratorienchor Kreuzlingen</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', -apple-system, sans-serif; background: #f5f3ef; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .login-card { background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); padding: 40px; max-width: 400px; width: 90%; }
        .login-card h1 { font-size: 1.3rem; margin-bottom: 24px; color: #2c3e50; text-align: center; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 4px; }
        .form-group input { width: 100%; padding: 10px 12px; border: 1px solid #e0dcd4; border-radius: 6px; font-size: 0.9rem; font-family: inherit; }
        .form-group input:focus { outline: none; border-color: #c8a96e; box-shadow: 0 0 0 3px rgba(200,169,110,0.15); }
        .pw-wrap { position: relative; }
        .pw-wrap input { padding-right: 40px; }
        .pw-toggle { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 18px; line-height: 1; color: #888; padding: 4px; }
        .pw-toggle:hover { color: #555; }
        .btn { width: 100%; padding: 12px; background: #c8a96e; color: #fff; border: none; border-radius: 6px; font-size: 1rem; font-weight: 600; cursor: pointer; font-family: inherit; }
        .btn:hover { background: #b8974d; }
        .error { color: #e74c3c; font-size: 0.85rem; margin-bottom: 16px; text-align: center; }
    </style>
</head>
<body>
<div class="login-card">
    <h1>Admin-Bereich</h1>
    <h2 style="font-size:0.95rem;text-align:center;color:#888;margin-bottom:20px;">Oratorienchor Kreuzlingen</h2>
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post">
        <div class="form-group">
            <label for="username">Benutzername</label>
            <input type="text" id="username" name="username" required autocomplete="username">
        </div>
        <div class="form-group">
            <label for="password">Passwort</label>
            <div class="pw-wrap">
                <input type="password" id="password" name="password" required autocomplete="current-password">
                <button type="button" class="pw-toggle" onclick="togglePw('password', this)" tabindex="-1">&#128065;</button>
            </div>
        </div>
        <button type="submit" class="btn">Anmelden</button>
    </form>
</div>
<script>
function togglePw(id, btn) {
    const input = document.getElementById(id);
    input.type = input.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>
