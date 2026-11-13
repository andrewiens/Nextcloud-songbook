# Vorbereitung einer späteren Veröffentlichung

Phase 1 erzeugt ein lokales Entwicklungsartefakt. Weder GitHub-Veröffentlichung noch App-Store-Einreichung oder produktive Installation sind durchgeführt oder automatisiert. `build-artifact.yml` besitzt nur lesenden Repository-Zugriff und lädt ein Workflow-Artefakt hoch; es erzeugt keinen öffentlichen Release.

## Vor einer Freigabe erforderlich

1. Das Repository [andrewiens/Nextcloud-songbook](https://github.com/andrewiens/Nextcloud-songbook) ist als Ziel hinterlegt; Quellcode-Veröffentlichung ausdrücklich freigeben lassen. Die vorläufige ID `songbook` im [Nextcloud App Store](https://apps.nextcloud.com/developer) prüfen und registrieren. Ein fehlender Store-Treffer ist kein Reservierungsnachweis. Die hinterlegten Repository-/Bugtracker-URLs vor der Einreichung auf öffentliche Erreichbarkeit prüfen.
2. MVP-Funktionen fertigstellen und Nextcloud-35-Installation, unterstützte PHP-Versionen, Datenbanksysteme, Berechtigungen, konkurrierende Bearbeitung und Tablet-Browser prüfen. Die Metadaten nur für ausreichend getestete Versionen freigeben.
3. App-Beschreibung, Changelog, Screenshot-Material ohne private Banddaten und Icon prüfen. Abhängigkeiten und gebündelte Lizenzhinweise inventarisieren. Eigener Code wird unter **AGPL-3.0-or-later** veröffentlicht; private Lieder sind keine Release-Inhalte.
4. Reproduzierbar aus den Lockdateien bauen. Das Entwicklungsarchiv verwendet eine feste Dateiliste und normalisierte Archiv-Zeitstempel. Für einen Store-Release wird zuerst ein sauberes finales App-Verzeichnis zusammengestellt.
5. Nach ausdrücklicher Freigabe einen privaten Schlüssel außerhalb des Repositories erzeugen und eine Zertifikatsanfrage für die festgelegte App-ID bei [nextcloud/app-certificate-requests](https://github.com/nextcloud/app-certificate-requests) einreichen. Nur die CSR ist öffentlich. Der Schlüssel bleibt privat; `.gitignore` ist zusätzlicher Schutz, kein Ersatz für sichere Schlüsselverwaltung.

## Zwei getrennte Signaturen

**Integrität des App-Verzeichnisses:** `occ integrity:sign-app` signiert die endgültigen Dateien mit dem Nextcloud-Zertifikat und dem privaten Schlüssel. Das Ergebnis ist `appinfo/signature.json`. Nach dieser Signatur dürfen die Dateien nicht mehr verändert werden. Dann wird das Verzeichnis `songbook/` als `.tar.gz` verpackt. Das Vorgehen ist in [Nextcloud Code Signing](https://docs.nextcloud.com/server/stable/developer_manual/app_publishing_maintenance/code_signing.html) beschrieben.

**Signatur des Release-Archivs:** Die Store-Einreichung benötigt zusätzlich die Signatur des fertigen herunterladbaren Archivs. Sie bezieht sich auf die Archivbytes, nicht auf die darin enthaltene `signature.json`. Nach einer Änderung des Archivs muss diese Signatur neu erstellt werden. Für die spätere Umsetzung das dann aktuelle [App-Store-Veröffentlichungsverfahren](https://nextcloudappstore.readthedocs.io/en/latest/developer.html) und den offiziellen [Release-Prozess](https://docs.nextcloud.com/server/stable/developer_manual/app_publishing_maintenance/release_process.html) verwenden.

Das vorhandene `scripts/build-dev-archive.py` signiert ausdrücklich **nicht** und darf nicht als fertiger Store-Release missverstanden werden. Ein späterer Signierungsworkflow benötigt geschützte Secrets, eine kontrollierte Release-Umgebung und eine Freigabe vor Veröffentlichung. Die Phasen-1-Workflows greifen auf keine Schlüssel zu.

Das signierte Archiv wird erst nach Freigabe öffentlich bereitgestellt und mit passender Signatur und Metadaten im App Store eingereicht. Danach werden Installation und Update aus dem Store in einer frischen Testinstanz geprüft. Bei Wolkensicher wird die App anschließend über den regulären Store installiert; die Möglichkeit einer manuellen Entwicklerinstallation ist unbekannt.

Quellenstand: **13.11.2026**. Weitere offizielle Grundlage: [App-Store-Regeln](https://docs.nextcloud.com/server/stable/developer_manual/app_publishing_maintenance/publishing.html).
