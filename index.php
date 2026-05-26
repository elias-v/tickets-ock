<?php
require_once __DIR__ . '/config.php';

$settings = [];
$rows = getDb()->query("SELECT `key`, `value` FROM settings")->fetchAll();
foreach ($rows as $row) {
    $settings[$row['key']] = $row['value'];
}

$priceKat1 = $settings['price_kat1'] ?? DEFAULT_PRICE_KAT1;
$priceKat2 = $settings['price_kat2'] ?? DEFAULT_PRICE_KAT2;
$priceStudent = $settings['price_student'] ?? DEFAULT_PRICE_STUDENT;
$concertDate = $settings['concert_date'] ?? 'Sonntag, 27. September 2026, 17:00 Uhr';
$concertLocation = $settings['concert_location'] ?? 'Kirche St. Stefan, Kreuzlingen-Emmishofen';
$bookingEnabled = ($settings['booking_enabled'] ?? '1') === '1';
?>
<!DOCTYPE html>
<html lang="de"<?= !$bookingEnabled ? ' style="overflow:hidden"' : '' ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket-Reservierung – Oratorienchor Kreuzlingen</title>
    <meta name="description" content="Online-Ticketreservierung für den Oratorienchor Kreuzlingen">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css?v=<?= time() ?>">
</head>
<body>

<div class="booking-disabled-overlay<?= !$bookingEnabled ? ' show' : '' ?>" id="booking-disabled-overlay">
    <div class="message">
        <h3>Ticketreservierung derzeit nicht möglich</h3>
        <p>Der Ticketverkauf ist momentan deaktiviert. Bitte versuchen Sie es einige Wochen vor dem Konzert erneut.</p>
        <p style="margin-top:12px;font-size:0.85rem;">
            <a href="https://oratorienchor-kreuzlingen.ch" target="_blank" style="color:var(--accent);">oratorienchor-kreuzlingen.ch</a>
        </p>
    </div>
</div>

<header class="header">
    <div class="container">
        <h1>Ticket-Reservierung</h1>
        <div class="subtitle">Oratorienchor Kreuzlingen</div>
                <div class="concert-info">
                    <span>&#128197; <?= htmlspecialchars($concertDate) ?></span>
                    <span>&#128205; <?= htmlspecialchars($concertLocation) ?></span>
                </div>
    </div>
</header>

<main class="container">
    <div class="main-layout">
        <div>
            <div class="legend">
                <div class="legend-item">
                    <span class="legend-swatch kat1">I</span>
                    <span>Kat. I (CHF <?= number_format($priceKat1, 0) ?>) – Reihen 3–15</span>
                </div>
                <div class="legend-item">
                    <span class="legend-swatch kat2">II</span>
                    <span>Kat. II (CHF <?= number_format($priceKat2, 0) ?>) – übrige Plätze</span>
                </div>
                <div class="legend-item">
                    <span class="legend-swatch selected-swatch">&check;</span>
                    <span>Ausgewählt</span>
                </div>
                <div class="legend-item">
                    <span class="legend-swatch pending">&bull;</span>
                    <span>Wird reserviert</span>
                </div>
                <div class="legend-item">
                    <span class="legend-swatch reserved">&times;</span>
                    <span>Vergeben</span>
                </div>
                <div class="legend-item">
                    <span class="legend-swatch bodan"></span>
                    <span>via Bodan Papeterie</span>
                    <button class="bodan-toggle" id="bodan-toggle" type="button" title="Info"><span class="info-icon">i</span></button>
                </div>
                <div class="bodan-info" id="bodan-info">
                    Billette erhältlich 4 Wochen vor Konzertbeginn bei<br>
                    <strong>Bodan Papeterie</strong>, Hauptstrasse 35, Kreuzlingen<br>
                    <a href="https://papeterie.bodan-ag.ch/standort" target="_blank" rel="noopener">papeterie.bodan-ag.ch/standort</a>
                </div>
            </div>

            <div class="seat-grid-wrapper">
                <div id="grid-container">
                    <div class="text-center text-muted" style="padding:40px;">
                        Sitzplan wird geladen…
                    </div>
                </div>
            </div>
        </div>

        <div class="cart-panel" id="cart-panel">
            <div class="cart-header">
                <span>Warenkorb</span>
                <span class="count" id="cart-count">0</span>
            </div>
            <div class="cart-body">
                <div class="cart-empty" id="cart-empty">
                    Klicke auf einen freien Platz im Sitzplan, um ihn auszuwählen.
                </div>
                <ul class="cart-items" id="cart-items"></ul>
                <div class="cart-total" id="cart-total">CHF 0.00</div>

                <div class="student-toggle">
                    <input type="checkbox" id="student-toggle">
                    <label for="student-toggle">Schüler/Studierende (<?= number_format($priceStudent, 0) ?> CHF)</label>
                </div>

                <form id="order-form" class="order-form" novalidate>
                    <div id="form-alert" class="alert"></div>

                    <div class="hp-field">
                        <input type="text" id="field-hp" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="delivery-group">
                        <label class="delivery-label">Gewünschter Billettbezug:</label>
                        <label class="delivery-option">
                            <input type="radio" name="delivery" value="pickup" checked>
                            <span>Abholung und Bezahlung bis 30 Min. vor Konzertbeginn an der Kasse</span>
                        </label>
                        <label class="delivery-option">
                            <input type="radio" name="delivery" value="mail">
                            <span>Zustellung nach Zahlungseingang per Post <strong>(+ Fr. <?= DELIVERY_SURCHARGE ?>.--)</strong></span>
                        </label>

                        <p style="font-size:0.75rem;color:var(--text-muted);margin-top:8px;">
                                <?= htmlspecialchars(BANK_INFO_TEXT) ?><br>
                                TKB PK <?= htmlspecialchars(BANK_PC) ?> / <?= htmlspecialchars(BANK_IBAN) ?>
                            </p>
                    </div>

                    <h3>Deine Angaben</h3>

                    <div class="form-group">
                        <label for="field-name">Name *</label>
                        <input type="text" id="field-name" name="name" required
                            placeholder="Vor- und Nachname">
                    </div>

                    <div id="shipping-fields" style="display:none;">
                        <div class="form-group">
                            <label for="field-street">Strasse Nr. *</label>
                            <input type="text" id="field-street" name="street"
                                placeholder="Strasse und Hausnummer">
                        </div>
                        <div class="form-group">
                            <label for="field-city">PLZ Ort *</label>
                            <input type="text" id="field-city" name="city"
                                placeholder="PLZ und Ort">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="field-email">E-Mail *</label>
                        <input type="email" id="field-email" name="email" required
                            placeholder="deine@email.ch">
                        <div class="hint">Du erhältst einen Bestätigungslink per E-Mail.</div>
                    </div>

                    <div class="form-group">
                        <label for="field-phone">Telefon</label>
                        <input type="tel" id="field-phone" name="phone"
                            placeholder="+41 79 123 45 67">
                    </div>

                    <div class="form-group">
                        <label for="field-notes">Fragen, Anliegen, Bemerkungen</label>
                        <textarea id="field-notes" name="notes" rows="3"
                            placeholder="…"></textarea>
                    </div>

                    <button type="submit" class="submit-btn" id="submit-btn" disabled>
                        Jetzt reservieren
                    </button>
                </form>
            </div>
        </div>
    </div>
</main>

<footer style="text-align:center; padding:16px; font-size:0.8rem; color:#888;">
    <a href="https://oratorienchor-kreuzlingen.ch/datenschutzerklaerung/" target="_blank" rel="noopener" style="color:#888; text-decoration:underline;">Datenschutzerklärung</a>
</footer>

<script src="assets/seat-grid.js?v=<?= time() ?>"></script>
<script src="assets/app.js?v=<?= time() ?>"></script>
</body>
</html>
