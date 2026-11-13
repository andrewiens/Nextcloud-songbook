# Architektur und Phasengrenzen

## Implementierte Grundlage

Songbook läuft als native Nextcloud-App im bestehenden PHP-Prozess. Nextcloud stellt Authentifizierung, Dependency Injection, Navigation und API-Middleware bereit. Die Vue-3-Anwendung wird als lokale JavaScript-/CSS-Datei ausgeliefert. Es gibt keinen separaten Anwendungsserver, keine zweite Benutzerverwaltung und keinen externen Dienst für Banddaten.

Die Oberfläche trennt Navigation und API-Zugriff. Sie verwendet Nextcloud-Komponenten und dessen Theme-Variablen. Der erste reale API-Aufruf liest Status und Funktionsumfang vom Backend. Fehler bleiben sichtbar und sind erneut abrufbar; es werden keine Daten als erfolgreich geladen dargestellt, wenn die Anfrage scheitert.

Phase 1 speichert keine Lieder oder Setlisten und verändert keine Nextcloud-Dateien. Es gibt deshalb noch keine Song-Migration, Dateisystem-Ereignisregistrierung oder Bibliotheksberechtigung, deren Funktion vorgetäuscht werden könnte.

## Ziel für Phase 2

1. Eine Bibliothek wird mit einem vom Benutzer bewusst ausgewählten, bereits freigegebenen Nextcloud-Ordner oder Gruppenordner verbunden. Es wird kein persönlicher Entwickler- oder Administratorordner automatisch zur gemeinsamen Bibliothek.
2. Songbook löst Ordner und Dateien immer über die Dateisystemsicht des angemeldeten Benutzers auf. Lesen und Bearbeiten werden serverseitig geprüft. Bibliotheks- und Setlisten-IDs sind keine Zugriffsberechtigung.
3. ChordPro-Dateien bilden die führende Quelle. Die Datenbank speichert nur Referenzen und Metadaten. Interne Song-ID und Nextcloud-Datei-ID werden getrennt behandelt; stabile Datei-IDs helfen bei Umbenennungen.
4. Migrationen führen Bibliotheken, Songs, Setlisten und Setlistenpositionen ein. Fremdbeziehungen und Löschregeln werden über Nextclouds portable Datenbankabstraktion umgesetzt. Setlistenpositionen besitzen eigene IDs, damit dasselbe Lied mehrfach vorkommen darf.
5. Ein begrenzter Ordnerabgleich baut den Metadatenindex auf. Offizielle Dateisystemereignisse markieren betroffene Einträge zur Aktualisierung; ergänzende ressourcenschonende Abgleiche fangen externe Änderungen auf. Die konkreten Event-Klassen werden vor dieser Implementierung gegen Nextcloud 35 geprüft.
6. Schreiboperationen vergleichen eine Dateiversion/ETag und behandeln Konflikte ausdrücklich. Das Originalformat bleibt erhalten; unbekannte ChordPro-Direktiven werden beim späteren Editor nicht gelöscht.

Importgrenzen, UTF-8-Prüfung, Dateinamenprüfung, Sperren, Konfliktantworten und Mehrbenutzerfälle werden zusammen mit den jeweiligen schreibenden Endpunkten implementiert und getestet. Diese Eigenschaften werden für Phase 1 nicht als bereits vorhanden zugesichert.

## Weitere Phasen

Phase 3 ergänzt einen definierten ChordPro-Parser, Quelltexteditor, sichere Live-Vorschau und verlustfreien Export. Phase 4 ergänzt referenzbasierte Setlisten mit Reihenfolge und Notizen. Phase 5 ergänzt den ablenkungsarmen Auftrittsmodus. Phase 6 umfasst Freigabetests, Sicherheitsprüfung und Store-Veröffentlichung.

Eine spätere PWA muss Nextclouds URL-Basis, Authentifizierung und mögliche Service-Worker-Konflikte berücksichtigen. Ein Startbildschirm-Link ist kein Offline-Modus. Deshalb registriert Phase 1 keinen Service Worker und speichert keine Lied- oder Anmeldedaten im Browser. Transponierung und Offline-Synchronisation bleiben getrennte spätere Funktionen.

## Versions- und API-Grundlagen

Am **13.11.2026** wurden die offiziellen Nextcloud-35-Dokumentationen zu [Systemanforderungen](https://docs.nextcloud.com/server/stable/admin_manual/installation/system_requirements.html), [App-Aufbau](https://docs.nextcloud.com/server/stable/developer_manual/app_development/intro.html), [Bootstrapping](https://docs.nextcloud.com/server/stable/developer_manual/app_development/bootstrap.html), [Metadaten](https://docs.nextcloud.com/server/stable/developer_manual/app_development/info.html), [Routing](https://docs.nextcloud.com/server/stable/developer_manual/basics/routing.html) und [Controllern](https://docs.nextcloud.com/server/stable/developer_manual/basics/controllers.html) konsultiert. PHP 8.3, 8.4 und 8.5 gehören zur NC35-Unterstützung. Vue wird in der App gebündelt; es wird keine interne globale Vue-Instanz des Servers vorausgesetzt. Die exakten installierten npm- und Composer-Versionen werden in den Lockdateien festgehalten.

Die `stable`-URLs sind bewegliche Dokumentationslinks. Vor einer späteren Veröffentlichung Versionsstand und APIs erneut prüfen; der tatsächlich getestete Stand steht in `VALIDATION.md`.
