# Prüfbericht – Phase 1

Stand: **13.11.2026**, lokal ausgeführt. Ergebnis: Songbook `0.1.0-dev` wurde in einer eigenen Nextcloud-35-Instanz installiert, aktiviert und im Browser geöffnet. Die produktive Wolkensicher-Instanz und die vorhandene lokale Instanz auf Port 8080 wurden nicht verändert.

## Tatsächlich verwendete Umgebung

| Komponente | Version |
| --- | --- |
| Nextcloud | 35.0.2, interne Version 35.0.2.2 |
| PHP | 8.5.12 |
| Datenbank | Nextcloud-eigene SQLite-Entwicklungsdatenbank |
| Node / npm | 24.22.3 / 11.19.1 |
| Composer | 2.11.2 |
| Vue / Nextcloud Vue | 3.5.45 / 9.14.1 |
| Vite / Vitest / ESLint | 7.3.8 / 4.1.12 / 10.14.0 |
| PHPUnit / OCP-Testtypen | 11.5.58 / 35.0.3 |

Auf dem Host sind Node, npm, PHP und Composer nicht installiert; die entsprechenden Befehle wurden mit den mitgelieferten Docker-Diensten ausgeführt. Python 3.9 führt die HTTP-Suite aus. Docker Compose: 5.7.0. Verwendetes Nextcloud-Image: `nextcloud:35-apache`, Digest `sha256:786c102155d2717ba0f53a62b3fdd7a1354edc48e800b94dbe32294d9a5752a2`. Die Tags in Compose bleiben auf Hauptversionen begrenzt; Lockdateien fixieren npm-/Composer-Abhängigkeiten.

## Erfolgreiche Prüfungen

| Prüfung | Ergebnis |
| --- | --- |
| `npm run build` | Produktionsbundle und CSS erzeugt; Drittanbieter-Lizenztexte beigefügt |
| `npm run typecheck` | Erfolgreich |
| `npm run lint -- --max-warnings=0` | Keine Fehler oder Warnungen |
| `npm test` | **13 Tests bestanden**: OCS-Antwortprüfung, URL-Helfer, Fehlerweitergabe, Laden, Navigation, Lesezeichen, erneuter Verbindungsversuch, Abbruch |
| `composer validate --strict` | Konfiguration und Lockdatei gültig |
| `composer lint` | Alle PHP-Dateien syntaktisch gültig |
| `composer test` | **5 Tests / 21 Assertions bestanden**: Statusservice, Controller, Sicherheitsattribute |
| `composer check-platform-reqs` | PHP und erforderliche Erweiterungen vorhanden |
| `composer audit` | Keine bekannten Sicherheitsmeldungen |
| Offizielle `info.xsd` + `xmllint` und PHP-Prüfskript | `appinfo/info.xml` gültig, Nextcloud min/max 35 |
| Compose- und Workflow-YAML | Erfolgreich mit Ruby/Psych geparst |
| `occ app:enable songbook` | `songbook 0.1.0-dev enabled` |
| `python3 tests/integration/test_http.py` | **6 HTTP-Tests bestanden**, siehe unten |
| Entwicklungsarchiv | Zwei Erzeugungen mit identischen Eingaben liefern identische SHA-256-Werte; App-Wurzel, Assets, Lizenzhinweise und Dokumentation enthalten; keine `.env`, `.git`, `vendor` oder `node_modules` |

Die sechs HTTP-Tests prüfen an einer echten Nextcloud:

- Anonyme API-Zugriffe werden abgewiesen.
- Die Statusroute akzeptiert keine POST-Mutation.
- Zwei unabhängig authentifizierte gewöhnliche Konten können die API gleichzeitig lesen.
- Eine echte Browser-Sitzung kann Seite, JavaScript, CSS und OCS-Antwort abrufen. Ohne CSRF-Token antwortet Nextcloud 35 mit HTTP **412**.
- `.env` und `.git/config` sind über den Webserver nicht zugänglich.
- Die HTML-Seite verlangt eine Anmeldung.

Die temporären Testkonten wurden über die offizielle Provisioning-API angelegt und nach jedem Lauf entfernt. Diese Tests bestätigen noch keine Song-/Ordnerberechtigungen oder Schreibkonflikte; entsprechende Endpunkte existieren in Phase 1 nicht.

## Browserprüfung

Anmeldung, eingebettete Nextcloud-Navigation, Wechsel zwischen Liedern und Setlisten sowie das Öffnen/Schließen der mobilen Navigation wurden im In-App-Browser geprüft. Größen: Desktop **1280 × 800**, Tablet **768 × 1024**, Smartphone **390 × 844**. Auf dem Smartphone entsprach die Dokumentbreite der Ansichtsbreite (390 Pixel); kein horizontaler Überlauf. Nach der Build-Korrektur wurden keine neuen JavaScript-Fehler beobachtet.

Nachweise: [Desktop](screenshots/desktop.jpg), [Tablet](screenshots/tablet.jpg), [Smartphone](screenshots/mobile.jpg). Das sind Prüfungen verschiedener Ansichtsgrößen, keine Tests auf physischer iPad-/Android-Hardware.

## Gefundene und behobene Fehler

- Die anfangs fehlende verpflichtende Bugtracker-URL wurde mit der vom Auftraggeber angegebenen Repository-URL ergänzt. Das offizielle Metadatenschema besteht jetzt vollständig.
- Die Nextcloud-Komponentenbibliothek benötigte Build-Konstanten für `appName` und `appVersion`; diese werden jetzt aus `package.json` eingebunden. Der lokale Nextcloud-Debug-Modus verhindert veraltete Assets im Browsercache.
- Der Build schrieb zunächst direkt in das Quellverzeichnis. Er baut jetzt in `.build/` und kopiert nur erzeugte Laufzeitassets nach erfolgreichem Build.
- Ein Test-Hook gab versehentlich die Mock-Funktion als Vitest-Aufräumfunktion zurück. Der Hook gibt jetzt nichts zurück; der Test zur Fehlerweitergabe besteht.
- Der HTTP-Test-Login erhielt den von Nextcloud 35 geforderten Origin-Header. Die CSRF-Assertion wurde auf den tatsächlich verwendeten OCS-Code 412 korrigiert. Sicherheitsprüfungen wurden nicht deaktiviert.
- Formatierungswarnungen wurden behoben; ESLint wurde auf die unterstützte Hauptversion 10 aktualisiert.
- Einige Nextcloud-npm-Pakete enthalten keinen separaten Lizenztext. Der Build ergänzt für deren exakte SPDX-Lizenz den mitgelieferten AGPL-/GPL-Text; Copyright-Hinweise im Bundle bleiben erhalten.

## Verbleibende Grenzen und offene Probleme

1. `npm audit` meldet nach kompatiblen Korrekturen **17 moderate**, keine hohen oder kritischen Befunde. Ursache sind `minimatch`/`glob` und davon abhängige Pakete, unter anderem in der Nextcloud- und Test-/Lint-Abhängigkeitskette. Ein erzwungenes Downgrade oder ungeprüfter Major-Override wurde nicht vorgenommen. Vor einem Produktionsrelease sind aktualisierte Upstream-Pakete und eine erneute Bewertung erforderlich; der Audit ist **nicht grün**.
2. PHP 8.3 und 8.4 sind in der CI-Matrix vorgesehen, hier aber nicht ausgeführt. Ebenso fehlen reale iPad-/Android-Browser und PostgreSQL-/MariaDB-Integrationstests. Die laufende Entwicklungsinstallation verwendet PHP 8.5 und SQLite.
3. Die GitHub-Workflows wurden geschrieben und syntaktisch geprüft, aber nicht auf GitHub ausgeführt. Es erfolgten kein Push, kein öffentlicher Release und keine Store-Einreichung. Das vorhandene Zielrepository wurde nur lesend mit `git ls-remote` geprüft und als lokales `origin` hinterlegt.
4. App-ID-Registrierung, Zertifikat und Signierung sind offen. Das Entwicklungsarchiv ist unsigniert und keine Produktionsfreigabe.
5. Songbibliothek, ChordPro-Editor, Setlistenoperationen, Dateisystem-Synchronisation und Auftrittsmodus gehören zu den nächsten Phasen. Es gibt keine Mock-Lieder und keine vorgetäuschte Datenhaltung.

Nächster Schritt: gemeinsam genutzten Bibliotheksordner explizit auswählen, über die Benutzersicht autorisieren und das Metadaten-/Dateireferenzmodell mit Migration und Mehrbenutzer-Berechtigungstests implementieren.
