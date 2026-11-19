# Den Songbook-Code Schritt für Schritt verstehen

Das Programm besteht aus zwei Teilen: Vue läuft im Browser und zeigt die Oberfläche. PHP läuft innerhalb von Nextcloud und prüft Rechte, liest Dateien und speichert den Metadatenindex.

1. **Nextcloud erkennt die App.** [appinfo/info.xml](../appinfo/info.xml) beschreibt Name, Version, Lizenz und unterstützte Nextcloud-Version. Der Navigationseintrag öffnet Songbook.

2. **Die Anfrage bekommt eine zuständige Methode.** [appinfo/routes.php](../appinfo/routes.php) ordnet URLs einem Controller zu. Die Hauptseite führt zu `PageController::index()`, die Liedliste zu `LibraryController::songs()`.

3. **Die Seite lädt die Oberfläche.** [PageController.php](../lib/Controller/PageController.php) liefert die Vorlage [main.php](../templates/main.php). Sie bindet die gebauten JavaScript-/CSS-Dateien ein und enthält den leeren Bereich `songbook-app`.

4. **Vue füllt diesen Bereich.** [src/main.ts](../src/main.ts) startet [App.vue](../src/App.vue). Die App fragt zuerst Status und zugängliche Bibliotheken ab. Erst nach erfolgreicher Antwort zeigt sie Inhalte. `ref` speichert veränderliche Werte wie die Auswahl; `computed` berechnet daraus die aktuelle Bibliothek.

5. **Du wählst einen Ordner aus.** [LibrarySettings.vue](../src/components/LibrarySettings.vue) zeigt die erreichbaren Ordner und sendet Name und Ordner-ID an die API. Es wird kein Ordner automatisch zur Bandbibliothek. Freigaben werden weiterhin in Nextcloud Dateien vergeben.

6. **Der API-Client übersetzt Klicks in HTTP-Anfragen.** [libraryApi.ts](../src/services/libraryApi.ts) baut die URL, übergibt Parameter und kontrolliert die OCS-Antwort. TypeScript beschreibt deren Form; zusätzliche Laufzeitprüfungen erkennen fehlerhafte Serverantworten. Ein `AbortController` verwirft überholte Anfragen.

7. **PHP prüft jede Anfrage.** [LibraryController.php](../lib/Controller/LibraryController.php) validiert Typen und IDs und ruft die zuständigen Services auf. Nextcloud erledigt Anmeldung und CSRF-Schutz. [PermissionService.php](../lib/Service/PermissionService.php) prüft den Ordner aus der Sicht des angemeldeten Benutzers. Die Kenntnis einer fremden ID reicht nicht zum Zugriff.

8. **Die Bibliothek verbindet Dateien und Index.** [LibraryService.php](../lib/Service/LibraryService.php) verwaltet Ordnerreferenzen. [Catalog.php](../lib/Db/Catalog.php) speichert Bibliotheks- und Songmetadaten mit parametrisierten Datenbankabfragen. Die [Migration](../lib/Migration/Version000200Date20261113000000.php) legt die Tabellen an. Eine Song-ID gehört zur App; eine Datei-ID gehört zum Nextcloud-Dateisystem.

9. **Der Import bewahrt das Original.** [chordProImport.ts](../src/services/chordProImport.ts) prüft die lokale Datei. [ChordProService.php](../lib/Service/ChordProService.php) prüft sie erneut auf dem Server und liest nur Titel, Interpret und Tonart aus. [ImportWriter.php](../lib/Service/ImportWriter.php) legt die Datei unter einer Sperre ausschließlich neu an. Bestehende Namen werden abgewiesen. BOM, Zeilenenden, Akkorde und unbekannte Direktiven bleiben erhalten.

10. **Die Liste liest nur erreichbare Dateien.** [SongService.php](../lib/Service/SongService.php) vergleicht den aktuellen Ordner mit dem Index, filtert die Suche und sortiert Ergebnisse. Beim Öffnen liest er den aktuellen Quelltext. [SongLibrary.vue](../src/components/SongLibrary.vue) zeigt ihn sicher als Text an. Die Akkorddarstellung über dem Liedtext folgt erst in Phase 3.

11. **Änderungen in Nextcloud Dateien werden berücksichtigt.** [Application.php](../lib/AppInfo/Application.php) registriert [FileChangedListener.php](../lib/Listener/FileChangedListener.php). Schreiben, Umbenennen und Löschen markieren den Index zur erneuten Prüfung. Zusätzlich prüft jeder Listenabruf den echten Ordner; ein veralteter Index gewährt niemals Zugriff.

12. **Tests und Build sichern den Ablauf ab.** [tests/frontend](../tests/frontend) prüft Vue-Verhalten, Import und API-Antworten. [tests/php](../tests/php) prüft Metadaten, Rechte und Fehlerfälle. [tests/integration](../tests/integration) verwendet echte Nextcloud-Konten und Freigaben. [vite.config.ts](../vite.config.ts) erzeugt die Browserdateien und Lizenzhinweise. Der [CI-Workflow](../.github/workflows/ci.yml) führt diese Prüfungen auf GitHub aus.

Der wichtigste Zusammenhang: Die ChordPro-Datei ist das Original. Der Datenbankindex beschleunigt die Übersicht. Nextcloud entscheidet über die Berechtigungen.
