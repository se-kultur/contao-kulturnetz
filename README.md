# Contao KulturNetz

Contao-Erweiterung zur Vernetzung von Kulturakteur\*innen: Verwaltung von Künstler\*innen, Veranstalter\*innen, Locations und Veranstaltungen.

## Installation

```bash
composer require se-kultur/contao-kulturnetz
```

Nach der Installation oder einem Update das Schema und die Daten angleichen, per Konsole oder über die Datenbankmigration des Contao Managers (eine eigene Anwendung, kein Menüpunkt des Contao-Backends):

```bash
php vendor/bin/contao-console contao:migrate
```

Führt man die Migration im Contao Manager aus, sind dort die Löschvorgänge abzuwählen.

Ohne `--with-deletes`: Das Bundle verlangt nicht, dass Tabellen oder Spalten gelöscht werden, und die Website hält Archiv-Tabellen (z. B. `tl_sekevents_2024`) bewusst vor dem Löschen geschützt.

## Voraussetzungen und Versionen

| Bundle-Version | Contao | PHP |
| --- | --- | --- |
| 2.x | 5.7 LTS (`contao/core-bundle` `^5.7`) | >= 8.3 |
| 1.x | 4.13 LTS | >= 8.1 |

Das Bundle benötigt außer dem Contao-Kern direkt `doctrine/dbal` (Migrationen). Die Version wird gemeinsam mit dem Kern aufgelöst (`^3.7 || ^4.3`).

Für Contao 4.13 ist `1.0.0` der letzte passende Stand (`composer require se-kultur/contao-kulturnetz:^1.0`). Ab 2.0.0 gibt es keine Kompatibilität mit Contao 4.13 mehr. Bundle und Contao werden gemeinsam angehoben, eine Zwischenstufe gibt es nicht.

## Funktionen

- Künstler\*innen-Profile mit Detailansichten
- Veranstalter\*innen-Profile
- Location-Verwaltung mit Kartendarstellung (OpenStreetMap/Leaflet)
- Event-Verwaltung mit Datumsfunktionen
- SE-KulturTage-Events (Sonderformat)
- Follower-System zwischen Profilen
- Postings/Neuigkeiten

## Aufbau (Stand 2.0.0)

Das Bundle nutzt bewusst die klassischen Contao-Mechanismen, die Contao 5.7 weiterhin vollständig unterstützt. Ein Umbau auf Fragment-Controller und Twig ist eine mögliche Folgearbeit, kein Teil von 2.0.0.

- **Frontend-Module:** Klassen unter `src/Module/` erweitern `Contao\Module` und sind über `$GLOBALS['FE_MOD']` registriert. Die Templates liegen als `.html5` unter `src/Resources/contao/templates/`.
- **Backend:** Data Container (`src/Resources/contao/dca/`) mit `DC_Table`, Backend-Module in `config/config.php`, Bezeichnungen der Module in `languages/de/modules.php` und `languages/en/modules.php`. Die Freischaltungsmail (`sendmail`) liest Betreff und Text über den Eingabenamen des aktuellen Datensatzes und funktioniert damit auch bei „Mehrere bearbeiten“. Ohne Betreff oder Text wird keine Mail gesendet und eine Fehlermeldung ausgegeben.
- **Inhaltselement:** `TestElement` (Entwicklungsrest, im Backend auswählbar).
- **Request-Kontext:** `SeKultur\ContaoKulturnetzBundle\Helper\RequestContext` ersetzt die in Contao 5 entfernten Konstanten `TL_MODE` und `FE_USER_LOGGED_IN` (`isBackendRequest()`, `hasFrontendUser()`, `getFrontendUser()`, `getMemberId()`). Die Legacy-Klassen haben keine Dependency Injection, deshalb löst die Klasse die Dienste über `System::getContainer()` auf, wie die Legacy-Module des Contao-Kerns.
- **Bilder:** `Helper\ImageHelper` skaliert über den Dienst `contao.image.factory` (in Contao 5 anders als in 4.13; die Service-ID `contao.image.image_factory` gibt es nicht mehr).
- **Dienste:** `src/Resources/config/services.yaml` registriert ausschließlich `src/Migration/`. Neue Migrationsdateien dort werden ohne weitere Konfiguration erkannt. Ein Autowiring über das gesamte `src/` gibt es nicht, weil dort nicht registrierte Entwicklungsreste liegen.

## Migrationen

Beide Migrationen laufen bei `contao:migrate` automatisch und sind idempotent: Nach dem ersten Lauf melden sie nichts mehr.

| Migration | Zweck |
| --- | --- |
| „KulturNetz: Basic Entities in den Bundle-Tabellen umwandeln“ | Contao 4.13 hat beim Speichern Platzhalter wie `[nbsp]`, `[-]`, `[&]`, `[lt]` und `[gt]` erzeugt und erst beim Rendern ersetzt. Contao 5 ersetzt sie nicht mehr. Die Migration wandelt sie in den Textspalten der Bundle-Tabellen in echte HTML-Entities um. Sie läuft nur, solange die Contao-4-Spalten `tl_article.keywords` und `tl_theme.vars` noch existieren, also vor einem Lauf mit `--with-deletes`. Das Archiv `tl_sekevents_2024` bleibt unverändert. |
| „KulturNetz: Galerie-Reihenfolge von orderSRC nach multiSRC übertragen“ | Contao 5 kennt `eval.orderField` nicht mehr, die Galerie (`multiSRC`) ist selbst sortierbar. Die Migration schreibt die Reihenfolge aus `orderSRC` nach `multiSRC`. Die Spalte `orderSRC` bleibt bestehen und darf nicht gelöscht werden: Die Templates und die Ajax-Skripte der Website lesen sie weiterhin, und das Backend hält sie beim Speichern gleich `multiSRC`. |

Hinweis: Ältere Versionen in `tl_version` und `tl_undo` enthalten die Platzhalter weiter. Wer eine alte Version im Backend wiederherstellt, bringt `[nbsp]` usw. zurück, und Contao 5 gibt sie dann sichtbar aus. Das entspricht dem Verhalten des Contao-Kerns für seine eigenen Tabellen.

## Lokale Entwicklung

Das Bundle liegt als Geschwisterordner neben der Website (`../contao-kulturnetz-bundle`). Die Website bindet es bis zur Veröffentlichung über ein Composer-`path`-Repository ein, die Docker-Umgebung der Website mountet den Ordner. Änderungen am Bundle wirken sofort, danach `cache:clear`. Siehe README der Website.

PHP-Dateien prüfen:

```bash
find src -name '*.php' -print0 | xargs -0 -n1 php -l
```

## Gefördert vom Land Schleswig-Holstein

Die Entwicklung wurde vom Land Schleswig-Holstein finanziell gefördert. Die Erweiterung steht unter der GNU General Public License v3.0 als Open Source zur freien Verfügung.

## Lizenz

GPL-3.0-or-later
