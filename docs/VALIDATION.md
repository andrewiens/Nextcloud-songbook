# Prüfbericht – Phase 2

Stand: **19.11.2026**, lokale Songbook-Version **0.2.0-dev**. Die Songbibliothek ist implementiert und in Nextcloud 35 getestet. Das ist eine Entwicklungsstufe, keine Produktions- oder Store-Freigabe. Der [Phase-1-Bericht](VALIDATION_PHASE1.md) bleibt als Historie erhalten.

## Umgebung

Nextcloud 35.0.2 (intern 35.0.2.2), PHP 8.5.12, SQLite im isolierten Compose-Projekt `songbook-dev` auf `127.0.0.1:8081`. Node 24.22.3, npm 11.19.1, Vue 3.5.45, Nextcloud Vue 9.14.1, Vite 7.3.8, Vitest 4.1.12, PHPUnit 11.5.58. npm-/Composer-Lockdateien bleiben unverändert. Die Produktionsinstanz bei Wolkensicher und Port 8080 wurden nicht verändert.

## Ausgeführte Prüfungen

| Prüfung | Ergebnis |
| --- | --- |
| Frontend-Tests | **39 bestanden**, sechs Testdateien |
| PHP-Tests | **34 bestanden**, 113 Assertions |
| HTTP-Grundtests | **6 bestanden** gegen echte Nextcloud |
| Bibliotheks-Integrationstests | **12 bestanden** mit vier Konten und echten Freigaben |
| `npm run lint -- --max-warnings=0` | Erfolgreich |
| `npm run typecheck` | Erfolgreich |
| `npm run build` | Erfolgreich, JavaScript/CSS und Lizenztexte erzeugt |
| `composer lint` | Alle PHP-Dateien syntaktisch gültig |
| `composer validate --strict` | Erfolgreich |
| Offizielles `info.xsd` | Metadaten gültig |
| `git diff --check` | Erfolgreich |
| `npm audit --audit-level=high` | Erfolgreicher Exit; **16 moderate**, keine hohen/kritischen Befunde |

Damit bestehen **91 automatisierte Tests**. Die Tests prüfen unter anderem:

- Dieselbe Bibliothek und dieselben Song-Referenzen für Besitzer, Bearbeiter und Leser; kein Zugriff für Außenstehende.
- Lesefreigaben verweigern Import und Konfiguration; entzogene Freigaben sperren bekannte IDs.
- Originalbytes einschließlich BOM, CRLF, Unicode und unbekannter Direktiven bleiben erhalten.
- Doppelte und gleichzeitige Importe überschreiben keine vorhandene Datei.
- Änderungen über WebDAV, Umbenennen, Löschen und Verschieben aus der Bibliothek werden korrekt berücksichtigt.
- Ungültige Dateinamen, Kodierung, Größe und API-Parameter werden abgewiesen.
- Session-Anfragen ohne CSRF-Token werden auch an den neuen schreibenden Endpunkten mit HTTP 412 abgewiesen.
- Vue verwirft veraltete Antworten, entfernt Inhalte bei Zugriffsfehlern und zeigt HTML im Liedtext ausschließlich als Text.

Testkonten und ihre Dateien wurden nach jedem Lauf entfernt. Der temporäre Browser-Testordner wurde ebenfalls nach Prüfung seines Inhalts entfernt. Nicht mehr erreichbare Metadatenzeilen können im erneuerbaren Index verbleiben; das gibt keinen Zugriff auf entfernte Dateien.

## Browserprüfung

Im In-App-Browser geprüft: Ordnerauswahl und Verbindung, Import über den echten Dateidialog, anschließendes Öffnen des Originals, Suche mit und ohne Treffer, Sortierung und erneutes Verbinden desselben Ordners. Keine JavaScript-Fehler in der abgefragten Browserkonsole. Desktop 1280 × 800 und Tablet 768 × 1024; auf dem Tablet kein horizontaler Dokumentüberlauf.

Nachweise: [Desktop](screenshots/phase2-desktop.jpg), [Tablet](screenshots/phase2-tablet.jpg). Die Bilder zeigen einen selbst erstellten temporären Funktionstest, keine Banddaten. Es wurden keine physischen iPads oder Android-Geräte getestet.

## Behobene Fehler

- Die fehlenden Bibliotheksrouten und Dateisystem-Listener wurden eingebunden.
- Die ursprüngliche exklusive Ordnersperre blockierte auch `Folder::newFile()`. Der Import nutzt nun die öffentliche Storage-API zur exklusiven Neuerstellung, aktualisiert den Dateicache und veröffentlicht Node-Ereignisse. Details und Grenzen stehen in [ARCHITECTURE.md](ARCHITECTURE.md).
- Ein Speicher-ETag änderte sich bei einer raschen externen Änderung nicht. Die Prüfung bestätigt deshalb tatsächlichen Inhalt und aktualisierte Metadaten; ETags allein werden nicht als spätere Editor-Konfliktprüfung zugesichert.
- Der vorgeschlagene Bibliotheksname folgt dem ausgewählten Ordner, ohne einen eigenen Namen zu überschreiben. Das persönliche Wurzelverzeichnis bleibt nicht verbindbar.
- Leere Importe sowie unsichere Namen werden in Browser und Backend konsistent abgewiesen.
- Debug wurde lokal deaktiviert, damit Nextcloud wieder versionierte Asset-URLs ausliefert. Debug allein verhindert veraltete Browserassets nicht. Ein PHP-Prozessabsturz nach der früheren Docker-Pause trat nach Containerneustart in den Integrationstests nicht erneut auf.

## Grenzen vor einem Release

Editor, Akkorddarstellung, Speichern mit Konfliktprüfung, Export, Setlisten und Auftrittsmodus gehören zu späteren Phasen. Phase 2 zeigt Originalquelltext und erfasst nur direkte Ordnerkinder (256 KiB je Lied, 1.000 Einträge je Ordner).

MariaDB/PostgreSQL, PHP 8.3/8.4, Gruppenordner-ACLs, externe Speicher, Verschlüsselung und Erweiterungen mit eigenen Datei-Hooks benötigen gesonderte Integrationsprüfungen. Der Create-only-Import benötigt Unterstützung für `fopen`-Modus `x`; ohne diese Unterstützung erfolgt kein unsicherer Rückfall auf Überschreiben. Der Storage-Pfad hat nicht dieselben Vorab-Hooks wie jede Node-API-Operation.

Die PHP-Versionen 8.3/8.4/8.5 sind in GitHub Actions vorgesehen. Der lokale Prüfbericht ersetzt kein Ergebnis dieser CI-Läufe. App-ID-Registrierung, Signierung und Store-Veröffentlichung stehen aus. Das lokale Archiv ist ein unsigniertes Entwicklungsartefakt.

Der npm-Audit ist damit nicht vollständig grün. Die moderaten Befunde betreffen vor allem `glob`/`minimatch` und abhängige Nextcloud-/Entwicklungswerkzeuge. Ein erzwungenes Downgrade der Nextcloud-Komponenten wurde nicht vorgenommen. Vor dem Produktionsrelease erneut bewerten.
