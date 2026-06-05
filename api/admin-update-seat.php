<?php
require_once __DIR__ . '/../config.php';

session_start();
if (!isset($_SESSION['admin_id'])) {
    jsonResponse(['error' => 'Nicht angemeldet'], 401);
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    jsonResponse(['error' => 'Ungültige Anfrage.'], 400);
}

// CSRF validation
if (!hash_equals($_SESSION['csrf_token'] ?? '', $input['csrf_token'] ?? '')) {
    jsonResponse(['error' => 'Ungültiges CSRF-Token.'], 403);
}

$seatNumber = (int)($input['seat_number'] ?? 0);
$status     = $input['status'] ?? '';
$isBodan    = isset($input['is_bodan']) ? (int)$input['is_bodan'] : null;

if ($seatNumber < 1) {
    jsonResponse(['error' => 'Ungültige Platznummer.'], 400);
}

$db = getDb();

// Auto-migrate is_bodan column
try {
    $db->exec("ALTER TABLE seats ADD COLUMN is_bodan TINYINT(1) NOT NULL DEFAULT 0 AFTER status");
} catch (Exception $e) {}

if ($status !== null && in_array($status, ['available', 'disabled', 'reserved'], true)) {
    // When unreserving a seat, also delete any confirmed/pending reservations containing this seat
    if ($status === 'available') {
        $stmt = $db->prepare("SELECT id, seats_json, discount_type, delivery_option FROM reservations WHERE status IN ('confirmed', 'pending')");
        $stmt->execute();
        while ($row = $stmt->fetch()) {
            $nums = json_decode($row['seats_json'], true) ?: [];
            if (in_array($seatNumber, $nums)) {
                $nums = array_values(array_diff($nums, [$seatNumber]));
                if (empty($nums)) {
                    $db->prepare("DELETE FROM reservations WHERE id = ?")->execute([$row['id']]);
                } else {
                    $db->prepare("UPDATE reservations SET seats_json = ? WHERE id = ?")->execute([json_encode($nums), $row['id']]);
                    // Recalculate total_amount
                    $placeholders = implode(',', array_fill(0, count($nums), '?'));
                    $catStmt = $db->prepare("SELECT seat_number, category FROM seats WHERE seat_number IN ($placeholders)");
                    $catStmt->execute($nums);
                    $catMap = [];
                    foreach ($catStmt->fetchAll() as $cr) {
                        $catMap[(int)$cr['seat_number']] = $cr['category'];
                    }
                    $newTotal = 0;
                    foreach ($nums as $n) {
                        $cat = $catMap[$n] ?? '2';
                        $price = $row['discount_type'] === 'student'
                            ? (float)getSetting('price_student', DEFAULT_PRICE_STUDENT)
                            : ($cat === '1'
                                ? (float)getSetting('price_kat1', DEFAULT_PRICE_KAT1)
                                : (float)getSetting('price_kat2', DEFAULT_PRICE_KAT2));
                        $newTotal += $price;
                    }
                    if (($row['delivery_option'] ?? 'pickup') === 'mail') {
                        $newTotal += DELIVERY_SURCHARGE;
                    }
                    $db->prepare("UPDATE reservations SET total_amount = ? WHERE id = ?")->execute([$newTotal, $row['id']]);
                }
            }
        }
    }

    $updateStmt = $db->prepare("UPDATE seats SET status = ? WHERE seat_number = ?");
    $updateStmt->execute([$status, $seatNumber]);
}

if ($isBodan !== null) {
    $stmt = $db->prepare("UPDATE seats SET is_bodan = ? WHERE seat_number = ?");
    $stmt->execute([$isBodan, $seatNumber]);
}

// Rebuild settings from actual seat data
$disabled = $db->query("SELECT seat_number FROM seats WHERE status = 'disabled' ORDER BY seat_number")->fetchAll(PDO::FETCH_COLUMN);
$bodan    = $db->query("SELECT seat_number FROM seats WHERE is_bodan = 1 ORDER BY seat_number")->fetchAll(PDO::FETCH_COLUMN);

$settingsStmt = $db->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
$settingsStmt->execute(['disabled_plaetze', implode(',', $disabled)]);
$settingsStmt->execute(['bodan_plaetze', implode(',', $bodan)]);

jsonResponse(['success' => true]);
