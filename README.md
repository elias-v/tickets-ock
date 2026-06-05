# OCK-Tickets – Ticket-Reservierung Oratorienchor Kreuzlingen

Ticket-Reservierungssystem für den Oratorienchor Kreuzlingen.
Besucher können online Plätze auswählen, reservieren und per E-Mail bestätigen.
Der Admin-Bereich erlaubt die Verwaltung des Sitzplans, der Reservierungen und Einstellungen.

## Voraussetzungen

- **PHP 8.0+** mit PDO MySQL, JSON, mbstring
- **MySQL / MariaDB**
- **Apache** mit mod_rewrite und mod_headers
- **Composer** (für PHPMailer)
- **Cron** (für automatische Bereinigung)
- **SMTP-Server** oder lokaler MTA für E-Mail-Versand

## Installation

### 1. Dateien hochladen

Das gesamte Projektverzeichnis in das Document-Root der Subdomain kopieren, z. B. `tickets.oratorienchor-kreuzlingen.ch`.

### 2. Composer-Abhängigkeiten installieren (lokal)

Auf dem Server (Shared Hosting) ist Composer in der Regel nicht verfügbar. Daher lokal ausführen und das `vendor/`-Verzeichnis hochladen:

```bash
cd /pfad/zum/projekt
composer install --no-dev
```

Installiert PHPMailer 7.1+ (`vendor/`-Verzeichnis muss auf dem Server vorhanden sein).

### 3. Datenbank einrichten

`db_schema.php` (oder `schema.sql`) einmalig ausführen:

```bash
php db_schema.php
```

Erzeugt die Datenbank `vabibese_tickets` mit allen Tabellen:
- **seats** – Sitzplätze (250+, Reihen 2–26, Kategorien 1/2)
- **reservations** – Reservierungen mit Token, Status, Kundendaten
- **settings** – Konfiguration (Preise, Buchung an/aus, Konzertdaten)
- **admin_users** – Admin-Login (Default: `admin` / `admin`)
- **rate_limits** – IP-basiertes Rate-Limiting

### 4. Konfiguration anpassen

Konfiguration via `.env`-Datei (git-ignoriert) oder Umgebungsvariablen. `config.php` wertet in dieser Reihenfolge aus: Env-Vars → `.env` → Hardcoded-Defaults.

```bash
# .env (im Projektverzeichnis)
DB_HOST=vabibese.mysql.db.internal
DB_NAME=vabibese_tickets
DB_USER=vabibese_reserv
DB_PASS=secret
SITE_URL=https://tickets.oratorienchor-kreuzlingen.ch
SALES_EMAIL=billettverkauf@oratorienchor-kreuzlingen.ch
EMAIL_FROM=webseite@oratorienchor-kreuzlingen.ch
SMTP_HOST=smtp.hostpoint.ch
SMTP_PORT=587
SMTP_USER=webseite@oratorienchor-kreuzlingen.ch
SMTP_PASS=secret
```

`DB_HOST` kann optional einen Port enthalten (`host:3306`) – wird automatisch korrekt ins PDO-DSN übernommen.  
Ohne `SMTP_USER`/`SMTP_PASS` wird der lokale MTA (localhost:25) ohne Authentifizierung verwendet.

**Wichtige Konstanten:**

| Konstante | Standard | Beschreibung |
|---|---|---|
| `SITE_URL` | `https://tickets.oratorienchor-kreuzlingen.ch` | Basis-URL (für Bestätigungslinks) |
| `SALES_EMAIL` | `billettverkauf@oratorienchor-kreuzlingen.ch` | Benachrichtigung bei neuen Bestellungen |
| `EMAIL_FROM` | `webseite@oratorienchor-kreuzlingen.ch` | Absenderadresse |
| `RESERVATION_EXPIRY_HOURS` | `24` | Gültigkeitsdauer des Bestätigungslinks |
| `RATE_LIMIT_MAX` | `5` | Max. Reservierungsversuche pro IP/Stunde |
| `DEFAULT_PRICE_KAT1` | `40` | Standardpreis Kat. I (CHF) |
| `DEFAULT_PRICE_KAT2` | `30` | Standardpreis Kat. II (CHF) |
| `DEFAULT_PRICE_STUDENT` | `20` | Studentenpreis (CHF) |
| `DELIVERY_SURCHARGE` | `5` | Aufpreis Postzustellung (CHF) |
| `BANK_IBAN` | `CH13 0078 4010 0907 5200 1` | IBAN für Zahlung |
| `BANK_NAME` | `Thurgauer Kantonalbank` | Bankname |
| `BANK_PC` | `85-123-0` | Postcheck-Nummer |

### 5. .htaccess prüfen

Die mitgelieferte `.htaccess` erledigt:
- HTTP → HTTPS-Weiterleitung
- Zugriffssperre auf `config.php`, `db_schema.php`, `composer.*`, `.env`
- Security-Header (CSP, X-Frame-Options, etc.)
- Kein Directory-Listing
- Caching für statische Assets (7 Tage)

### 6. Cron-Job einrichten

Stündlicher Cron-Job für die Bereinigung abgelaufener Reservierungen und alter Rate-Limit-Einträge:

```cron
0 * * * * php /pfad/zu/cron/cleanup.php
```

`cleanup.php` setzt `pending`-Reservierungen nach Ablauf der Frist auf `expired` und löscht Rate-Limit-Einträge älter als 24h.

### 7. Admin-Zugang

`/admin/login.php` – Standard-Zugang: `admin` / `admin`

Passwort nach der ersten Anmeldung im Admin-Bereich ändern.

## Buchungsablauf

1. **Sitzplan laden** – Die Webseite lädt den Sitzplan via `api/get-seats.php`, zeigt verfügbare, reservierte, deaktivierte und Bodan-Plätze farblich an.
2. **Plätze auswählen** – Klick auf verfügbare Plätze (nicht Bodan) fügt sie dem Warenkorb hinzu. Studentenpreis und Lieferoption (Abholung/Zustellung) wählbar.
3. **Formular ausfüllen** – Name, E-Mail, optional Telefon/Adresse/Notizen. Honeypot-Feld schützt vor Bots.
4. **Reservierung senden** – `POST /api/reserve.php` validiert, prüft Rate-Limiting, erstellt die Reservierung mit 32-Byte-Token in einer DB-Transaktion (mit `SELECT ... FOR UPDATE`) und sendet Bestätigungs-E-Mail.
5. **E-Mail bestätigen** – Kunde klickt Link in der E-Mail, `confirm.php` markiert Sitze in einer Transaktion mit `rowCount()`-Prüfung: nur wenn alle Sitze noch frei sind, wird die Reservierung bestätigt und die Zahlungs-E-Mail gesendet. Bei Konflikt wird zurückgerollt.
6. **Cron** – Bereinigt stündlich abgelaufene `pending`-Reservierungen.

## Architektur

```
OCK-Tickets/
├── index.php                 # Frontend (Ticketseite)
├── config.php                # Konstanten, DB, sendEmail()
├── db_schema.php             # Datenbank-Setup
├── schema.sql                # Datenbank-Setup (SQL)
├── .htaccess                 # Rewrites, Security-Header
├── composer.json             # PHPMailer 7.1
│
├── admin/
│   ├── index.php             # Dashboard (Sitzplan, Reservierungen, Einstellungen)
│   ├── login.php             # Admin-Login mit Rate-Limiting
│   └── export-csv.php        # CSV-Export einzelner/aller Reservierungen
│
├── api/
│   ├── get-seats.php         # GET  – Sitzplan + Preise
│   ├── reserve.php           # POST – Reservierung erstellen
│   ├── confirm.php           # GET  – Bestätigungslink
│   ├── reservation-by-seat.php # GET  – Reservierungsdaten zu Platz (Admin)
│   └── admin-update-seat.php # POST – Platzstatus ändern (Admin); berechnet `total_amount` bei Seat-Entfernung neu
│
├── assets/
│   ├── style.css             # Komplettes CSS
│   ├── app.js                # Frontend-Logik (Kunde)
│   ├── seat-grid.js          # Sitzplan-Rendering (von App + Admin genutzt)
│   └── images/               # Bilder (falls vorhanden)
│
├── cron/
│   └── cleanup.php           # Stündliche Bereinigung
│
└── vendor/                   # Composer (PHPMailer)
```

### Wichtige Dateien

| Datei | Zweck |
|---|---|
| `config.php` | DB, Konstanten, `sendEmail()`, `getSetting()`, `jsonResponse()` |
| `assets/seat-grid.js` | Rendert den Sitzplan (CHOR, Reihen 2–22, Empore 23–26) |
| `assets/app.js` | Frontend: Grid-Interaktion, Warenkorb, Formular, Bestellung |
| `assets/style.css` | Komplettes CSS (~740 Zeilen, CSS-Variablen für Farben) |
| `cron/cleanup.php` | Expired-Reservierungen + Rate-Limit-Bereinigung |

## Admin-Funktionen

- **Sitzplan-Verwaltung** (3 Modi):
  - Plätze aktivieren/deaktivieren (grau mit rotem Diagonalstrich)
  - Bodan-Plätze markieren (nur in Bodan Buchhandlung erhältlich)
  - Reservierungen entfernen (mit Bestätigungsdialog und optionalem CSV-Export)
- **Reservierungsliste** – Tabelle mit Details, Status-Badges, Info-Popup, CSV-Export, Löschen
- **Einstellungen** – Preise (Kat. I / II / Student), Konzertdatum, -ort, Buchung an/aus
- **Passwort ändern** – Eigenes Admin-Passwort
- **Alle Daten löschen** – Gefahrenzone mit Bestätigungsdialog

## E-Mail-Versand

- **PHPMailer 7.1+** via Composer
- `sendEmail()` in `config.php` – SMTP mit/ohne Auth, STARTTLS bei aktivem Benutzer
- 3 E-Mail-Templates:
  1. **Bestätigungs-E-Mail** – nach Reservierung mit Link (24h gültig)
  2. **Verkaufs-Benachrichtigung** – an `SALES_EMAIL` mit allen Details
  3. **Zahlungs-E-Mail** – nach Bestätigung mit Bankverbindung/Kassen-Hinweis

## Datenbank

Tabelle | Inhalt
---|---
`seats` | Sitzplätze (Nummer, Reihe, Kategorie, Sektion, Status, Bodan-Markierung)
`reservations` | Reservierungen (Kundendaten, Plätze, Betrag, Token, Status, IP)
`settings` | Key-Value-Konfiguration
`admin_users` | Admin-Login (bcrypt)
`rate_limits` | IP-basiertes Rate-Limiting

### Sitzplatz-Status

Die effektiven Status `reserved` und `pending` werden aus den aktuellen Reservierungen abgeleitet, nicht aus `seats.status`:

- `available` – verfügbar (Kategoriefarbe) — in `seats.status` gespeichert
- `reserved` – reserviert/vergeben (grau, durchgestrichen) — aus `reservations`-Tabelle abgeleitet
- `pending` – wird reserviert (gelb) — aus `reservations`-Tabelle abgeleitet
- `disabled` – vom Admin deaktiviert (weiss, roter Diagonalstrich) — in `seats.status` gespeichert

## Sicherheit

- **bcrypt** für Admin-Passwörter
- **32-Byte-Token** (bin2hex(random_bytes(32))) für Bestätigungslinks
- **Rate-Limiting** – 5 Versuche pro Stunde pro IP (Reservierung + Admin-Login)
- **Honeypot** – unsichtbares Feld gegen Bots
- **Session-Management** – Admin-Sitzung mit `session_start()`
- **CSRF-Schutz** – Alle Admin-Formulare und der `admin-update-seat.php`-Endpunkt validieren ein Session-gebundenes CSRF-Token
- **Transaktionsschutz** – `reserve.php` und `confirm.php` verwenden DB-Transaktionen mit `FOR UPDATE` bzw. `rowCount()`-Prüfung gegen Double-Booking
- **Logout via POST** – Admin-Logout erfordert POST + CSRF-Token
- **CSP-Header** – Content-Security-Policy eingeschränkt
- **HTTPS-Erzwingung** – via .htaccess-Rewrite
- **Dateisperren** – `.htaccess` blockiert direkten Zugriff auf `config.php`, `db_schema.php`, `composer.*`
- **Kein Directory-Listing**
- **Prepared Statements** (PDO) – keine SQL-Injection

## Datenschutz

- Die IP-Adresse wird bei Reservierung gespeichert (24h für Rate-Limiting, dann bereinigt)
- Löschung aller Kundendaten über die Gefahrenzone im Admin
- CSV-Export aller Kundendaten möglich (Auskunftspflicht)
- Datenschutzerklärung auf der Ticketseite verlinkt
- Hostpoint ADV gilt als vereinbart (Hostpoint-AGB Stand Sept. 2023)
- Siehe `datenschutzanalyse.md` und `datenschutzergaenzung.md` für Details
