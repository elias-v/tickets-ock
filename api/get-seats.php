<?php
require_once __DIR__ . '/../config.php';

header('Access-Control-Allow-Origin: https://tickets.oratorienchor-kreuzlingen.ch');
header('Vary: Origin');
header('Content-Type: application/json; charset=utf-8');

$db = getDb();

// Auto-migrate is_bodan column
try {
    $db->exec("ALTER TABLE seats ADD COLUMN is_bodan TINYINT(1) NOT NULL DEFAULT 0 AFTER status");
    $db->exec("UPDATE seats SET is_bodan = 1 WHERE status = 'bodan'");
    $db->exec("UPDATE seats SET status = 'available' WHERE status = 'bodan'");
} catch (Exception $e) {}

// Expire old pending reservations
$db->prepare("
    UPDATE reservations 
    SET status = 'expired' 
    WHERE status = 'pending' AND expires_at <= NOW()
")->execute();

// Get all seats with is_bodan
$seats = $db->query("
    SELECT s.id, s.seat_number, s.`row_number`, s.category, s.section, s.col_pos, s.status, s.is_bodan
    FROM seats s
    ORDER BY s.`row_number`, s.col_pos
")->fetchAll();

// Get currently pending seat numbers
$pendingSeats = $db->prepare("
    SELECT seats_json FROM reservations 
    WHERE status = 'pending' AND expires_at > NOW()
");
$pendingSeats->execute();
$pendingNumbers = [];
while ($row = $pendingSeats->fetch()) {
    $nums = json_decode($row['seats_json'], true) ?: [];
    $pendingNumbers = array_merge($pendingNumbers, $nums);
}
$pendingSet = array_flip($pendingNumbers);

// Get confirmed reservation seat numbers
$confirmedStmt = $db->query("
    SELECT seats_json FROM reservations WHERE status = 'confirmed'
");
$confirmedNumbers = [];
while ($row = $confirmedStmt->fetch()) {
    $nums = json_decode($row['seats_json'], true) ?: [];
    $confirmedNumbers = array_merge($confirmedNumbers, $nums);
}
$confirmedSet = array_flip($confirmedNumbers);

// Get settings
$settings = [];
$rows = $db->query("SELECT `key`, `value` FROM settings")->fetchAll();
foreach ($rows as $row) {
    $settings[$row['key']] = $row['value'];
}
$bookingEnabled = ($settings['booking_enabled'] ?? '1') === '1';

$result = [];
foreach ($seats as $seat) {
    $num = (int)$seat['seat_number'];
    $status = $seat['status'];
    $isBodan = (int)$seat['is_bodan'];

    // Override with pending/confirmed
    if ($status === 'available' && isset($pendingSet[$num])) {
        $status = 'pending';
    }
    if (($status === 'available' || $status === 'pending') && isset($confirmedSet[$num])) {
        $status = 'reserved';
    }

    $result[] = [
        'number' => $num,
        'row' => (int)$seat['row_number'],
        'cat' => $seat['category'],
        'section' => $seat['section'],
        'col' => (int)$seat['col_pos'],
        'status' => $status,
        'is_bodan' => $isBodan,
    ];
}

jsonResponse([
    'seats' => $result,
    'booking_enabled' => $bookingEnabled,
    'prices' => [
        'kat1' => (float)($settings['price_kat1'] ?? DEFAULT_PRICE_KAT1),
        'kat2' => (float)($settings['price_kat2'] ?? DEFAULT_PRICE_KAT2),
        'student' => (float)($settings['price_student'] ?? DEFAULT_PRICE_STUDENT),
    ],
]);
