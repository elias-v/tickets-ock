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

try {
    $db = getDb();

    // Auto-migrate missing columns
    foreach ([
        "ALTER TABLE reservations ADD COLUMN delivery_option ENUM('pickup','mail') NOT NULL DEFAULT 'pickup' AFTER discount_type",
        "ALTER TABLE reservations ADD COLUMN notes TEXT NOT NULL DEFAULT '' AFTER address",
    ] as $sql) {
        try { $db->exec($sql); } catch (Exception $e) {}
    }

    $stmt = $db->prepare("SELECT id, customer_name, email, phone, address, notes, seats_json, total_amount, discount_type, delivery_option, status, created_at
        FROM reservations WHERE status IN ('confirmed', 'pending')
        ORDER BY created_at DESC");
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
} catch (Throwable $e) {
    error_log('reservation-by-seat error: ' . $e->getMessage());
    jsonResponse(['error' => 'Interner Fehler: ' . $e->getMessage()], 500);
}