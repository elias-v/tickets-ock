# Ergänzungsvorschläge für die Datenschutzerklärung

> Die bestehende Datenschutzerklärung wurde mit dem Datenschutz-Generator erstellt und ist
> bereits umfangreich. Nachfolgend die spezifischen Ergänzungen für das Ticket-Reservierungs-
> system unter `tickets.oratorienchor-kreuzlingen.ch`, die in die bestehende DS eingearbeitet
> werden sollten.

---

## 1. Neue Tabelle in „Übersicht der Verarbeitungen"

Am Ende des bestehenden Abschnitts **"Übersicht der Verarbeitungen"** folgende Zeile
ergänzen:

**Arten der verarbeiteten Daten** (Ergänzung):

- Buchungsdaten (Name, E-Mail, Telefon, Adresse, Sitzplätze, Rabattstatus,
  Lieferoption, Zahlungsinformationen, IP-Adresse, Zeitstempel)

**Zwecke der Verarbeitung** (Ergänzung):

- Ticket-Reservierung und -Verkauf
- Sitzplatzverwaltung
- Betrugsprävention / Missbrauchsschutz (Rate-Limiting)
- Administrations- und Abrechnungszwecke
- CSV-Export für interne Verwaltung

---

## 2. Ergänzung im Abschnitt „Geschäftliche Leistungen / Eventmanagement"

Nach dem bestehenden Text zu Eventmanagement folgende Präzisierung einfügen:

> **Ticket-Reservierungssystem**
>
> Für die Online-Reservierung von Tickets nutzen wir ein separates Buchungssystem
> unter `tickets.oratorienchor-kreuzlingen.ch`. Im Rahmen der Reservierung erheben
> und verarbeiten wir die folgenden Daten:
>
> - **Name und Vorname** (Pflichtfeld) – zur Identifikation der Reservierung und
>   persönlichen Ansprache
> - **E-Mail-Adresse** (Pflichtfeld) – zum Versand des Bestätigungslinks und der
>   Zahlungsinformationen
> - **Telefonnummer** (freiwillig) – für Rückfragen seitens des Veranstalters
> - **Adresse (Strasse, PLZ, Ort)** (Pflichtfeld bei Postzustellung) – für den
>   Versand der Billette per Post
> - **Gewünschte Sitzplätze** – ausgewählt aus dem interaktiven Sitzplan
> - **Rabattstatus** (Schüler/Student oder keine Ermässigung)
> - **Lieferoption** (Abholung an der Kasse oder Postzustellung)
> - **Freitext-Bemerkungen** (freiwillig) – für Fragen, Anliegen oder
>   Zusatzinformationen
> - **IP-Adresse** – wird automatisch zum Schutz vor Missbrauch (z. B.
>   automatisierte Buchungsversuche) erhoben und für maximal 24 Stunden
>   gespeichert (Rate-Limiting)
> - **Zeitstempel** der Reservierung und Bestätigung
>
> Die Verarbeitung dieser Daten erfolgt zur Erfüllung des mit Ihrer Buchung
> geschlossenen Vertrags (Art. 6 Abs. 1 lit. b DSGVO) sowie auf Grundlage
> unseres berechtigten Interesses an der Sicherheit und Funktionsfähigkeit des
> Buchungssystems (Art. 6 Abs. 1 lit. f DSGVO – IP-Adresse für Rate-Limiting).
>
> **Datenübermittlung per E-Mail:** Nach erfolgreicher Reservierung erhalten Sie
> eine Bestätigungs-E-Mail mit einem Link zur Freischaltung Ihrer Buchung. Der
> Verein erhält eine separate Benachrichtigung über die Neureservierung. Beide
> E-Mails enthalten Ihren Namen, die gebuchten Sitzplätze sowie den Gesamtbetrag.
> Die E-Mail-Übermittlung erfolgt über den Server des Hosting-Anbieters.
>
> **CSV-Export:** Die Administratorinnen und Administratoren des Vereins können
> die Reservierungsdaten als CSV-Datei exportieren, um die Abwicklung der
> Veranstaltung (Sitzplatzverwaltung, Kassenabwicklung, Postversand)
> durchzuführen. Diese Exporte werden nicht dauerhaft auf dem Server
> gespeichert, sondern direkt an die Administratoren übermittelt.
>
> **Speicherdauer:** Reservierungsdaten werden bis zur Durchführung der
> Veranstaltung sowie für die Dauer der gesetzlichen Aufbewahrungspflichten
> gespeichert (10 Jahre gemäss Art. 958f OR für Buchhaltungsunterlagen).
> Nach Ablauf dieser Frist werden die Daten gelöscht. Ausgenommen sind
> Daten, die zur Erfüllung steuerrechtlicher Aufbewahrungspflichten
> erforderlich sind.
>
> **Hinweis zum Hosting:** Das Ticket-Reservierungssystem wird bei der
> Hostpoint AG (Rapperswil-Jona, Schweiz) betrieben. Die ADV
> (Auftragsdatenverarbeitungsvereinbarung) ist Bestandteil der AGB von
> Hostpoint und gilt automatisch für alle Kunden. Ein separater AVV ist
> nicht erforderlich.

---

## 3. Einleitungssatz in „Plug-ins und eingebettete Funktionen sowie Inhalte" (optional)

Falls im Ticketsystem keine Google Maps oder YouTube eingebunden sind (nur
Google Fonts), ist der bestehende Abschnitt ausreichend. Der Punkt Google Fonts
ist bereits gut beschrieben.

---

## 4. Zusammenfassung der Änderungen gegenüber dem aktuellen Stand

| Abschnitt | Änderung |
|-----------|----------|
| Übersicht der Verarbeitungen | Buchungsdaten als neue Datenart, Ticket-Zwecke ergänzen |
| Eventmanagement | Neuer Unterabschnitt "Ticket-Reservierungssystem" mit vollständiger Aufstellung aller Felder, Zwecke, Rechtsgrundlage, IP-Hinweis, CSV-Export, Speicherdauer, Hosting-Hinweis |

---

## 5. Hinweis zum Auftragsverarbeitungsvertrag (AVV)

Die bestehende DS erwähnt bereits den AVV mit Mailchimp. Für das Ticket-System
bei Hostpoint ist **kein separater AVV nötig**: Die ADV (Auftragsdatenverarbeitungs-
vereinbarung) ist seit September 2023 fester Bestandteil der AGB von Hostpoint und
gilt automatisch für alle Kunden. Die Anforderung aus Art. 28 DSGVO / Art. 9 nDSG
ist damit erfüllt.

*Quelle: [support.hostpoint.ch](https://support.hostpoint.ch/de/administratives/schweizer-dsg/haeufig-gestellte-fragen-zum-neuen-schweizer-dsg/bietet-hostpoint-fuer-kunden-eine-auftragsdatenverarbeitungsvereinbarung-adv)*

---

Stand: Mai 2026  
Erstellt für: Oratorienchor Kreuzlingen – Ticket-Reservierungssystem
