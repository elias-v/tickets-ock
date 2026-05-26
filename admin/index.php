<?php
require_once __DIR__ . '/../config.php';

session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$db = getDb();
$message = '';
$error = '';

// Auto-migrate missing columns
foreach ([
    "ALTER TABLE reservations ADD COLUMN delivery_option ENUM('pickup','mail') NOT NULL DEFAULT 'pickup' AFTER discount_type",
    "ALTER TABLE reservations ADD COLUMN notes TEXT NOT NULL DEFAULT '' AFTER address",
    "ALTER TABLE seats ADD COLUMN is_bodan TINYINT(1) NOT NULL DEFAULT 0 AFTER status",
    "UPDATE seats SET is_bodan = 1 WHERE status = 'bodan'",
    "UPDATE seats SET status = 'available' WHERE status = 'bodan'",
] as $sql) {
    try { $db->exec($sql); } catch (Exception $e) {}
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $msg = '';
    $err = '';

    if ($action === 'update_settings') {
        $group = $_POST['group'] ?? 'all';
        $stmt = $db->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");

        if ($group === 'booking') {
            $bookingEnabled = !empty($_POST['booking_enabled']) ? '1' : '0';
            $stmt->execute(['booking_enabled', $bookingEnabled]);
        } else {
            $priceKat1 = (float)($_POST['price_kat1'] ?? $settings['price_kat1'] ?? DEFAULT_PRICE_KAT1);
            $priceKat2 = (float)($_POST['price_kat2'] ?? $settings['price_kat2'] ?? DEFAULT_PRICE_KAT2);
            $priceStudent = (float)($_POST['price_student'] ?? $settings['price_student'] ?? DEFAULT_PRICE_STUDENT);
            $concertDate = trim($_POST['concert_date'] ?? $settings['concert_date'] ?? '');
            $concertLocation = trim($_POST['concert_location'] ?? $settings['concert_location'] ?? '');
            $stmt->execute(['price_kat1', (string)$priceKat1]);
            $stmt->execute(['price_kat2', (string)$priceKat2]);
            $stmt->execute(['price_student', (string)$priceStudent]);
            $stmt->execute(['concert_date', $concertDate]);
            $stmt->execute(['concert_location', $concertLocation]);
        }

        $msg = 'Einstellungen gespeichert.';
    }

    if ($action === 'clear_all') {
        if (empty($_POST['confirmed'])) {
            $err = 'Löschvorgang nicht bestätigt.';
        } else {
            $db->exec("DELETE FROM reservations");
            $msg = 'Alle Reservierungen wurden gelöscht.';
        }
    }

    if ($action === 'delete_reservation') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("DELETE FROM reservations WHERE id = ?")->execute([$id]);
        $msg = 'Reservierung gelöscht.';
    }

    if ($action === 'change_password') {
        $newPass = $_POST['new_password'] ?? '';
        if (strlen($newPass) < 6) {
            $err = 'Passwort muss mindestens 6 Zeichen lang sein.';
        } else {
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $db->prepare("UPDATE admin_users SET password_hash = ? WHERE id = ?")
                ->execute([$hash, $_SESSION['admin_id']]);
            $msg = 'Passwort geändert.';
        }
    }

    // PRG: redirect to avoid form resubmission
    $params = [];
    if ($msg) $params['msg'] = $msg;
    if ($err) $params['err'] = $err;
    $query = $params ? '?' . http_build_query($params) : '';
    header('Location: index.php' . $query);
    exit;
}

// Read message from query string
$message = $_GET['msg'] ?? '';
$error = $_GET['err'] ?? '';

// Format price for display: whole numbers without decimals, .50 with two decimals
function formatPrice($val) {
    $v = (float)$val;
    return $v == (int)$v ? (string)(int)$v : number_format($v, 2);
}

// Load current settings
$settings = [];
$rows = $db->query("SELECT `key`, `value` FROM settings")->fetchAll();
foreach ($rows as $row) {
    $settings[$row['key']] = $row['value'];
}

// Load reservations
$reservations = $db->query("
    SELECT id, customer_name, email, phone, address, notes, seats_json, total_amount, discount_type, delivery_option, status, created_at, expires_at, confirmed_at
    FROM reservations ORDER BY created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin – Oratorienchor Kreuzlingen</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/style.css">
    <script src="../assets/seat-grid.js"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', -apple-system, sans-serif; background: #f5f3ef; color: #1a1a2e; }
        .container { max-width: 1320px; margin: 0 auto; padding: 20px; }
        header { background: #2c3e50; color: #fff; padding: 16px 0; margin-bottom: 24px; }
        header .container { display: flex; justify-content: space-between; align-items: center; }
        header h1 { font-size: 1.25rem; font-weight: 600; }
        header a { color: #c8a96e; text-decoration: none; font-size: 0.9rem; }
        header a:hover { text-decoration: underline; }
        .card { background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); padding: 24px; margin-bottom: 20px; }
        .card h2 { font-size: 1.05rem; margin-bottom: 16px; color: #2c3e50; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 700px) { .grid-2 { grid-template-columns: 1fr; } }
        .form-group { margin-bottom: 14px; }
        .form-group label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 4px; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 10px 12px; border: 1px solid #e0dcd4; border-radius: 6px; font-size: 0.9rem; font-family: inherit; }
        .form-group input:focus, .form-group textarea:focus { outline: none; border-color: #c8a96e; }
        .form-group textarea { min-height: 80px; font-family: monospace; }
        .form-hint { font-size: 0.75rem; color: #888; margin-top: 2px; }
        .toggle-wrap { display: flex; align-items: center; gap: 12px; }
        .toggle-wrap input[type="checkbox"] { width: 20px; height: 20px; accent-color: #c8a96e; }
        .btn { padding: 10px 20px; background: #c8a96e; color: #fff; border: none; border-radius: 6px; font-size: 0.9rem; font-weight: 600; cursor: pointer; font-family: inherit; }
        .btn:hover { background: #b8974d; }
        .btn-danger { background: #e74c3c; }
        .btn-danger:hover { background: #c0392b; }
        .btn-sm { padding: 6px 12px; font-size: 0.8rem; }
        .message { padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 0.9rem; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #e0dcd4; }
        th { font-weight: 600; color: #555; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.03em; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 0.75rem; font-weight: 600; }
        .badge.pending { background: #f5d76e; color: #8a7000; }
        .badge.confirmed { background: #d4edda; color: #155724; }
        .badge.expired { background: #e5e5e5; color: #888; }
        .confirm-input { margin-top: 8px; }
        .confirm-input input { padding: 8px 12px; border: 1px solid #e74c3c; border-radius: 4px; font-family: monospace; width: 140px; }
        .pw-wrap { position: relative; }
        .pw-wrap input { padding-right: 40px; }
        .pw-toggle { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 18px; line-height: 1; color: #888; padding: 4px; }
        .pw-toggle:hover { color: #555; }
        .mt-2 { margin-top: 8px; }
        .mb-2 { margin-bottom: 8px; }

        /* Radio buttons for seat mode */
        .admin-mode-bar { display: flex; gap: 4px; background: #f5f3ef; border-radius: 8px; padding: 4px; margin-bottom: 16px; }
        .admin-mode-bar label { flex: 1; text-align: center; padding: 8px 12px; border-radius: 6px; font-size: 0.82rem; font-weight: 500; cursor: pointer; transition: all 0.15s; }
        .admin-mode-bar input[type="radio"] { display: none; }
        .admin-mode-bar input[type="radio"]:checked + label { background: #f5d76e; box-shadow: 0 1px 4px rgba(0,0,0,0.1); font-weight: 700; }

        .admin-hint { font-size: 0.8rem; color: #888; margin-bottom: 12px; }

        /* Mode-specific hovers */
        #admin-grid.mode-toggle .seat-cell.available:hover {
            background: var(--reserved);
            color: #999;
            border-color: var(--reserved-border);
            transform: none;
        }
        #admin-grid.mode-toggle .seat-cell.disabled.kat1:hover {
            background: var(--kat1);
            color: var(--kat1-text);
            border-color: var(--kat1-border);
            transform: scale(1.15);
            z-index: 2;
        }
        #admin-grid.mode-toggle .seat-cell.disabled.kat2:hover {
            background: var(--kat2);
            color: var(--kat2-text);
            border-color: var(--kat2-border);
            transform: scale(1.15);
            z-index: 2;
        }

        /* Bodan mode: show white on hover */
        #admin-grid.mode-bodan .seat-cell:hover {
            background: #fff !important;
            color: #1a1a2e !important;
            border-color: var(--text-muted) !important;
            transform: none !important;
        }

        /* Unreserve mode: reserved seats hover with category color, others no hover */
        #admin-grid.mode-unreserve .seat-cell.available:hover,
        #admin-grid.mode-unreserve .seat-cell.disabled:hover {
            background: transparent !important;
            color: inherit !important;
            border-color: transparent !important;
            transform: none !important;
        }
        #admin-grid.mode-unreserve .seat-cell.reserved.kat1:hover,
        #admin-grid.mode-unreserve .seat-cell.pending.kat1:hover {
            background: var(--kat1);
            color: var(--kat1-text);
            border-color: var(--kat1-border);
        }
        #admin-grid.mode-unreserve .seat-cell.reserved.kat2:hover,
        #admin-grid.mode-unreserve .seat-cell.pending.kat2:hover {
            background: var(--kat2);
            color: var(--kat2-text);
            border-color: var(--kat2-border);
        }

        .message { position: relative; }
        .msg-close {
            position: absolute;
            top: 50%;
            right: 12px;
            transform: translateY(-50%);
            background: none;
            border: none;
            font-size: 1.3rem;
            cursor: pointer;
            color: inherit;
            opacity: 0.6;
            line-height: 1;
            padding: 0;
        }
        .msg-close:hover { opacity: 1; }

        .price-spinner { display: flex; align-items: stretch; }
        .price-spinner input { flex: 1; min-width: 0; border-radius: 4px 0 0 4px; }
        .price-spinner .spin-group { display: flex; flex-direction: column; }
        .price-spinner .spin-btn {
            display: flex; align-items: center; justify-content: center;
            width: 28px; flex: 1; border: 1px solid #e0dcd4; background: #f5f3ef;
            cursor: pointer; font-size: 0.6rem; line-height: 1; color: #555;
            padding: 0; margin: 0; margin-left: -1px;
        }
        .price-spinner .spin-btn:hover { background: #e8e4dc; }
        .price-spinner .spin-btn:first-child { border-radius: 0 4px 0 0; }
        .price-spinner .spin-btn:last-child { border-radius: 0 0 4px 0; }

        .info-btn {
            background: none; border: 1px solid var(--border); border-radius: 50%;
            width: 26px; height: 26px; cursor: pointer; font-size: 0.85rem;
            display: flex; align-items: center; justify-content: center;
            color: var(--text-muted); padding: 0; line-height: 1;
        }
        .info-btn:hover { background: var(--accent); color: #fff; border-color: var(--accent); }

        .detail-popup {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.4); z-index: 1000; align-items: center; justify-content: center;
        }
        .detail-popup.show { display: flex; }
        .detail-popup .popup-content {
            background: #fff; border-radius: 8px; padding: 24px 28px; max-width: 460px;
            width: 90%; box-shadow: 0 8px 30px rgba(0,0,0,0.15);
            font-size: 0.85rem; line-height: 1.6; white-space: pre-wrap;
            position: relative;
        }
        .detail-popup .popup-close {
            position: absolute; top: 10px; right: 14px; background: none; border: none;
            font-size: 1.3rem; cursor: pointer; color: #999; padding: 0; line-height: 1;
        }
        .detail-popup .popup-close:hover { color: #333; }

        .confirm-popup {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.4); z-index: 1100; align-items: center; justify-content: center;
        }
        .confirm-popup.show { display: flex; }
        .confirm-popup .popup-content {
            background: #fff; border-radius: 8px; padding: 24px 28px; max-width: 420px;
            width: 90%; box-shadow: 0 8px 30px rgba(0,0,0,0.15);
            font-size: 0.85rem; line-height: 1.6;
        }
        .confirm-popup .popup-content p { margin: 8px 0; }
        .confirm-popup .popup-buttons { display: flex; gap: 8px; margin-top: 16px; flex-wrap: wrap; }

        @media (max-width: 700px) {
            header .container {
                flex-direction: column;
                gap: 8px;
                text-align: center;
            }
            header h1 {
                font-size: 1.05rem;
            }
            .container {
                padding: 12px;
            }
            .card {
                padding: 16px;
                margin-bottom: 14px;
            }
            .card h2 {
                font-size: 0.95rem;
                margin-bottom: 12px;
            }
            .admin-mode-bar label {
                font-size: 0.75rem;
                padding: 6px 8px;
            }
            th, td {
                padding: 6px 6px;
                font-size: 0.78rem;
            }
            .btn {
                padding: 8px 14px;
                font-size: 0.82rem;
            }
            .btn-sm {
                padding: 5px 8px;
                font-size: 0.72rem;
            }
            .form-group input, .form-group textarea, .form-group select {
                font-size: 16px;
            }
            .confirm-popup .popup-content {
                padding: 18px 16px;
                margin: 0 10px;
            }
        }
    </style>
</head>
<body>

<header>
    <div class="container">
        <h1>Admin-Bereich – Oratorienchor Kreuzlingen</h1>
        <div>
            <a href="<?= SITE_URL ?>">&#8592; Zurück zur Ticket-Seite</a>
            <span style="color:rgba(255,255,255,0.5);margin:0 10px;">|</span>
            <span style="color:rgba(255,255,255,0.7);font-size:0.85rem;"><?= htmlspecialchars($_SESSION['admin_user']) ?></span>
            <a href="?logout=1" style="margin-left:10px;font-size:0.85rem;">Anmelden</a>
        </div>
    </div>
</header>

<?php
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}
?>

<div class="container">
    <?php if ($message): ?>
        <div class="message success">
            <?= htmlspecialchars($message) ?>
            <button class="msg-close" onclick="this.parentElement.remove()">&times;</button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="message error">
            <?= htmlspecialchars($error) ?>
            <button class="msg-close" onclick="this.parentElement.remove()">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Seat Plan at top -->
    <div class="card">
        <h2>Sitzplan – Platzstatus verwalten</h2>
        <div class="admin-mode-bar">
            <input type="radio" name="admin-mode" id="mode-toggle" value="toggle" checked>
            <label for="mode-toggle">Plätze de-/aktivieren</label>
            <input type="radio" name="admin-mode" id="mode-bodan" value="bodan">
            <label for="mode-bodan">Bodanplätze markieren</label>
            <input type="radio" name="admin-mode" id="mode-unreserve" value="unreserve">
            <label for="mode-unreserve">Reservierung entfernen</label>
        </div>
        <p class="admin-hint" id="admin-hint">Klicke auf einen verfügbaren Platz, um ihn zu deaktivieren.</p>
    <div class="seat-grid-wrapper">
        <div id="admin-grid"><p style="color:#888;">Sitzplan wird geladen…</p></div>
    </div>
</div>

<div class="grid-2">
        <!-- Left: Concert info + Prices -->
        <div class="card">
            <h2>Einstellungen</h2>
            <form method="post">
                <input type="hidden" name="action" value="update_settings">
                <input type="hidden" name="group" value="prices">

                <div class="form-group">
                    <label for="concert_date">Konzert-Datum und -Uhrzeit</label>
                    <input type="text" id="concert_date" name="concert_date"
                        value="<?= htmlspecialchars($settings['concert_date'] ?? 'Sonntag, 27. September 2026, 17:00 Uhr') ?>">
                </div>

                <div class="form-group">
                    <label for="concert_location">Konzert-Ort</label>
                    <input type="text" id="concert_location" name="concert_location"
                        value="<?= htmlspecialchars($settings['concert_location'] ?? 'Kirche St. Stefan, Kreuzlingen-Emmishofen') ?>">
                </div>

                <hr style="border:none;border-top:1px solid #e0dcd4;margin:16px 0;">

                <div class="form-group">
                    <label for="price_kat1">Preis Kat. I (CHF)</label>
                    <div class="price-spinner">
                        <input type="text" id="price_kat1" name="price_kat1" inputmode="decimal"
                            value="<?= formatPrice($settings['price_kat1'] ?? DEFAULT_PRICE_KAT1) ?>">
                        <div class="spin-group">
                            <button type="button" class="spin-btn" onclick="stepPrice(this,0.5)" title="Erhöhen">&#9650;</button>
                            <button type="button" class="spin-btn" onclick="stepPrice(this,-0.5)" title="Verringern">&#9660;</button>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="price_kat2">Preis Kat. II (CHF)</label>
                    <div class="price-spinner">
                        <input type="text" id="price_kat2" name="price_kat2" inputmode="decimal"
                            value="<?= formatPrice($settings['price_kat2'] ?? DEFAULT_PRICE_KAT2) ?>">
                        <div class="spin-group">
                            <button type="button" class="spin-btn" onclick="stepPrice(this,0.5)" title="Erhöhen">&#9650;</button>
                            <button type="button" class="spin-btn" onclick="stepPrice(this,-0.5)" title="Verringern">&#9660;</button>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="price_student">Preis Schüler/Studenten (CHF)</label>
                    <div class="price-spinner">
                        <input type="text" id="price_student" name="price_student" inputmode="decimal"
                            value="<?= formatPrice($settings['price_student'] ?? DEFAULT_PRICE_STUDENT) ?>">
                        <div class="spin-group">
                            <button type="button" class="spin-btn" onclick="stepPrice(this,0.5)" title="Erhöhen">&#9650;</button>
                            <button type="button" class="spin-btn" onclick="stepPrice(this,-0.5)" title="Verringern">&#9660;</button>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn">Einstellungen speichern</button>
            </form>
        </div>

        <!-- Right column -->
        <div>
            <!-- Booking config -->
            <div class="card">
                <h2>Buchungskonfiguration</h2>
                <form method="post">
                    <input type="hidden" name="action" value="update_settings">
                    <input type="hidden" name="group" value="booking">

                    <div class="form-group toggle-wrap">
                        <input type="checkbox" id="booking_enabled" name="booking_enabled" value="1"
                            <?= ($settings['booking_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                        <label for="booking_enabled">Ticket-Reservierung aktiviert</label>
                    </div>

                    <p style="font-size:0.78rem;color:#888;margin:4px 0 12px 0;">Bodan- und deaktivierte Plätze werden im Sitzplan oben verwaltet.</p>

                    <button type="submit" class="btn">Speichern</button>
                </form>
            </div>

            <!-- Password change -->
            <div class="card">
                <h2>Admin-Passwort ändern</h2>
                <form method="post">
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-group">
                        <label for="new_password">Neues Passwort</label>
                        <div class="pw-wrap">
                            <input type="password" id="new_password" name="new_password" required minlength="6"
                                autocomplete="new-password">
                            <button type="button" class="pw-toggle" onclick="togglePw('new_password', this)" tabindex="-1">&#128065;</button>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-sm">Passwort ändern</button>
                </form>
            </div>
        </div>
    </div>

<!-- Danger Zone -->
<div class="card" style="border-left:3px solid #e74c3c;">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <div>
            <h2 style="color:#e74c3c;margin:0;">Gefahrenzone</h2>
            <p style="font-size:0.8rem;color:#888;margin:4px 0 0 0;">Alle Reservierungen inkl. aller Kundendaten unwiderruflich löschen</p>
        </div>
        <button type="button" class="btn btn-danger" onclick="confirmDeleteAll()">Alle Reservierungen löschen</button>
    </div>
</div>

<!-- Reservations list -->
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h2 style="margin:0;">Reservierungen (<?= count($reservations) ?>)</h2>
            <?php if (count($reservations) > 0): ?>
                <a href="export-csv.php" class="btn btn-sm">Alle speichern</a>
            <?php endif; ?>
        </div>
        <?php if (count($reservations) === 0): ?>
            <p style="color:#888;">Keine Reservierungen vorhanden.</p>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Datum</th>
                            <th>Name</th>
                            <th>E-Mail</th>
                            <th>Plätze</th>
                            <th>Betrag</th>
                            <th>Bemerkungen</th>
                            <th>Status</th>
                            <th></th>
                            <th>Aktion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reservations as $r):
                            $seats = implode(', ', json_decode($r['seats_json'], true) ?: []);
                            $detail = sprintf(
                                "Name: %s\nE-Mail: %s\nTelefon: %s\nAdresse: %s\nLieferung: %s\nPlätze: %s\nBetrag: CHF %s\nErmässigung: %s\nBemerkungen: %s\nStatus: %s\nErstellt: %s\nBestätigt: %s",
                                $r['customer_name'],
                                $r['email'],
                                $r['phone'],
                                $r['address'],
                                $r['delivery_option'] === 'mail' ? 'Zustellung' : 'Abholung',
                                $seats,
                                number_format((float)$r['total_amount'], 2),
                                $r['discount_type'] === 'student' ? 'Schüler/Student' : 'Keine',
                                $r['notes'] ?: '—',
                                $r['status'],
                                $r['created_at'],
                                $r['confirmed_at'] ?? '—'
                            );
                        ?>
                        <tr>
                            <td style="white-space:nowrap;"><?= date('d.m. H:i', strtotime($r['created_at'])) ?></td>
                            <td><?= htmlspecialchars($r['customer_name']) ?></td>
                            <td><a href="mailto:<?= htmlspecialchars($r['email']) ?>"><?= htmlspecialchars($r['email']) ?></a></td>
                            <td><?= htmlspecialchars($seats) ?></td>
                            <td>CHF <?= number_format((float)$r['total_amount'], 2) ?>
                                <?php if ($r['discount_type'] === 'student'): ?>
                                    <span style="font-size:0.75rem;color:#888;">(S)</span>
                                <?php endif; ?>
                            </td>
                            <td style="max-width:140px;font-size:0.78rem;color:#666;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($r['notes']) ?></td>
                            <td>
                                <span class="badge <?= $r['status'] ?>">
                                    <?= $r['status'] === 'pending' ? 'Ausstehend' : ($r['status'] === 'confirmed' ? 'Bestätigt' : 'Abgelaufen') ?>
                                </span>
                            </td>
                            <td>
                                <button type="button" class="info-btn" title="Details anzeigen"
                                    onclick="showReservationDetail(this, <?= htmlspecialchars(json_encode($detail)) ?>)">&#8505;</button>
                            </td>
                            <td style="white-space:nowrap;">
                                <a href="export-csv.php?id=<?= $r['id'] ?>" class="btn btn-sm" style="text-decoration:none;">Speichern</a>
                                <form method="post" onsubmit="return confirm('Reservierung löschen?');" style="display:inline;">
                                    <input type="hidden" name="action" value="delete_reservation">
                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Löschen</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="detail-popup" id="detail-popup">
    <div class="popup-content">
        <button class="popup-close" onclick="document.getElementById('detail-popup').classList.remove('show')">&times;</button>
        <div id="detail-popup-text"></div>
    </div>
</div>

<div class="confirm-popup" id="confirm-popup">
    <div class="popup-content">
        <div id="confirm-popup-text"></div>
        <div class="popup-buttons" id="confirm-popup-buttons"></div>
    </div>
</div>

<script>
function togglePw(id, btn) {
    const input = document.getElementById(id);
    if (input.type === 'password') {
        input.type = 'text';
        btn.textContent = '\u{1F441}';
    } else {
        input.type = 'password';
        btn.textContent = '\u{1F441}';
    }
}

// Admin seat plan
let adminSeats = [];

async function loadAdminSeats() {
    try {
        const res = await fetch('../api/get-seats.php');
        const data = await res.json();
        adminSeats = data.seats;
        renderAdminSeats();
    } catch (e) {
        document.getElementById('admin-grid').innerHTML = '<p style="color:#e74c3c;">Fehler beim Laden des Sitzplans.</p>';
    }
}

function getAdminMode() {
    return document.querySelector('input[name="admin-mode"]:checked')?.value || 'toggle';
}

function updateHint() {
    const hints = {
        toggle: 'Klicke auf einen verfügbaren Platz, um ihn zu deaktivieren, oder auf einen deaktivierten, um ihn zu aktivieren.',
        bodan: 'Klicke auf einen Platz, um ihn als Bodanplatz zu markieren oder die Markierung zu entfernen.',
        unreserve: 'Klicke auf einen reservierten Platz, um die Reservierung aufzuheben.',
    };
    document.getElementById('admin-hint').textContent = hints[getAdminMode()] || '';
}

function setAdminGridMode() {
    document.getElementById('admin-grid').className = 'mode-' + getAdminMode();
}

document.querySelectorAll('input[name="admin-mode"]').forEach(r => {
    r.addEventListener('change', () => { updateHint(); renderAdminSeats(); setAdminGridMode(); });
});

function renderAdminSeats() {
    renderGrid(document.getElementById('admin-grid'), adminSeats, createAdminCell);
}

function createAdminCell(seat) {
    const el = document.createElement('div');
    let cls = 'seat-cell';
    cls += seat.cat === '1' ? ' kat1' : ' kat2';
    if (seat.status === 'disabled') cls += ' disabled';
    else if (seat.status === 'reserved') cls += ' reserved';
    else if (seat.status === 'pending') cls += ' pending';
    else cls += ' available';
    if (seat.is_bodan) cls += ' bodan-mark';
    el.className = cls;
    el.textContent = String(seat.number).padStart(2, '0');
    if (seat.status === 'disabled') {
        const tip = document.createElement('div');
        tip.className = 'tooltip-text';
        tip.textContent = 'nicht verfügbar';
        el.appendChild(tip);
    }
    el.addEventListener('click', () => handleSeatClick(seat));
    return el;
}

function confirmDialog(html, buttons) {
    const popup = document.getElementById('confirm-popup');
    document.getElementById('confirm-popup-text').innerHTML = html;
    const btnContainer = document.getElementById('confirm-popup-buttons');
    btnContainer.innerHTML = '';
    buttons.forEach(b => {
        const btn = document.createElement('button');
        btn.className = 'btn ' + (b.danger ? 'btn-danger ' : '') + (b.sm ? 'btn-sm ' : '');
        btn.textContent = b.label;
        btn.onclick = () => { popup.classList.remove('show'); b.action(); };
        btnContainer.appendChild(btn);
    });
    popup.classList.add('show');
}

function confirmDeleteAll() {
    confirmDialog(
        '<p><strong>Alle vorhandenen Reservierungsdaten werden unwiderruflich gelöscht.</strong></p>' +
        '<p>Sollen die Informationen zusätzlich gespeichert werden?</p>',
        [
            { label: 'Speichern & löschen', action: () => {
                window.open('export-csv.php', '_blank');
                submitDeleteAll();
            }},
            { label: 'Nur löschen', action: () => submitDeleteAll() },
            { label: 'Abbrechen', action: () => {} },
        ]
    );
}

function submitDeleteAll() {
    const form = document.createElement('form');
    form.method = 'post';
    form.innerHTML = '<input type="hidden" name="action" value="clear_all"><input type="hidden" name="confirmed" value="1">';
    document.body.appendChild(form);
    form.submit();
}

async function handleSeatClick(seat) {
    const mode = getAdminMode();
    let targetStatus, targetBodan;

    if (mode === 'toggle') {
        if (seat.status === 'available') {
            targetStatus = 'disabled';
        } else if (seat.status === 'disabled') {
            targetStatus = 'available';
        } else {
            return;
        }
        targetBodan = seat.is_bodan ? 1 : 0;
    } else if (mode === 'bodan') {
        targetStatus = seat.status;
        targetBodan = seat.is_bodan ? 0 : 1;
    } else if (mode === 'unreserve') {
        if (seat.status !== 'reserved' && seat.status !== 'pending') return;

        // Fetch reservation info
        let resInfo;
        try {
            const r = await fetch('../api/reservation-by-seat.php?seat=' + seat.number);
            const d = await r.json();
            if (d.error) { alert(d.error); return; }
            resInfo = d.reservation;
        } catch (e) {
            alert('Fehler beim Laden der Reservierungsdaten.');
            return;
        }

        const seats = (JSON.parse(resInfo.seats_json) || []).join(', ');
        const infoHtml = '<strong>Reservierung entfernen?</strong><br>' +
            'Name: ' + escapeHtml(resInfo.customer_name) + '<br>' +
            'E-Mail: ' + escapeHtml(resInfo.email) + '<br>' +
            'Plätze: ' + escapeHtml(seats) +
            (resInfo.notes ? '<br>Bemerkungen: ' + escapeHtml(resInfo.notes) : '');

        confirmDialog(infoHtml, [
            { label: 'Speichern & entfernen', action: () => {
                window.open('export-csv.php?id=' + resInfo.id, '_blank');
                doUnreserve(seat);
            }},
            { label: 'Nur entfernen', action: () => doUnreserve(seat) },
            { label: 'Abbrechen', action: () => {} },
        ]);
        return;
    } else {
        return;
    }

    try {
        const res = await fetch('../api/admin-update-seat.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({seat_number: seat.number, status: targetStatus, is_bodan: targetBodan}),
        });
        const data = await res.json();
        if (data.error) { alert(data.error); return; }
        await loadAdminSeats();
    } catch (e) {
        alert('Fehler beim Aktualisieren des Platzes.');
    }
}

async function doUnreserve(seat) {
    try {
        const res = await fetch('../api/admin-update-seat.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({seat_number: seat.number, status: 'available', is_bodan: seat.is_bodan ? 1 : 0}),
        });
        const data = await res.json();
        if (data.error) { alert(data.error); return; }
        await loadAdminSeats();
    } catch (e) {
        alert('Fehler beim Aktualisieren des Platzes.');
    }
}

function escapeHtml(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

updateHint();
setAdminGridMode();
loadAdminSeats();

// Remove msg/err from URL so refresh clears the message
if (location.search.includes('msg=') || location.search.includes('err=')) {
    history.replaceState(null, '', location.pathname);
}

function showReservationDetail(btn, text) {
    document.getElementById('detail-popup-text').textContent = text;
    document.getElementById('detail-popup').classList.add('show');
}

function stepPrice(btn, step) {
    const input = btn.closest('.price-spinner').querySelector('input');
    let v = parseFloat(input.value.replace(',', '.')) || 0;
    v = Math.round((v + step) * 100) / 100;
    v = Math.max(0, v);
    input.value = v === Math.floor(v) ? String(Math.floor(v)) : v.toFixed(2);
    input.dispatchEvent(new Event('change', { bubbles: true }));
}
</script>
</body>
</html>
