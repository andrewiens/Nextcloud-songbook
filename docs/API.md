# OCS-API v1 – Phase 2

Basis: `/ocs/v2.php/apps/songbook/api/v1/`. Alle Endpunkte erfordern Nextcloud-Authentifizierung. Browser verwenden `@nextcloud/axios` für den Request-Token und `@nextcloud/router` für das Installations-Unterverzeichnis. `NoAdminRequired` erlaubt gewöhnliche Benutzer; kein API-Endpunkt deaktiviert CSRF. Nur die navigierbare HTML-Seite trägt `NoCSRFRequired`.

JSON über `Accept: application/json` oder `format=json` anfordern. Antworten stehen in `ocs.data`; zusätzlich muss `ocs.meta.status` gleich `ok` sein. Externe Clients verwenden unterstützte Nextcloud-Authentifizierung und `OCS-APIRequest: true`.

| Methode / Pfad | Eingabe | Inhalt von `ocs.data` |
| --- | --- | --- |
| GET `status` | – | `app`, installierte `version`, `apiVersion: 1`, `capabilities: {songLibrary: true, setlists: false}` |
| GET `folders` | Query `folderId`, 0 = eigene Dateien | `{folder, folders}` mit jeweils `id`, `name`, benutzerrelativem `path` |
| GET `libraries` | – | `{libraries: [...]}` mit zugänglichen Bibliotheken |
| POST `libraries` | JSON `{name, folderId}` | `{library}`; derselbe Ordner liefert dieselbe Bibliothek |
| GET `songs` | Query `libraryId`, optional `search`, `sort` | `{songs: [...], warnings: [...]}` |
| GET `songs/{id}` | interne Song-ID | `{song}` einschließlich `content` |
| POST `songs/import` | JSON `{libraryId, filename, content}` | `{song}` einschließlich des gespeicherten Originals |
| POST `libraries/{id}/sync` | Bibliotheks-ID | `{songs: [...], warnings: [...]}` ohne Suchfilter |

Bibliothek: `{id, name, folderId, canImport}`. Song: `{id, fileId, filename, title, artist, key, version}`. IDs werden als JSON-Zahlen übertragen. `id` ist die interne Song-ID, `fileId` die Nextcloud-Datei-ID. `version` ist derzeit ein Speicher-ETag für den Index, noch kein verlässlicher Token für die spätere Editor-Konfliktprüfung.

Beispiel eines Imports (nur selbst erstellter Beispieltext):

```json
{"libraryId": 1, "filename": "Probe.cho", "content": "{title: Probe}\r\n{key: D}\r\n[D]Unser Test.\r\n"}
```

Der Originaltext wird unverändert geschrieben; UTF-8-BOM, Zeilenenden und unbekannte Direktiven bleiben erhalten. Keine Überschreibfunktion. Nach einem unterbrochenen Import zuerst aktualisieren: Die Datei kann bereits gespeichert sein, obwohl die Antwort den Browser nicht erreicht hat.

## Regeln und Fehler

Bibliotheksnamen: 1–100 Zeichen, keine Steuerzeichen. Das persönliche Wurzelverzeichnis darf nicht als Bibliothek verbunden werden. Verbinden und Importieren erfordern Lese- und Anlegerechte im Zielordner. Jeder Dateizugriff wird aus der aktuellen Benutzersicht aufgelöst; bekannte IDs ersetzen keine Freigabe.

Import: nichtleerer UTF-8-Text ohne Nullbytes, maximal 262.144 Bytes; Dateiname maximal 255 UTF-8-Bytes, ohne Pfadbestandteile, Steuerzeichen oder führenden Punkt. Endungen `.cho`, `.pro`, `.chordpro`, unabhängig von Großschreibung. Der ausgewählte Ordner enthält höchstens 1.000 direkte Einträge. Ungültige bestehende Dateien werden mit Warnung ausgelassen. Unterordner werden nicht rekursiv durchsucht.

Suche: maximal 200 Zeichen; Teilstring in Titel, Interpret, Tonart oder Dateiname, ohne Beachtung der Großschreibung. `%` ist ein normales Suchzeichen. `sort` ist `title` (Standard), `artist` oder `key`; bei gleichem Wert entscheidet die Song-ID.

| HTTP-Code | Bedeutung |
| --- | --- |
| 400 | Ungültige IDs, Felder, Namen oder Sortierung |
| 401 / 403 | Fehlende Anmeldung oder fehlende Berechtigung |
| 404 | Datei/Bibliothek fehlt oder ist aus dieser Benutzersicht nicht erreichbar |
| 409 | Namenskollision oder temporäre Dateisperre |
| 412 | Nextcloud lehnt fehlenden/ungültigen CSRF-Token ab |
| 413 | Datei- oder Verzeichnislimit überschritten |
| 422 | Leerer oder ungültiger ChordPro-Text |
| 503 | Speicher oder Index momentan nicht verfügbar |
| 507 | Speicherplatz reicht nicht aus / Schreiben nicht vollständig möglich |

Es gibt noch keine Endpunkte für Bearbeiten, Löschen, Export oder Setlisten. Offizielle Grundlagen: [OCS-Routing](https://docs.nextcloud.com/server/stable/developer_manual/basics/routing.html#ocs-routes), [Controller-Sicherheit](https://docs.nextcloud.com/server/stable/developer_manual/basics/controllers.html), [Dateisystem](https://docs.nextcloud.com/server/stable/developer_manual/basics/storage/filesystem.html).
