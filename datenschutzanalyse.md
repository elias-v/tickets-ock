# Datenschutzrechtliche Bewertung: OCK-Tickets

> **Disclaimer:** Dies ist eine technische Analyse der aktuellen Implementierung aus
> datenschutzrechtlicher Sicht, erstellt durch eine KI. Sie ersetzt keine anwaltliche
> Beratung. Bitte lass die finale Beurteilung von einem Fachanwalt für Datenschutzrecht
> (CH/DE) vornehmen.

---

## 1. Anwendbares Recht

| Rahmen | Anwendbar? | Begründung |
|--------|-----------|-----------|
| **Schweizer DSG (nDSG/nFADP)** – in Kraft seit 1.9.2023 | **Ja, zwingend** | Sitz des Vereins in Kreuzlingen TG, Website unter `.ch`, Hosting in der Schweiz |
| **EU-DSGVO (GDPR)** | **Wahrscheinlich (Art. 3 Abs. 2)** | Kreuzlingen liegt an der deutschen Grenze; Konzerte ziehen vermutlich EU-Besucher an; Website ist auf Deutsch und akzeptiert Buchungen von EU-Bürgern |

**Fazit:** Der strengere Massstab (DSGVO) sollte als Referenz dienen, da die Schweizer
Gesetzgebung seit 2023 weitgehend an die DSGVO angeglichen ist.

---

## 2. Kritische Lücken (Rot – sofort handlungsbedürftig)

| # | Problem | Betroffene Norm | Risiko |
|---|---------|----------------|--------|
| 🔴 1 | **Keine Datenschutzerklärung** auf der Website | Art. 19 nDSG / Art. 13 DSGVO | Bussgeld bis CHF 250'000 (nDSG) bzw. bis 20 Mio. EUR (DSGVO) |
| 🔴 2 | **Keine Rechtsgrundlage** für die Datenverarbeitung dokumentiert | Art. 6 DSGVO / Art. 31 nDSG | Jede Verarbeitung ist ohne Rechtsgrundlage rechtswidrig |
| 🔴 3 | **IP-Adresse wird ohne Rechtsgrundlage / Hinweis gespeichert** (`ip_address` in `reservations`, `rate_limits`) | Art. 4 Nr. 1 DSGVO (IP = personenbezogen) | Verstoss gegen das Transparenzgebot |
| 🔴 4 | **Google Fonts** laden IP-Adressen an Google aus ohne Einwilligung | EuGH, Urteil C-634/21 („Google Fonts") | In Deutschland bereits Abmahnungen und Schadensersatzklagen |
| 🔴 5 | **Keine Löschfrist** – Daten werden bis zur manuellen Löschung gespeichert | Art. 5 Abs. 1 lit. e DSGVO (Speicherbegrenzung) | Verletzung des Grundsatzes der Speicherbegrenzung |
| 🔴 6 | **Export-CSV** enthält alle personenbezogenen Daten, kein Log, wer wann exportiert hat | Art. 5 Abs. 2 DSGVO (Rechenschaftspflicht) | Keine Nachvollziehbarkeit, wer Daten abgerufen hat |

---

## 3. Mittelschwere Lücken (Orange – zeitnah beheben)

| # | Problem | Betroffene Norm |
|---|---------|----------------|
| 🟠 7 | **E-Mails unverschlüsselt** per `mail()` – Klartext-Transport personenbezogener Daten | Art. 32 DSGVO (Sicherheit der Verarbeitung) |
| 🟠 8 | **Kein Auftragsverarbeitungsvertrag (AVV / ADB-Vereinbarung)** mit dem Hoster | Art. 28 DSGVO / Art. 9 nDSG |
| 🟠 9 | **Kein SSL-Zwang** – kein HTTP→HTTPS-Redirect | Art. 32 DSGVO |
| 🟠 10 | **Admin-Standardpasswort** (`admin/admin`) – kein Brute-Force-Schutz | Art. 32 DSGVO |
| 🟠 11 | **Kein Zugriffslog / Audit-Trail** – nicht nachvollziehbar, welcher Admin wann welche Daten gesehen/gelöscht hat | Art. 5 Abs. 2 DSGVO |
| 🟠 12 | **Keine Benachrichtigung bei Löschung** – Kunde erfährt nicht, dass seine Reservierung gelöscht wurde | Art. 14 DSGVO (Transparenz) |

---

## 4. Kleinere Lücken (Gelb – nice to have)

| # | Problem |
|---|---------|
| 🟡 13 | Freitextfeld `notes` ohne Limite – potenziell beliebige sensitive Daten |
| 🟡 14 | Bankverbindung (IBAN) in unverschlüsselten E-Mails |
| 🟡 15 | Keine CORS-Einschränkung (`Access-Control-Allow-Origin: *`) |
| 🟡 16 | Keine Content Security Policy (CSP) im `.htaccess` |
| 🟡 17 | Kein Cookie-Consent-Banner (PHP-Session-Cookie für Admin, Google Fonts) |
| 🟡 18 | Keine Möglichkeit für Kunden, ihre Daten abzufragen oder löschen zu lassen (Art. 15, 17 DSGVO) |
| 🟡 19 | Login nur mit Passwort – keine 2FA, kein Account-Lockout |

---

## 5. Was aktuell technisch passiert (Datenfluss)

### Erhobene Daten

| Feld | Typ | Erforderlich? | Verwendung |
|------|-----|---------------|------------|
| Name (Vor- und Nachname) | TEXT | Ja | Reservierung, E-Mail-Bestätigung, Admin-Ansicht |
| E-Mail-Adresse | TEXT | Ja | Bestätigungslink, Zahlungsaufforderung |
| Telefon | TEXT | Nein | Kontakt bei Rückfragen |
| Strasse + Nr. | TEXT | Nur bei Postzustellung | Versand der Billette |
| PLZ + Ort | TEXT | Nur bei Postzustellung | Versand der Billette |
| Notizen / Bemerkungen | TEXT (frei) | Nein | Beliebig – potenziell sensitive Daten |
| Sitzplätze | JSON | Ja | Reservierungslogik |
| Studentenrabatt | Boolean | Nein | Preisberechnung |
| Lieferoption | ENUM | Ja | Abholung vs. Post |
| **IP-Adresse** | VARCHAR(45) | **Automatisch** | Spam-Schutz, Rate-Limiting (nicht kommuniziert) |

### Speicherung

- **Datenbank:** MySQL/MariaDB auf `vabibese.mysql.db.internal` (extern gehostet)
- **Tabellen:** `reservations` (alle Kundendaten), `rate_limits` (IPs), `seats` (keine Personen-daten), `admin_users` (Passwort-Hash)
- **Verschlüsselung:** Keine – alle Daten im Klartext in der DB
- **Keine Dateiablage** für personenbezogene Daten

### Übermittlung

- **Website:** HTTPS (`https://tickets.oratorienchor-kreuzlingen.ch`) – aber kein automatischer HTTP→HTTPS-Redirect
- **E-Mails:** PHP `mail()` ohne SMTP/TLS – Klartext-Transport
- **Google Fonts:** IP-Adressen der Besucher werden an Google-Server übermittelt
- **CORS:** `Access-Control-Allow-Origin: *` – jeder Dritte könnte API-Endpunkte aufrufen

### Löschung / Aufbewahrung

- **Automatisch:** Pending-Reservierungen nach 24h → expired; Rate-Limits nach 24h gelöscht
- **Manuell:** Admin kann einzelne oder alle Reservierungen löschen
- **Keine automatische Löschung** bestätigter Reservierungen – verbleiben unbefristet
- **Kein Soft-Delete / Audit-Trail** bei Löschung
- **Keine Anonymisierungsfunktion**

---

## 6. DSGVO-konformer Soll-Zustand

### 6.1 Datenschutzerklärung erstellen und einbinden

Eine vollständige Datenschutzerklärung nach Art. 13 DSGVO / Art. 19 nDSG muss enthalten:

- Wer ist Verantwortlicher (Verein, Kontaktdaten)
- Welche Daten werden zu welchem Zweck verarbeitet
- Rechtsgrundlage (z.B. Art. 6 Abs. 1 lit. b DSGVO – Vertragserfüllung für Buchung)
- Speicherdauer / Löschfristen
- Rechte der betroffenen Person (Auskunft, Löschung, Berichtigung, Datenübertragbarkeit)
- Beschwerderecht beim EDÖB (CH) bzw. der zuständigen Aufsichtsbehörde (EU)
- Hinweis auf Google Fonts / Drittanbieter
- Kontakt für Datenschutzanfragen

### 6.2 Rechtsgrundlage dokumentieren

| Verarbeitung | Rechtsgrundlage | Anmerkung |
|-------------|----------------|----------|
| Name, E-Mail, Sitzplätze, Zahlung | Art. 6 Abs. 1 lit. b DSGVO (Vertragserfüllung) | Unproblematisch |
| IP-Adresse, Rate-Limiting | Art. 6 Abs. 1 lit. f DSGVO (berechtigtes Interesse) | Muss in Datenschutzerklärung benannt werden |
| Google Fonts | Einwilligung (Art. 6 Abs. 1 lit. a) ODER lokales Hosting | Empfohlen: lokal hosten |
| Notizen (freiwillig) | Art. 6 Abs. 1 lit. a (Einwilligung) | Checkbox beim Absenden |

### 6.3 Löschkonzept implementieren

- **Automatische Löschung** aller Daten (auch bestätigter Reservierungen) **12 Monate nach Konzert**
- Ausnahme: Buchhaltungsrelevante Daten (Name, Betrag, Datum) nach OR (10 Jahre) – aber ohne Adresse, Telefon, Notizen
- **Anonymisierung** als Alternative: `customer_name = 'ANONYMISIERT'`, E-Mail und Telefon leeren
- **Löschung auf Antrag** des Kunden jederzeit möglich (E-Mail-Kontakt)

### 6.4 Technische Massnahmen

| Massnahme | Aufwand | Priorität |
|-----------|---------|-----------|
| SSL erzwingen (HTTP→HTTPS) | 5 Min. | 🔴 Hoch |
| Google Fonts lokal hosten | 30 Min. | 🔴 Hoch |
| SMTP+TLS für E-Mail-Versand | 2 h | 🟠 Mittel |
| AVV mit Hosting-Provider abschliessen | 1–2 h | 🟠 Mittel |
| CORS einschränken | 5 Min. | 🟡 Niedrig |
| CSP (Content Security Policy) setzen | 15 Min. | 🟡 Niedrig |
| Admin-Rate-Limiting + starkes Passwort | 30 Min. | 🟠 Mittel |

### 6.5 Prozessuale Massnahmen

- **Verzeichnis der Verarbeitungstätigkeiten (VVT)** führen (Art. 30 DSGVO)
- **Zugriffslog** für Admin-Datenaufrufe (wer, wann, welche Daten-ID)
- **Lösch-Log** (wer, wann, welche Daten-ID gelöscht, Grund)
- **Schnittstelle für Betroffenenrechte**: E-Mail-Kontakt für Auskunfts-/Löschbegehren, innerhalb 30 Tagen beantwortbar

---

## 7. Umsetzungs-Priorität für einen Verein

| Priorität | Massnahme | Aufwand |
|-----------|-----------|---------|
| **Sofort (diese Woche)** | Datenschutzerklärung schreiben & auf Website einfügen | 1 Tag |
| **Sofort** | Google Fonts lokal hosten | 30 Min. |
| **Sofort** | SSL-Redirect einrichten | 5 Min. |
| **In 1–2 Wochen** | AVV mit Hosting-Anbieter abschliessen | 1–2 h |
| **In 1–2 Wochen** | Löschkonzept + Cronjob (auto-delete nach 12 Monaten) | 2 h |
| **In 1 Monat** | SMTP+TLS für E-Mail-Versand | 2 h |
| **In 1 Monat** | Admin-Passwort ändern, Rate-Limiting für Login | 1 h |
| **In 3 Monaten** | Zugriffs- + Löschlog implementieren | 4 h |
| **In 3 Monaten** | Betroffenenrechte-Schnittstelle (Auskunft/Löschung) | 4 h |

---

## 8. Kurzantwort

> **Ist die aktuelle Implementierung rechtskonform?**

**Nein**, in der aktuellen Form ist sie **nicht** DSGVO/nDSG-konform. Die schwerwiegendsten
Mängel sind:

1. **Keine Datenschutzerklärung** – Verstoss gegen Art. 13 DSGVO / Art. 19 nDSG
2. **Keine Löschfrist** – Daten verbleiben unbefristet (ausser man löscht manuell)
3. **Google Fonts** – Datenschutzrechtswidrige Drittlandübermittlung ohne Einwilligung
4. **Kein AVV mit dem Hoster** – Verstoss gegen Art. 28 DSGVO
5. **Keine Dokumentation der Rechtsgrundlage**

> **Kann man das so machen, wenn man Bedingungen erfüllt?**

**Ja, mit folgenden Auflagen:**

- Datenschutzerklärung einbinden (Pflicht)
- Google Fonts lokal hosten (einfach)
- Löschfrist implementieren (12 Monate nach Konzert)
- AVV mit Hoster abschliessen
- SSL erzwingen
- Admin-Zugriff absichern (Passwort, Rate-Limiting)
- IP-Erhebung in der Datenschutzerklärung offenlegen

Die gute Nachricht: Ein Verein mit lokalen Konzerten in der Schweiz hat einen relativ
kleinen Risiko-Appetit der Aufsichtsbehörden – bei gutwilliger Umsetzung der offensichtlichen
Massnahmen ist das Risiko von Bussgeldern gering. Ein **Verstoss gegen Art. 19 nDSG
(keine Datenschutzerklärung)** ist aber auch in der Schweiz busgeldbewehrt (bis CHF 250'000).

**Empfehlung:** Datenschutzerklärung aufsetzen (lassen) und Google Fonts lokal hosten –
das sind die beiden einfachsten und dringendsten Punkte.
