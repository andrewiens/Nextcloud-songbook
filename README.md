# Songbook

Native Nextcloud-App zur gemeinsamen Liedverwaltung einer Band. **Phase 1** enthält den App-Einstieg, eine Vue-3-Oberfläche mit den Bereichen **Lieder** und **Setlisten** sowie eine authentifizierte OCS-Status-API. Die beiden Bereiche sind absichtlich leer. Lieddateien, Editor, Bibliothekskonfiguration und Setlistenverwaltung folgen in den nächsten Phasen.

Das Entwicklungsziel ist **Nextcloud 35**. Die Versionsgrenze in `appinfo/info.xml` begrenzt die lokale Installation auf diese Hauptversion; sie ersetzt keine Freigabe für den Produktivbetrieb. Tatsächlich ausgeführte Prüfungen und verbleibende Einschränkungen stehen in [docs/VALIDATION.md](docs/VALIDATION.md).

## Lokaler Start mit Docker

Benötigt werden Docker mit Compose v2 oder neuer und für den HTTP-Integrationstest Python 3. Node, PHP und Composer können vollständig in den bereitgestellten Containern laufen. Alle folgenden Befehle werden **im Verzeichnis `songbook`** ausgeführt.

Die Umgebung verwendet ein eigenes Compose-Projekt `songbook-dev`, ein eigenes Datenvolume und standardmäßig `127.0.0.1:8081`. Eine bereits bestehende Nextcloud auf Port 8080 und deren Datenvolume werden dadurch nicht geändert. SQLite dient ausschließlich der lokalen Entwicklungsumgebung.

1. Lokale Konfiguration anlegen:

   ```sh
   cp .env.example .env
   ```

   In `.env` `NEXTCLOUD_ADMIN_USER` und ein eigenes `NEXTCLOUD_ADMIN_PASSWORD` setzen. Die Datei ist von Git ausgeschlossen. Dieses Passwort gilt nur für die neu angelegte lokale Testinstanz. `SONGBOOK_PORT` kann bei einem Portkonflikt geändert werden.

2. Abhängigkeiten installieren und Oberfläche bauen:

   ```sh
   docker compose run --rm node ci
   docker compose run --rm node run build
   docker compose run --rm composer install --no-interaction --prefer-dist
   ```

   `js/songbook-main.js`, `css/songbook-main.css` und die automatisch erzeugte Datei `THIRD_PARTY_LICENSES.txt` müssen vor dem Öffnen der App vorhanden sein. Composer enthält in Phase 1 nur Entwicklungswerkzeuge; zur Laufzeit lädt Nextcloud die PHP-Klassen selbst.

3. Nextcloud starten und Installation abwarten:

   ```sh
   docker compose up -d nextcloud
   docker compose logs -f nextcloud
   ```

   Sobald die Installation abgeschlossen ist, die Loganzeige mit Strg+C verlassen. Das beendet den Container nicht.

4. Versionsprüfung und App-Aktivierung:

   ```sh
   docker compose exec --user www-data nextcloud php occ status
   docker compose exec --user www-data nextcloud php occ app:enable songbook
   docker compose exec --user www-data nextcloud php occ config:system:set debug --type=boolean --value=true
   ```

   Die Ausgabe von `occ status` muss Nextcloud 35 bestätigen. **Keine erzwungene Aktivierung** auf anderen Hauptversionen verwenden.

5. [Lokale Nextcloud öffnen](http://127.0.0.1:8081), mit den lokalen Zugangsdaten anmelden und **Songbook** in der App-Navigation öffnen. Die direkte App-Adresse ist [Songbook](http://127.0.0.1:8081/index.php/apps/songbook/).

Die Compose-Datei bindet diesen Quellordner nach `/var/www/html/custom_apps/songbook` ein. Änderungen an PHP werden direkt verwendet; nach Frontend-Änderungen erneut `npm run build` ausführen. Für fortlaufendes Bauen kann der Build mit `npm run build -- --watch` laufen. Ein separater Frontend-Server ist nicht erforderlich. Der oben aktivierte Debug-Modus ist für diese lokale Entwicklungsinstanz vorgesehen; er verhindert störendes Asset-Caching bei Änderungen. In einer späteren Produktivinstanz bleibt Debug deaktiviert.

Stoppen ohne Datenverlust:

```sh
docker compose down
```

Das lokale Nextcloud-Volume bleibt bestehen. Ein geändertes Initialpasswort in `.env` setzt das Passwort einer bereits installierten Instanz nicht zurück. Für Passwortänderungen den regulären Nextcloud-Weg verwenden.

## Entwicklung und Tests

```sh
docker compose run --rm node run lint
docker compose run --rm node run typecheck
docker compose run --rm node test
docker compose run --rm node run build
docker compose run --rm composer validate --strict
docker compose run --rm composer lint
docker compose run --rm composer test
```

Die HTTP-Tests laufen gegen die oben gestartete und aktivierte **lokale** Instanz:

```sh
python3 tests/integration/test_http.py
```

Der Test verwendet die Zugangsdaten aus `.env`, legt vorübergehend zwei gewöhnliche Testkonten an und entfernt diese anschließend. Er prüft den authentifizierten API-Zugriff, abgewiesene anonyme Zugriffe, Assets und CSRF-Verhalten. Er ist nicht für eine produktive Instanz bestimmt. Die jeweilige Testsuite und ihre Grenzen sind in [docs/VALIDATION.md](docs/VALIDATION.md) dokumentiert.

Mit lokal installiertem Node 24, PHP 8.3–8.5 und Composer 2 können dieselben `npm`- und `composer`-Befehle ohne Docker-Präfix ausgeführt werden. Die PHP-Versionen entsprechen den geprüften offiziellen [Nextcloud-35-Systemanforderungen](https://docs.nextcloud.com/server/stable/admin_manual/installation/system_requirements.html).

Metadaten gegen das offizielle Schema validieren:

```sh
curl --fail --location https://apps.nextcloud.com/schema/apps/info.xsd --output /tmp/songbook-info.xsd
docker compose run --rm -v /tmp/songbook-info.xsd:/tmp/songbook-info.xsd:ro --entrypoint php composer scripts/validate-metadata.php /tmp/songbook-info.xsd
```

Der Schema-Download erfordert Internetzugriff. Das Skript akzeptiert eine lokal vorhandene XSD-Datei und verändert keine Metadaten. Der CI-Workflow führt diese Prüfung ebenfalls aus.

## API und Aufbau

Die API verwendet Nextcloud-Authentifizierung, dessen OCS-Controller und serverseitigen CSRF-Schutz. Details zu Anfrage, Antwort und Fehlern: [docs/API.md](docs/API.md). Es gibt in Phase 1 keine schreibenden Song- oder Setlisten-Endpunkte.

```text
songbook/
├── appinfo/              App-Metadaten und Seiten-/OCS-Routen
├── lib/
│   ├── AppInfo/          Nextcloud-Bootstrapping
│   ├── Controller/       HTML-Seite und Status-API
│   └── Service/          Statusdaten
├── src/                  Vue 3, TypeScript und API-Client
├── templates/            HTML-Einstieg und Asset-Einbindung
├── img/                  App-Icon
├── tests/
│   ├── php/              PHP-Unit-Tests
│   ├── frontend/         Frontend-Tests
│   └── integration/      Tests gegen echte lokale Nextcloud
├── scripts/              Metadatenprüfung und Entwicklungsarchiv
├── docs/                 Architektur, API, Prüfung und Release-Vorbereitung
├── .github/workflows/    Automatische Tests und manueller Artefakt-Build
├── compose.yaml          Isolierte lokale Nextcloud-35-Umgebung
├── composer.json         PHP-Entwicklungsabhängigkeiten
├── package.json          Frontend-Abhängigkeiten und Skripte
├── js/ und css/          Generierte Build-Ergebnisse (nicht in Git)
└── LICENSE               GNU AGPL v3; eigener Code unter AGPL-3.0-or-later
```

## Entwicklungsarchiv und Veröffentlichung

Nach erfolgreichem Build lässt sich ein **unsigniertes lokales Entwicklungsarchiv** erzeugen:

```sh
python3 scripts/build-dev-archive.py
```

Das Ergebnis liegt unter `dist/`. Das Skript verwendet eine ausdrückliche Liste von Laufzeitdateien und Dokumentation und nimmt weder `.env`, Entwicklungsabhängigkeiten, private Schlüssel noch Banddaten auf. Es veröffentlicht nichts. Der manuell auslösbare GitHub-Workflow erzeugt ebenfalls ausschließlich ein Entwicklungsartefakt.

`songbook` ist vorläufige App-ID. Das vom Auftraggeber angegebene Repository [andrewiens/Nextcloud-songbook](https://github.com/andrewiens/Nextcloud-songbook) und dessen Issue-Tracker sind in den Metadaten hinterlegt. Registrierung, Signierungszertifikat, Veröffentlichung des Quellcodes und App-Store-Freigabe stehen noch aus. Ein nicht erreichbarer Store-Eintrag beweist keine freie App-ID. Die Vorbereitung und die beiden unterschiedlichen Signaturen sind in [docs/RELEASE.md](docs/RELEASE.md) beschrieben.

Der App-Store-Zugriff bei Wolkensicher ist **keine** Zusage, dass dort dieses lokale Archiv manuell installiert werden darf. Eine Produktionsinstallation setzt den späteren veröffentlichten Store-Release und die entsprechenden Berechtigungen voraus.

## Nächster Schritt

Phase 2 verbindet eine Bibliothek mit einem ausdrücklich ausgewählten freigegebenen Ordner oder Gruppenordner. Zuerst werden Ordner-/Datei-Referenzen, tatsächliche Benutzerberechtigungen und das Index-Schema implementiert; anschließend folgen ChordPro-Import, Metadatenindex und Suche. Architekturentscheidungen und bewusst noch fehlende Funktionen: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

Die AGPL-Lizenz betrifft den App-Quellcode. Private Lieddateien, Setlisten und Nextcloud-Daten gehören nicht in dieses Repository.
