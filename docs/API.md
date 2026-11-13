# API v1 – Phase 1

Basis: `/ocs/v2.php/apps/songbook/api/v1/`. Die erste Route ist `GET status`. Die OCS-Routen stehen separat unter dem Schlüssel `ocs` in `appinfo/routes.php`; die HTML-Seite verwendet die normalen `routes`.

## Status abfragen

```http
GET /ocs/v2.php/apps/songbook/api/v1/status?format=json
Accept: application/json
OCS-APIRequest: true
```

Der Aufruf erfordert eine angemeldete Nextcloud-Sitzung oder unterstützte Nextcloud-API-Authentifizierung. Im Browser übernimmt `@nextcloud/axios` den Request-Token; `@nextcloud/router` berücksichtigt das Installations-Unterverzeichnis. Das Attribut `NoAdminRequired` erlaubt gewöhnliche Benutzer, aber keinen anonymen Zugriff. Der API-Controller deaktiviert CSRF-Prüfungen nicht.

Erfolgreiche JSON-Antwort (die Version stammt aus der installierten App):

```json
{
  "ocs": {
    "meta": { "status": "ok", "statuscode": 200, "message": "OK" },
    "data": {
      "app": "songbook",
      "version": "0.1.0-dev",
      "apiVersion": 1,
      "capabilities": { "songLibrary": false, "setlists": false }
    }
  }
}
```

`capabilities` zeigt implementierte Funktionen an. Die leeren Phase-1-Bereiche liefern keine vorgetäuschten Lieddaten. Die Antwort enthält keine Benutzernamen, Dateien oder privaten Bibliotheksinformationen.

Ein manueller Aufruf kann das Passwort interaktiv abfragen, damit es nicht im Shell-Verlauf landet:

```sh
curl --user songbook-admin \
  --header 'OCS-APIRequest: true' \
  --header 'Accept: application/json' \
  'http://127.0.0.1:8081/ocs/v2.php/apps/songbook/api/v1/status?format=json'
```

Benutzernamen und Port an `.env` anpassen. Für externe Clients vorzugsweise ein Nextcloud-App-Passwort verwenden. API-Authentifizierung, HTTP-Fehlercode und OCS-Fehlerhülle werden durch Nextcloud bestimmt; fehlende Anmeldung oder ungültige Tokens dürfen nicht als erfolgreiche Statusantwort behandelt werden. Der Frontend-Client prüft sowohl Transport- als auch OCS-Fehler und bietet erneutes Laden an.

## Weitere Routen

`GET /index.php/apps/songbook/` rendert die authentifizierte App-Seite. Nur diese navigierbare HTML-Seite trägt `NoCSRFRequired`, damit Lesezeichen und die Nextcloud-Navigation funktionieren.

Song-CRUD, Bibliotheks-Endpunkte und Setlisten-Endpunkte sind in Phase 1 noch nicht verfügbar. Es gibt keine Phase-1-Mutation und keine eigene Datenbankmigration. Für Phase 2 werden Dateioperationen pro Anfrage über die Sicht des angemeldeten Benutzers autorisiert; Datei-IDs allein sind keine Berechtigung.

Offizielle Grundlagen, geprüft am 13.11.2026: [OCS-Routing](https://docs.nextcloud.com/server/stable/developer_manual/basics/routing.html#ocs-routes), [Controller und Sicherheitsattribute](https://docs.nextcloud.com/server/stable/developer_manual/basics/controllers.html).
