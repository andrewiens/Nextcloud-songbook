# Phase 2: Gemeinsame Liedbibliothek

Diese Entwicklungsstufe verbindet Songbook mit vorhandenen Nextcloud-Ordnern. Liedtexte bleiben ausschließlich in den ChordPro-Dateien. Die Datenbank enthält Bibliotheksreferenzen und einen erneuerbaren Metadatenindex.

## Benutzung

1. In Nextcloud Files einen Ordner für die Band anlegen oder einen bestehenden gemeinsamen Ordner verwenden. Die Freigabe an Bandmitglieder erfolgt weiterhin in Nextcloud Files; auch Gruppenordner können als Speicher dienen.
2. In Songbook **Bibliothekseinstellungen** öffnen, den Ordner auswählen, einen Namen vergeben und verbinden. Songbook legt keinen persönlichen Standardordner an und verändert keine Freigaben.
3. Bandmitglieder mit Zugriff auf diesen Ordner sehen dieselbe Bibliothek. Zum Importieren benötigen sie im Ordner die Berechtigung, Dateien anzulegen. Personen mit Leserechten können die Bibliothek durchsuchen und Liedquellen öffnen.
4. Unter **Lieder** die Bibliothek auswählen. Über den Import eine UTF-8-Datei mit Endung `.cho`, `.pro` oder `.chordpro` hinzufügen. Namenskollisionen werden abgewiesen; bestehende Dateien werden nicht überschrieben.
5. Nach Änderungen in Files die Liste aktualisieren. Titel, Interpret und Tonart werden aus den Dateien neu eingelesen, wenn sich deren Dateiversion geändert hat. Eine Umbenennung innerhalb des Ordners erhält die interne Song-ID.

In Phase 2 werden die **direkt im ausgewählten Ordner** liegenden Dateien betrachtet. Für einen Ordner `Band/Songbook/Songs` also genau diesen Ordner auswählen. Unterordner werden nicht automatisch rekursiv durchsucht. Pro Verzeichnis sind höchstens 1.000 Einträge und pro Song 256 KiB vorgesehen. Ungültige oder zu große Songdateien werden mit einer Warnung ausgelassen; die Originaldateien bleiben erhalten.

Der geöffnete Song zeigt in dieser Phase den originalen ChordPro-Quelltext. Die formatierte Akkorddarstellung und der integrierte Editor sind Gegenstand von Phase 3. Setlisten folgen in Phase 4.

## Datenmodell und Berechtigungen

`songbook_libraries` speichert Name, Nextcloud-Ordner-ID, Ersteller und Erstellungszeit. Eine Ordner-ID kann nur einmal verknüpft werden. `songbook_songs` speichert eine eigene Song-ID, Bibliotheks-ID, Nextcloud-Datei-ID, Dateinamen, Titel, Interpret, Tonart, Dateiversion und Aktualisierungszeit. Song-ID und Datei-ID sind verschiedene Schlüssel.

Der Index entscheidet niemals über Zugriffsrechte. Jede Anfrage löst den Bibliotheksordner aus der Dateisystemsicht des angemeldeten Benutzers auf. Beim Auflisten und Öffnen werden zusätzlich die Dateirechte und die aktuelle Zugehörigkeit zum Ordner geprüft. Ein alter Indexeintrag oder eine bekannte Song-ID verschafft keinen Zugriff nach Entzug einer Freigabe.

Der Abgleich vergleicht die tatsächlichen Dateiversionen mit dem Index. Unveränderte Dateien müssen nicht erneut als Text gelesen werden. Metadaten ungültiger oder nicht mehr sichtbarer Dateien werden nicht als Liedliste ausgegeben. Ein eingeschränkter Leser darf den gemeinsamen Index nicht anhand seiner eigenen unvollständigen Sicht bereinigen.

## ChordPro-Teilmenge

Für den Index werden `{title: ...}`, die Kurzform `{t: ...}`, `{artist: ...}` und `{key: ...}` erkannt. Fehlt ein Titel, dient der Dateiname als Ersatz. Die jeweils erste nichtleere Angabe wird verwendet. Die Verarbeitung akzeptiert UTF-8 einschließlich BOM und verschiedener Zeilenenden; diese bleiben im Original erhalten. Metadatenlängen werden für den Index begrenzt. Unbekannte Direktiven und Akkorde werden nicht verändert oder entfernt.

## Lokales Upgrade von Phase 1

Die folgenden Befehle im Projektverzeichnis ausführen:

```sh
docker compose run --rm node run build
docker compose exec -T --user www-data nextcloud php occ upgrade
docker compose exec -T --user www-data nextcloud php occ app:enable songbook
```

Die Migration legt die beiden Songbook-Tabellen an. Es werden dabei keine Lieddateien erzeugt oder verschoben. Vorhandene `.env`-Dateien nicht durch `.env.example` ersetzen. Die produktive Instanz bei Wolkensicher gehört nicht zu diesem lokalen Upgrade.

Offizielle Grundlagen: [Nextcloud-Dateisystem](https://docs.nextcloud.com/server/stable/developer_manual/basics/storage/filesystem.html), [Datenbankzugriff](https://docs.nextcloud.com/server/stable/developer_manual/basics/storage/database.html), [Migrationen](https://docs.nextcloud.com/server/stable/developer_manual/basics/storage/migrations.html), [ChordPro-Einführung](https://www.chordpro.org/chordpro/chordpro-introduction/). Stand: 13.11.2026.
