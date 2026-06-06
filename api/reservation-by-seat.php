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
    FROM reservations WHERE status IN ('confirmed', 'pending') AND JSON_CONTAINS(seats_json, CAST(? AS JSON))
    ORDER BY created_at DESC LIMIT 1");
$stmt->execute([(string)$seatNumber]);
$result = $stmt->fetch();

if (!$result) {
    jsonResponse(['error' => 'Keine Reservierung für diesen Platz gefunden.'], 404);
}

jsonResponse(['reservation' => $result]);