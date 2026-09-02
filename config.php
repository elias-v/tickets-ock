<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/vendor/autoload.php';

// .env-Datei laden (falls vorhanden)
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (str_contains($line, '=')) {
            putenv(trim($line));
        }
    }
}

define('DB_HOST', getenv('DB_HOST') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: '');
define('DB_USER', getenv('DB_USER') ?: '');
define('DB_PASS', getenv('DB_PASS') ?: '');

define('SITE_URL', getenv('SITE_URL') ?: '');
define('ADMIN_USER', 'admin');

define('RESERVATION_EXPIRY_HOURS', 24);
define('RATE_LIMIT_MAX', 5);
define('RATE_LIMIT_WINDOW_HOURS', 1);

define('EMAIL_FROM', getenv('EMAIL_FROM') ?: '');
define('EMAIL_FROM_NAME', getenv('EMAIL_FROM_NAME') ?: '');

define('SMTP_HOST', getenv('SMTP_HOST') ?: 'localhost');
define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: 25));
define('SMTP_USER', getenv('SMTP_USER') ?: '');
define('SMTP_PASS', getenv('SMTP_PASS') ?: '');

define('DEFAULT_PRICE_KAT1', 40);
define('DEFAULT_PRICE_KAT2', 30);
define('DEFAULT_PRICE_STUDENT', 20);
define('DELIVERY_SURCHARGE', 5);

define('BANK_NAME', 'Thurgauer Kantonalbank');
define('BANK_PC', '85-123-0');
define('BANK_IBAN', 'CH13 0078 4010 0907 5200 1');
define('BANK_INFO_TEXT', 'Bitte bis 5 Tage vor Konzertbeginn einzahlen:');

define('SALES_EMAIL', getenv('SALES_EMAIL') ?: '');

function getDb(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $host = DB_HOST;
        $extraPort = '';
        if (str_contains($host, ':')) {
            [$host, $extraPort] = explode(':', $host, 2);
            $extraPort = ';port=' . (int)$extraPort;
        }
        $dsn = sprintf('mysql:host=%s%s;dbname=%s;charset=utf8mb4', $host, $extraPort, DB_NAME);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}

function getSetting(string $key, string $default = ''): string {
    $db = getDb();
    $stmt = $db->prepare('SELECT `value` FROM settings WHERE `key` = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['value'] : $default;
}

function jsonResponse(mixed $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function ensureLayoutSchema(): void {
    $db = getDb();
    $migrations = [
        "CREATE TABLE IF NOT EXISTS seat_layouts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            cols INT NOT NULL DEFAULT 35,
            `rows` INT NOT NULL DEFAULT 30,
            grid_config JSON NOT NULL,
            cells JSON NOT NULL,
            labels JSON,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "ALTER TABLE seats ADD COLUMN layout_id INT NULL AFTER is_bodan",
        "ALTER TABLE seats ADD INDEX idx_layout (layout_id)",
    ];
    foreach ($migrations as $sql) {
        try { $db->exec($sql); } catch (\Exception $e) {}
    }
}

function getActiveLayout(): ?array {
    $db = getDb();
    ensureLayoutSchema();
    $stmt = $db->query("SELECT id, name, cols, `rows`, grid_config, cells, labels, is_active FROM seat_layouts WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
    $row = $stmt->fetch();
    if (!$row) return null;
    $row['grid_config'] = json_decode($row['grid_config'], true) ?: [];
    $row['cells'] = json_decode($row['cells'], true) ?: [];
    $row['labels'] = json_decode($row['labels'] ?? '[]', true) ?: [];
    return $row;
}

function getAllLayouts(): array {
    $db = getDb();
    ensureLayoutSchema();
    $stmt = $db->query("SELECT id, name, cols, `rows`, is_active, created_at FROM seat_layouts ORDER BY is_active DESC, name ASC");
    return $stmt->fetchAll() ?: [];
}

function sendEmail(string $to, string $subject, string $body): bool {
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        if (SMTP_USER) {
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
            $mail->SMTPSecure = SMTP_PORT === 465
                ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPAuth = false;
        }
        $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $body;
        $mail->send();
        return true;
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log('sendEmail failed: ' . $e->getMessage());
        return false;
    }
}
