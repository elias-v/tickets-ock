<?php
require_once __DIR__ . '/../config.php';

$token = trim($_GET['token'] ?? '');

if (!$token) {
    die('Kein Bestätigungstoken angegeben.');
}

$db = getDb();

// Auto-migrate missing columns
foreach ([
    "ALTER TABLE reservations ADD COLUMN delivery_option ENUM('pickup','mail') NOT NULL DEFAULT 'pickup' AFTER discount_type",
] as $sql) {
    try { $db->exec($sql); } catch (Exception $e) {}
}

$stmt = $db->prepare("
    SELECT id, customer_name, email, seats_json, total_amount, discount_type, delivery_option, expires_at, status
    FROM reservations WHERE token = ?
");
$stmt->execute([$token]);
$res = $stmt->fetch();

if (!$res) {
    $error = 'Ungültiger oder unbekannter Bestätigungslink.';
} elseif ($res['status'] === 'confirmed') {
    $success = 'Deine Reservierung wurde bereits bestätigt. Vielen Dank!';
} elseif ($res['status'] === 'expired') {
    $error = 'Dieser Bestätigungslink ist abgelaufen. Bitte erstelle eine neue Reservierung.';
} elseif (strtotime($res['expires_at']) < time()) {
    $db->prepare("UPDATE reservations SET status = 'expired' WHERE id = ?")->execute([$res['id']]);
    $error = 'Dieser Bestätigungslink ist abgelaufen. Bitte erstelle eine neue Reservierung.';
} else {
    // Confirm the reservation
    $db->prepare("
        UPDATE reservations SET status = 'confirmed', confirmed_at = NOW() WHERE id = ?
    ")->execute([$res['id']]);

    // Mark seats as reserved in the seats table
    $seatNums = json_decode($res['seats_json'], true) ?: [];
    $updateStmt = $db->prepare("UPDATE seats SET status = 'reserved' WHERE seat_number = ? AND status = 'available'");
    foreach ($seatNums as $num) {
        $updateStmt->execute([(int)$num]);
    }

    $success = 'Deine Reservierung wurde erfolgreich bestätigt!';

    // Send payment info email
    $seatList = implode(', ', json_decode($res['seats_json'], true) ?: []);
    $delivery = $res['delivery_option'] ?? 'pickup';
    $deliveryAddress = $res['address'] ?? '';
    $concertDate = getSetting('concert_date', 'Sonntag, 27. September 2026, 17:00 Uhr');
    $concertLocation = getSetting('concert_location', 'Kirche St. Stefan, Kreuzlingen-Emmishofen');
    $concertHtml = '<p style="font-size:0.9rem;color:#888;">' . htmlspecialchars($concertDate) . '<br>' . htmlspecialchars($concertLocation) . '</p>';
    $subject = 'Ticket-Reservierung erfolgreich';
    $body = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
        body { font-family: -apple-system, sans-serif; color: #333; line-height: 1.6; }
        .footer { margin-top:24px; font-size:0.85rem; color:#888; }
    </style></head><body>
        <h2>Ticket-Reservierung</h2>
        ' . $concertHtml . '
        <p>Sehr geehrte/r ' . htmlspecialchars($res['customer_name']) . ',</p>
        <p>Ihre Reservierung für folgende Plätze ist bestätigt:</p>
        <p><strong>' . htmlspecialchars($seatList) . '</strong></p>
        <p>Gesamtbetrag: <strong>CHF ' . number_format((float)$res['total_amount'], 2) . '</strong></p>
        <p style="font-size:0.9rem;color:#888;">'
            . ($res['discount_type'] === 'student' ? '(Studentenpreis)' : '(Normalpreis)') .
            ($delivery === 'mail' ? '<br>+ Fr. 5.-- Zustellung per Post' : '') .
        '</p>
        <p><strong>Billettbezug:</strong> ' . ($delivery === 'mail' ? 'Zustellung per Post nach Zahlungseingang' . ($deliveryAddress ? ' an: ' . htmlspecialchars($deliveryAddress) : '') : 'Abholung an der Kasse bis 30 Min. vor Konzertbeginn') . '</p>
        ' . ($delivery === 'mail' ? '
        <p>' . htmlspecialchars(BANK_INFO_TEXT) . '</p>
        <table border="0" cellpadding="4" style="margin:12px 0;">
            <tr><td><strong>Bank:</strong></td><td>' . htmlspecialchars(BANK_NAME) . '</td></tr>
            <tr><td><strong>PK:</strong></td><td>' . htmlspecialchars(BANK_PC) . '</td></tr>
            <tr><td><strong>IBAN:</strong></td><td>' . htmlspecialchars(BANK_IBAN) . '</td></tr>
        </table>' : '
        <p style="color:#888;">Die Bezahlung erfolgt bei der Abholung an der Kasse.</p>') . '
        <div class="footer">
            <p>Oratorienchor Kreuzlingen<br>
            <a href="https://oratorienchor-kreuzlingen.ch">oratorienchor-kreuzlingen.ch</a></p>
        </div>
    </body></html>';

    sendEmail($res['email'], $subject, $body);
}
?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservierung bestätigen – Oratorienchor Kreuzlingen</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', -apple-system, sans-serif; background: #f5f3ef; color: #1a1a2e; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); padding: 40px; max-width: 500px; width: 90%; text-align: center; }
        .card h2 { margin-bottom: 12px; }
        .card .success { color: #27ae60; }
        .card .error { color: #e74c3c; }
        .card p { margin-bottom: 8px; line-height: 1.6; color: #555; }
        .card .btn { display: inline-block; margin-top: 16px; padding: 12px 24px; background: #c8a96e; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; }
        .card .btn:hover { background: #b8974d; }
    </style>
</head>
<body>
<div class="card">
    <?php if (isset($error)): ?>
        <h2 class="error">&#10060; Fehler</h2>
        <p><?= htmlspecialchars($error) ?></p>
        <a class="btn" href="<?= SITE_URL ?>">Zurück zur Ticket-Seite</a>
    <?php else: ?>
        <h2 class="success">&#10004; Reservierung bestätigt!</h2>
        <p><?= htmlspecialchars($success) ?></p>
        <p style="font-size:0.85rem;color:#888;margin-top:8px;">
            Du erhältst in Kürze eine E-Mail mit den Zahlungsinformationen.
        </p>
        <a class="btn" href="<?= SITE_URL ?>">Zurück zur Startseite</a>
    <?php endif; ?>
</div>
</body>
</html>
