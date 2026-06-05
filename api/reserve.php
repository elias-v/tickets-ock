<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://tickets.oratorienchor-kreuzlingen.ch');
header('Vary: Origin');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

// Auto-migrate missing columns
$db = getDb();
foreach ([
    "ALTER TABLE reservations ADD COLUMN delivery_option ENUM('pickup','mail') NOT NULL DEFAULT 'pickup' AFTER discount_type",
    "ALTER TABLE reservations ADD COLUMN notes TEXT NOT NULL DEFAULT '' AFTER address",
    "ALTER TABLE seats ADD COLUMN is_bodan TINYINT(1) NOT NULL DEFAULT 0 AFTER status",
    "UPDATE seats SET is_bodan = 1 WHERE status = 'bodan'",
    "UPDATE seats SET status = 'available' WHERE status = 'bodan'",
] as $sql) {
    try { $db->exec($sql); } catch (Exception $e) {}
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    jsonResponse(['error' => 'Ungültige Anfrage.'], 400);
}

// --- Validate input ---
$seats  = $input['seats'] ?? [];
$name   = trim($input['name'] ?? '');
$email  = trim($input['email'] ?? '');
$phone  = trim($input['phone'] ?? '');
$street = trim($input['street'] ?? '');
$city   = trim($input['city'] ?? '');
$notes  = trim($input['notes'] ?? '');
$isStudent = !empty($input['is_student']);
$delivery = $input['delivery'] ?? 'pickup';

if (!in_array($delivery, ['pickup', 'mail'], true)) {
    $delivery = 'pickup';
}

// Build address string: only relevant for postal delivery
$address = '';
if ($delivery === 'mail') {
    $address = trim($street . ', ' . $city, ', ');
}

if (!is_array($seats) || count($seats) === 0) {
    jsonResponse(['error' => 'Bitte wähle mindestens einen Platz aus.'], 400);
}
if ($name === '') {
    jsonResponse(['error' => 'Bitte gib deinen Namen ein.'], 400);
}
if (!preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email)) {
    jsonResponse(['error' => 'Bitte gib eine gültige E-Mail-Adresse ein.'], 400);
}
if (count($seats) > 50) {
    jsonResponse(['error' => 'Maximal 50 Plätze pro Bestellung.'], 400);
}

// --- Rate limiting ---
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

$stmt = $db->prepare("
    SELECT COUNT(*) FROM rate_limits
    WHERE ip_address = ?
      AND action_type = 'reserve'
      AND created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
");
$stmt->execute([$ip, RATE_LIMIT_WINDOW_HOURS]);
$recentCount = (int)$stmt->fetchColumn();

if ($recentCount >= RATE_LIMIT_MAX) {
    jsonResponse([
        'error' => 'Du hast zu viele Anfragen in kurzer Zeit gesendet. Bitte warte eine Weile.'
    ], 429);
}

// Log this attempt
$db->prepare("INSERT INTO rate_limits (ip_address, action_type) VALUES (?, 'reserve')")->execute([$ip]);

// --- Check booking enabled ---
$bookingEnabled = getSetting('booking_enabled', '1');
if ($bookingEnabled !== '1') {
    jsonResponse(['error' => 'Die Ticketreservierung ist momentan deaktiviert.'], 403);
}

$db->beginTransaction();

// --- Validate seats (with row locks) ---
$seatNumbers = array_map('intval', $seats);
$placeholders = implode(',', array_fill(0, count($seatNumbers), '?'));
$stmt = $db->prepare("SELECT seat_number, category, status, is_bodan FROM seats WHERE seat_number IN ($placeholders) FOR UPDATE");
$stmt->execute($seatNumbers);
$seatRows = $stmt->fetchAll();

if (count($seatRows) !== count($seatNumbers)) {
    $db->rollBack();
    jsonResponse(['error' => 'Einige ausgewählte Plätze existieren nicht.'], 400);
}

$dbSeatMap = [];
foreach ($seatRows as $r) {
    $dbSeatMap[(int)$r['seat_number']] = $r;
}

// Load admin settings for disabled override
$disabledPlaces = [];
$settings = $db->query("SELECT `key`, `value` FROM settings")->fetchAll();
foreach ($settings as $s) {
    if ($s['key'] === 'disabled_plaetze') {
        foreach (explode(',', $s['value']) as $part) {
            $part = trim($part);
            if (str_contains($part, '-')) {
                [$a, $b] = array_map('intval', explode('-', $part));
                $disabledPlaces = array_merge($disabledPlaces, range($a, $b));
            } elseif (is_numeric($part)) {
                $disabledPlaces[] = (int)$part;
            }
        }
    }
}
$disabledSet = array_flip($disabledPlaces);

// Check pending/reserved for same seats (within transaction)
$pendingSeats = $db->prepare("
    SELECT seats_json FROM reservations
    WHERE status = 'pending' AND expires_at > NOW()
    FOR UPDATE
");
$pendingSeats->execute();
$pendingNumbers = [];
while ($row = $pendingSeats->fetch()) {
    $nums = json_decode($row['seats_json'], true) ?: [];
    $pendingNumbers = array_merge($pendingNumbers, $nums);
}
$pendingSet = array_flip($pendingNumbers);

$reservedSeats = $db->prepare("
    SELECT seats_json FROM reservations
    WHERE status = 'confirmed'
    FOR UPDATE
");
$reservedSeats->execute();
$reservedNumbers = [];
while ($row = $reservedSeats->fetch()) {
    $nums = json_decode($row['seats_json'], true) ?: [];
    $reservedNumbers = array_merge($reservedNumbers, $nums);
}
$reservedSet = array_flip($reservedNumbers);

$unavailable = [];
foreach ($seatNumbers as $num) {
    $s = $dbSeatMap[$num] ?? null;
    if (!$s) { $unavailable[] = $num; continue; }
    if (isset($disabledSet[$num]) || $s['status'] === 'disabled') {
        $unavailable[] = $num;
    } elseif ($s['is_bodan']) {
        $unavailable[] = $num;
    } elseif (isset($pendingSet[$num])) {
        $unavailable[] = $num;
    } elseif (isset($reservedSet[$num]) || $s['status'] === 'reserved') {
        $unavailable[] = $num;
    }
}

if (count($unavailable) > 0) {
    $db->rollBack();
    jsonResponse([
        'error' => 'Folgende Plätze sind nicht mehr verfügbar: ' . implode(', ', $unavailable) .
            '. Bitte aktualisiere den Sitzplan und wähle erneut.'
    ], 409);
}

// --- Calculate price ---
$total = 0;
foreach ($seatNumbers as $num) {
    $s = $dbSeatMap[$num];
    $price = $isStudent ? (float)getSetting('price_student', DEFAULT_PRICE_STUDENT) :
        ($s['category'] === '1' ? (float)getSetting('price_kat1', DEFAULT_PRICE_KAT1) : (float)getSetting('price_kat2', DEFAULT_PRICE_KAT2));
    $total += $price;
}
if ($delivery === 'mail') {
    $total += DELIVERY_SURCHARGE;
}

// --- Create reservation ---
$token = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', time() + RESERVATION_EXPIRY_HOURS * 3600);

$stmt = $db->prepare("
    INSERT INTO reservations (customer_name, email, phone, address, notes, seats_json, total_amount, discount_type, delivery_option, token, ip_address, expires_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([
    $name,
    $email,
    $phone,
    $address,
    $notes,
    json_encode($seatNumbers),
    $total,
    $isStudent ? 'student' : 'none',
    $delivery,
    $token,
    $ip,
    $expiresAt,
]);

$db->commit();

// --- Send confirmation email ---
$confirmLink = SITE_URL . '/api/confirm.php?token=' . urlencode($token);

$concertDate = getSetting('concert_date', 'Sonntag, 27. September 2026, 17:00 Uhr');
$concertLocation = getSetting('concert_location', 'Kirche St. Stefan, Kreuzlingen-Emmishofen');
$concertHtml = '<p style="font-size:0.9rem;color:#888;">' . htmlspecialchars($concertDate) . '<br>' . htmlspecialchars($concertLocation) . '</p>';

$seatList = implode(', ', $seatNumbers);
$subject = 'Eingang Ihrer Ticket-Reservierung – bitte bestätigen';
$body = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
    body { font-family: -apple-system, sans-serif; color: #333; line-height: 1.6; }
    .btn { display:inline-block; padding:12px 24px; background:#c8a96e; color:#fff !important;
           text-decoration:none; border-radius:6px; font-weight:600; margin:16px 0; }
    .footer { margin-top:24px; font-size:0.85rem; color:#888; }
</style></head><body>
    <h2>Bitte bestätigen Sie Ihre Ticket-Reservierung</h2>
    ' . $concertHtml . '
    <p>Sehr geehrte/r ' . htmlspecialchars($name) . ',</p>
    <p>Sie haben folgende Plätze reserviert:</p>
    <p><strong>' . htmlspecialchars($seatList) . '</strong></p>
    <p>Gesamtbetrag: <strong>CHF ' . number_format($total, 2) . '</strong></p>
    <p style="font-size:0.9rem;color:#888;">' . ($isStudent ? '(Studentenpreis)' : '(Normalpreis)') . '
    ' . ($delivery === 'mail' ? '<br>+ Fr. 5.-- Zustellung per Post' : '') . '</p>
    <p><strong>Billettbezug:</strong> ' . ($delivery === 'mail' ? 'Zustellung per Post' . ($address ? ' an: ' . htmlspecialchars($address) : '') : 'Abholung an der Kasse') . '</p>
    <p>Bitte klicken Sie auf den folgenden Link, um Ihre Reservierung zu bestätigen:</p>
    <p><a class="btn" href="' . htmlspecialchars($confirmLink) . '">Reservierung bestätigen</a></p>
    <p>Der Link ist <strong>' . RESERVATION_EXPIRY_HOURS . ' Stunden</strong> gültig.</p>
    <p>Falls der Button nicht funktioniert, kopieren Sie diesen Link in Ihren Browser:<br>
    <span style="font-size:0.8rem;">' . htmlspecialchars($confirmLink) . '</span></p>
    <div class="footer">
        <p>Oratorienchor Kreuzlingen<br>
        Nach der Bestätigung erhalten Sie die Zahlungsinformationen per separater E-Mail.</p>
    </div>
</body></html>';

sendEmail($email, $subject, $body);

// Notify sales
$salesSubject = 'Neue Ticket-Reservierung von ' . htmlspecialchars($name);
$deliveryLabel = $delivery === 'mail' ? 'Zustellung per Post' . ($address ? ' an: ' . htmlspecialchars($address) : '') : 'Abholung an der Kasse';
$salesBody = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
    body { font-family: -apple-system, sans-serif; color: #333; line-height: 1.6; }
    .footer { margin-top:24px; font-size:0.85rem; color:#888; }
</style></head><body>
    <h2>Neue Ticket-Reservierung</h2>
    <p style="font-size:0.9rem;color:#888;">' . htmlspecialchars($concertDate) . '<br>' . htmlspecialchars($concertLocation) . '</p>
    <table border="0" cellpadding="6" style="margin:12px 0;">
        <tr><td><strong>Name:</strong></td><td>' . htmlspecialchars($name) . '</td></tr>
        <tr><td><strong>E-Mail:</strong></td><td>' . htmlspecialchars($email) . '</td></tr>
        <tr><td><strong>Telefon:</strong></td><td>' . htmlspecialchars($phone) . '</td></tr>
        <tr><td><strong>Plätze:</strong></td><td>' . htmlspecialchars($seatList) . '</td></tr>
        <tr><td><strong>Betrag:</strong></td><td>CHF ' . number_format($total, 2) . ($isStudent ? ' (Studentenpreis)' : '') . '</td></tr>
        <tr><td><strong>Billettbezug:</strong></td><td>' . $deliveryLabel . '</td></tr>
        <tr><td><strong>Bemerkungen:</strong></td><td>' . htmlspecialchars($notes ?: '–') . '</td></tr>
    </table>
    <p><a href="' . SITE_URL . '/admin/index.php">Zum Admin-Bereich</a></p>
    <div class="footer"><p>Oratorienchor Kreuzlingen</p></div>
</body></html>';
sendEmail(SALES_EMAIL, $salesSubject, $salesBody);

jsonResponse([
    'success' => true,
    'message' => 'Reservierung eingegangen. Bitte prüfe dein E-Mail-Postfach.',
]);
