# Abhängigkeiten und Lizenzen

Der eigene Quellcode steht unter **AGPL-3.0-or-later**. Die vollständige AGPL v3 steht in `LICENSE`. Zur Laufzeit benötigt das PHP-Backend keine zusätzlichen Composer-Pakete; `nextcloud/ocp` und PHPUnit sind reine Entwicklungsabhängigkeiten.

Der Frontend-Build ermittelt die tatsächlich im JavaScript-Bundle enthaltenen npm-Pakete und erzeugt `THIRD_PARTY_LICENSES.txt` mit Versionen und vollständigen Lizenztexten. Das Entwicklungsarchiv enthält diese Datei. Paketversionen und Integritätswerte stehen in `package-lock.json`.

Die am 13.11.2026 gebündelten Pakete verwenden MIT, ISC, GPL-3.0-or-later, AGPL-3.0-or-later und die Alternative `(MPL-2.0 OR Apache-2.0)`. Für die Alternative wird Apache-2.0 verwendet. GPL v3 und AGPL v3 gestatten ihre gemeinsame Verwendung ausdrücklich in Abschnitt 13; MIT, ISC und Apache-2.0 lassen eine solche Kombination unter Einhaltung ihrer Hinweise zu. Die Originalhinweise werden beigefügt; die Lizenz der Banddateien wird dadurch nicht verändert.

Einige Nextcloud-Pakete veröffentlichen in npm keine Lizenzdatei. Bei der exakt angegebenen AGPL- bzw. GPL-Lizenz ergänzt der Build den entsprechenden vollständigen Standardtext aus `LICENSE` bzw. `LICENSES/GPL-3.0.txt`. Letzterer stammt aus dem installierten offiziellen Paket `@nextcloud/axios`. Die vorhandenen Copyright-Kommentare bleiben im Bundle erhalten. Bei einer unbekannten oder fehlenden Lizenz ohne entsprechende Vorlage bricht der Build ab.

Vor einer öffentlichen Veröffentlichung ist die Inventarisierung auf dem finalen Release erneut auszuführen. Aktuelle Sicherheitsbefunde sind separat in [VALIDATION.md](VALIDATION.md) dokumentiert.
