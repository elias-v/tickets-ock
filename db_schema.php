<?php
require_once __DIR__ . '/config.php';

echo "Creating database schema...\n";

$dsn = sprintf('mysql:host=%s;charset=utf8mb4', DB_HOST);
$pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo->exec('USE `' . DB_NAME . '`');

$pdo->exec('
    CREATE TABLE IF NOT EXISTS seats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        seat_number INT NOT NULL UNIQUE,
        `row_number` INT NOT NULL,
        category ENUM("1","2") NOT NULL,
        section ENUM("left","right","front_left","front_right") NOT NULL,
        col_pos INT NOT NULL,
        status ENUM("available","reserved","disabled") NOT NULL DEFAULT "available",
        is_bodan TINYINT(1) NOT NULL DEFAULT 0,
        INDEX idx_status (status),
        INDEX idx_row (`row_number`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
');

$pdo->exec('
    CREATE TABLE IF NOT EXISTS reservations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        phone VARCHAR(100) NOT NULL DEFAULT "",
        address TEXT NOT NULL DEFAULT "",
        notes TEXT NOT NULL DEFAULT "",
        seats_json TEXT NOT NULL,
        total_amount DECIMAL(10,2) NOT NULL,
        discount_type ENUM("none","student") NOT NULL DEFAULT "none",
        delivery_option ENUM("pickup","mail") NOT NULL DEFAULT "pickup",
        token VARCHAR(64) NOT NULL UNIQUE,
        status ENUM("pending","confirmed","expired") NOT NULL DEFAULT "pending",
        ip_address VARCHAR(45) NOT NULL DEFAULT "",
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NOT NULL,
        confirmed_at DATETIME DEFAULT NULL,
        INDEX idx_token (token),
        INDEX idx_status (status),
        INDEX idx_email (email),
        INDEX idx_ip (ip_address),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
');

$pdo->exec('
    CREATE TABLE IF NOT EXISTS settings (
        `key` VARCHAR(100) PRIMARY KEY,
        `value` TEXT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
');

$pdo->exec('
    CREATE TABLE IF NOT EXISTS admin_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
');

$pdo->exec('
    CREATE TABLE IF NOT EXISTS rate_limits (
        ip_address VARCHAR(45) NOT NULL,
        action_type VARCHAR(50) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ip_action (ip_address, action_type),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
');

$pdo->exec('TRUNCATE TABLE seats');
$pdo->exec('TRUNCATE TABLE settings');
$pdo->exec('TRUNCATE TABLE admin_users');

$stmt = $pdo->prepare('
    INSERT INTO seats (seat_number, `row_number`, category, section, col_pos, status)
    VALUES (?, ?, ?, ?, ?, ?)
');

function insertSeats(PDO $stmt, int $start, int $end, int $row, string $cat, string $section, int $colStart, string $status = 'available'): void {
    for ($i = $start; $i <= $end; $i++) {
        $col = $colStart + ($i - $start);
        $stmt->execute([$i, $row, $cat, $section, $col, $status]);
    }
}

function insertSeat(PDO $stmt, int $num, int $row, string $cat, string $section, int $col, string $status = 'available'): void {
    $stmt->execute([$num, $row, $cat, $section, $col, $status]);
}

// --- ROW 2 --- Kat. II (blue) ---
foreach ([3,4,5,6] as $i => $col) {
    insertSeat($stmt, [3,4,5,6][$i], 2, '2', 'front_left', $col, 'available');
}
insertSeats($stmt, 41, 50, 2, '2', 'left', 8, 'available');
insertSeats($stmt, 51, 60, 2, '2', 'right', 19, 'available');
foreach ([13,14,15,16,17,18] as $i => $num) {
    insertSeat($stmt, $num, 2, '2', 'front_right', 30 + $i, 'available');
}

// --- ROW 3 --- Kat. I (pink) ---
foreach ([9,10,11,12] as $i => $num) {
    insertSeat($stmt, $num, 3, '2', 'front_left', 3 + $i, 'available');
}
insertSeats($stmt, 61, 70, 3, '1', 'left', 8, 'available');
insertSeats($stmt, 71, 80, 3, '1', 'right', 19, 'available');
foreach ([19,20,21,22,23,24] as $i => $num) {
    insertSeat($stmt, $num, 3, '2', 'front_right', 30 + $i, 'available');
}

// --- ROW 4-18 --- all available except bodan rows
insertSeats($stmt, 81, 100, 4, '1', 'left', 8, 'available');
// Right 91-100
for ($i = 0; $i < 10; $i++) {
    insertSeat($stmt, 91 + $i, 4, '1', 'right', 19 + $i, 'available');
}

$rows5to18 = [
    5 => [102,103,104,105,106,107,108,109,110, 111,112,113,114,115,116,117,118,119,120],
    6 => [122,123,124,125,126,127,128,129,130, 131,132,133,134,135,136,137,138,139,140],
    7 => [142,143,144,145,146,147,148,149,150, 151,152,153,154,155,156,157,158,159,160],
    8 => [], // bodan
    9 => [181,182,183,184,185,186,187,188,189,190, 191,192,193,194,195,196,197,198,199,200],
    10 => [201,202,203,204,205,206,207,208,209,210, 211,212,213,214,215,216,217,218,219,220],
    11 => [], // bodan
    12 => [241,242,243,244,245,246,247,248,249,250, 251,252,253,254,255,256,257,258,259,260],
    13 => [261,262,263,264,265,266,267,268,269,270, 271,272,273,274,275,276,277,278,279,280],
    14 => [], // bodan
    15 => [301,302,303,304,305,306,307,308,309,310, 311,312,313,314,315,316,317,318,319,320],
    16 => [321,322,323,324,325,326,327,328,329,330, 331,332,333,334,335,336,337,338,339,340],
    17 => [], // bodan
    18 => [361,362,363,364,365,366,367,368,369,370, 371,372,373,374,375,376,377,378,379,380],
];

foreach ($rows5to18 as $row => $seats) {
    if (empty($seats)) continue;
    $half = count($seats) / 2;
    for ($i = 0; $i < $half; $i++) {
        $cat = ($row <= 15) ? '1' : '2';
        insertSeat($stmt, $seats[$i], $row, $cat, 'left', 8 + $i, 'available');
    }
    for ($i = $half; $i < count($seats); $i++) {
        $cat = ($row <= 15) ? '1' : '2';
        insertSeat($stmt, $seats[$i], $row, $cat, 'right', 19 + ($i - $half), 'available');
    }
}

// Bodan rows – seeded as available, then marked is_bodan
insertSeats($stmt, 162, 170, 8, '1', 'left', 9, 'available');
insertSeats($stmt, 171, 180, 8, '1', 'right', 19, 'available');
insertSeats($stmt, 221, 240, 11, '1', 'left', 8, 'available');
insertSeats($stmt, 231, 240, 11, '1', 'right', 19, 'available');
insertSeats($stmt, 281, 300, 14, '1', 'left', 8, 'available');
insertSeats($stmt, 291, 300, 14, '1', 'right', 19, 'available');
insertSeats($stmt, 341, 360, 17, '2', 'left', 8, 'available');
insertSeats($stmt, 351, 360, 17, '2', 'right', 19, 'available');

// Also the front bodan rows from earlier
$pdo->exec("UPDATE seats SET is_bodan = 1 WHERE seat_number BETWEEN 51 AND 60");
$pdo->exec("UPDATE seats SET is_bodan = 1 WHERE seat_number BETWEEN 61 AND 70");
$pdo->exec("UPDATE seats SET is_bodan = 1 WHERE seat_number BETWEEN 162 AND 180");
$pdo->exec("UPDATE seats SET is_bodan = 1 WHERE seat_number BETWEEN 221 AND 240");
$pdo->exec("UPDATE seats SET is_bodan = 1 WHERE seat_number BETWEEN 281 AND 300");
$pdo->exec("UPDATE seats SET is_bodan = 1 WHERE seat_number BETWEEN 341 AND 360");

// --- ROWS 19-22 ---
foreach ([19,20,21,22] as $rn) {
    $leftGaps = [387, 407, 427, 447];
    $rightGaps = [394, 414, 434, 454];
    $idx = $rn - 19;
    $leftSeats = [];
    for ($n = 381 + ($rn - 19) * 20; $n <= 390 + ($rn - 19) * 20; $n++) {
        if (in_array($n, $leftGaps)) { $n++; }
        if ($n > 390 + ($rn - 19) * 20) break;
        $leftSeats[] = $n;
    }
    $rightSeats = [];
    for ($n = 391 + ($rn - 19) * 20; $n <= 400 + ($rn - 19) * 20; $n++) {
        if (in_array($n, $rightGaps)) { $n++; }
        if ($n > 400 + ($rn - 19) * 20) break;
        $rightSeats[] = $n;
    }
    // simplified: just hardcode
}

// Hardcode rows 19-22
$rows19_22 = [
    19 => [[381,382,383,384,385,386,388,389,390], [391,392,393,395,396,397,398,399,400]],
    20 => [[401,402,403,404,405,406,408,409,410], [411,412,413,415,416,417,418,419,420]],
    21 => [[421,422,423,424,425,426,428,429,430], [431,432,433,435,436,437,438,439,440]],
    22 => [[448,449,450], [451,452,453,455,456,457,458,459,460]],
];

foreach ($rows19_22 as $rn => [$left, $right]) {
    foreach ($left as $i => $num) {
        insertSeat($stmt, $num, $rn, '2', 'left', 8 + $i, 'available');
    }
    foreach ($right as $i => $num) {
        insertSeat($stmt, $num, $rn, '2', 'right', 19 + $i, 'available');
    }
}

// Empore / Orgel
$emporeRows = [
    23 => [[471,472,473,474,475,476,477], []],
    24 => [[481,482,483,484,485,486,487], [461,462,463,464]],
    25 => [[491,492,493,494,495,496,497], [465,466,467,468]],
    26 => [[501,502,503,504,505,506,507], []],
];

foreach ($emporeRows as $rn => [$left, $right]) {
    foreach ($left as $i => $num) {
        insertSeat($stmt, $num, $rn, '2', 'left', 8 + $i, 'available');
    }
    foreach ($right as $i => $num) {
        insertSeat($stmt, $num, $rn, '2', 'right', 26 + $i, 'available');
    }
}

echo "Seats inserted successfully.\n";

// Rebuild bodan/disabled settings from seat data
$bodanSeats = $pdo->query("SELECT GROUP_CONCAT(seat_number ORDER BY seat_number SEPARATOR ',') FROM seats WHERE is_bodan = 1")->fetchColumn();
$disabledSeats = $pdo->query("SELECT GROUP_CONCAT(seat_number ORDER BY seat_number SEPARATOR ',') FROM seats WHERE status = 'disabled'")->fetchColumn();

// Settings
$pdo->exec("
    INSERT INTO settings (`key`, `value`) VALUES
    ('booking_enabled', '1'),
    ('bodan_plaetze', " . $pdo->quote($bodanSeats ?: '') . "),
    ('disabled_plaetze', " . $pdo->quote($disabledSeats ?: '') . "),
    ('price_kat1', '40'),
    ('price_kat2', '30'),
    ('price_student', '20')
    ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
");

$pdo->exec("
    INSERT INTO admin_users (username, password_hash) VALUES ('admin', '" . password_hash('admin', PASSWORD_DEFAULT) . "')
    ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)
");

echo "Settings and admin user created.\n";
echo "Schema created successfully!\n";
echo "Default admin login: admin / admin\n";
echo "IMPORTANT: Change the password after first login!\n";
