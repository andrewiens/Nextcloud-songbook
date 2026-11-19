# Architektur und Phasengrenzen

Songbook läuft als native Nextcloud-App. Nextcloud stellt Anmeldung, Dependency Injection, Dateisystem, Datenbank und API-Middleware bereit. Vue 3 und TypeScript werden zu lokalen JavaScript-/CSS-Dateien gebaut. Es gibt keinen zusätzlichen Anwendungsserver oder Dienst für Banddaten.

## Daten und Zugriffsrechte

Eine Bibliothek verweist auf einen ausdrücklich ausgewählten bestehenden Ordner. Freigaben verwaltet Nextcloud Files. `PermissionService` löst Ordner ausschließlich unter `IRootFolder::getUserFolder()` des angemeldeten Benutzers auf. Bibliotheks- und Song-IDs verleihen keine Rechte. Beim Öffnen muss die Datei weiterhin ein lesbares direktes Kind des Bibliotheksordners sein.

Die Migration erzeugt `songbook_libraries` und `songbook_songs` mit Nextclouds Datenbankabstraktion. Eine Ordner-ID ist eindeutig; pro Bibliothek und Datei-ID existiert höchstens ein Song. Der Index enthält nur Metadaten, keine führende Kopie des Liedtexts. Eine Umbenennung innerhalb derselben Bibliothek erhält die interne Song-ID.

`FileChangedListener` invalidiert Metadaten bei `NodeWrittenEvent`, `NodeRenamedEvent` und `NodeDeletedEvent`. Er liest keine fremden Lieddateien und lässt einen Fehler des erneuerbaren Index nicht zum Abbruch einer Files-Operation führen. Zusätzlich gleicht jede Liedliste sichtbare Datei-IDs, Namen und ETags ab. Unveränderte Dateien müssen nicht erneut gelesen werden.

Gelöschte oder aus der Bibliothek verschobene Dateien erscheinen nicht mehr und lassen sich über alte Song-IDs nicht öffnen. Indexzeilen können verbleiben. Sie werden nicht anhand der möglicherweise eingeschränkten Sicht eines Lesers global gelöscht. Administrative Bereinigung und Bibliothekslöschung sind noch offen; das gilt auch für Metadaten aus Integrationstests.

## Import und Konflikte

`ChordProService` prüft Dateiname, UTF-8 und Größe. `ImportWriter` verwendet eine exklusive native Ordnersperre und die öffentliche Storage-API `fopen(path, 'x')` zur ausschließlichen Neuerstellung. `Folder::newFile()` kann überschreiben und kollidiert unter dieser Sperre mit seinen eigenen verschachtelten Sperren. Deshalb wird es hier nicht verwendet. Es gibt keinen unsicheren Rückfall auf Schreibmodus `w`, wenn ein Speicher `x` nicht unterstützt.

Die Operation prüft aktuelle Ordnerrechte, vorhandene Dateinamen, Kapazität und Speicherplatz. Nach dem Schreiben aktualisiert die öffentliche Updater-API den Nextcloud-Dateicache; anschließend werden `NodeCreatedEvent` und `NodeWrittenEvent` ausgelöst. Scheitert das Schreiben nach erfolgreicher Neuerstellung, wird nur die eigene unvollständige Datei unter der gehaltenen Sperre entfernt. Scheitert später der Songindex, bleibt die Originaldatei erhalten und wird beim nächsten Abgleich erneut erfasst.

Dieser Create-only-Pfad ist auf lokalem Nextcloud-Speicher und Benutzerfreigaben getestet. Externe Speicher, Verschlüsselung, Gruppenordner-ACLs und Erweiterungen mit eigenen Schreib-/Scan-Hooks brauchen gesonderte Freigabetests. Die Storage-API durchläuft nicht automatisch dieselben Vorab-Hooks wie jede Node-API-Operation. Eine Produktionsfreigabe für solche Kombinationen ist damit nicht gegeben.

`version` enthält in Phase 2 das ETag. Einige Speicher können bei raschen Änderungen dasselbe ETag liefern; Dateisystemereignisse sorgen trotzdem für erneutes Einlesen. Für Phase 3 muss die Editor-Konfliktprüfung einen geeigneten Inhalts-/Versionsvergleich unter Sperre ergänzen.

## Oberfläche

`App.vue` steuert Navigation und Bibliotheksauswahl. `LibrarySettings.vue` verbindet Ordner; `SongLibrary.vue` übernimmt Suche, Sortierung, Import und Quelltextansicht. API-Antworten werden zur Laufzeit validiert. Abbruchsignale verhindern, dass eine verspätete Antwort eine neuere Auswahl überschreibt. Bei verweigertem Zugriff werden angezeigte Metadaten und Quelltexte entfernt. Inhalte werden als Text interpoliert, niemals als HTML ausgeführt.

Grenzen: 256 KiB je Lied, höchstens 1.000 direkte Verzeichniseinträge, keine rekursive Indizierung. Erkannt werden Titel (`title`/`t`), Interpret (`artist`) und Tonart (`key`); das Original bleibt unverändert. Es gibt keine dauerhafte Speicherung von Lieddaten im Browser.

## Weitere Phasen

Phase 3: Parser, formatierte Akkorde, Editor, Live-Vorschau, Speichern, Export und Konfliktprüfung. Phase 4: referenzbasierte Setlisten mit Reihenfolge, wiederholten Liedern und Notizen. Phase 5: Tablet-/Auftrittsmodus. Phase 6: Freigabetests, Sicherheitsprüfung und Store-Veröffentlichung. PWA und echte Offline-Synchronisation bleiben spätere Funktionen.

Verwendete öffentliche OCP-Schnittstellen wurden gegen die installierten Nextcloud-35-Quellen und OCP-35-Testtypen geprüft. Offizielle Grundlagen: [Dateisystem](https://docs.nextcloud.com/server/stable/developer_manual/basics/storage/filesystem.html), [Migrationen](https://docs.nextcloud.com/server/stable/developer_manual/basics/storage/migrations.html), [Datenbank](https://docs.nextcloud.com/server/stable/developer_manual/basics/storage/database.html). Tatsächliche Tests und Grenzen: [VALIDATION.md](VALIDATION.md).
