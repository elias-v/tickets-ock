<?php
require_once __DIR__ . '/../config.php';

session_start();
if (!isset($_SESSION['admin_id'])) {
    jsonResponse(['error' => 'Nicht angemeldet'], 401);
}

header('Content-Type: application/json; charset=utf-8');

$seatNumber = (int)($_GET['seat'] ?? 0);
if ($seatNumber < 1) {
    jsonResponse(['error' => 'Ungültige Platznummer.'], 400);
}

$db = getDb();

$stmt = $db->prepare("SELECT id, customer_name, email, phone, address, notes, seats_json, total_amount, discount_type, delivery_option, status, created_at
    FROM reservations WHERE status IN ('confirmed', 'pending') ORDER BY created_at DESC");
$stmt->execute();

$result = null;
while ($row = $stmt->fetch()) {
    $nums = json_decode($row['seats_json'], true) ?: [];
    if (in_array($seatNumber, $nums)) {
        $result = $row;
        break;
    }
}

if (!$result) {
    jsonResponse(['error' => 'Keine Reservierung für diesen Platz gefunden.'], 404);
}

jsonResponse(['reservation' => $result]);