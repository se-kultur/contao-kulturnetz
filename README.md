# Contao KulturNetz

Contao-Erweiterung zur Vernetzung von Kulturakteur\*innen: Profile von Künstler\*innen, Veranstalter\*innen und Locations, Veranstaltungen des KulturNetzes und der SE-KulturTage, Folgen und Beiträge, Frontend-Formulare für Mitglieder und Exporte für die Redaktion.

## Stand und Versionen

| Bundle-Version | Contao | PHP | Status |
| --- | --- | --- | --- |
| 2.0.0 | 5.7 LTS (`contao/core-bundle` `^5.7`) | >= 8.3 | in Arbeit, **noch nicht veröffentlicht** (kein Tag) |
| 1.0.0 | 4.13 LTS | >= 8.1 | letzter Stand für Contao 4.13 |

Version 2.0.0 wird erst nach der Testphase der Website getaggt (Go-live-Checkliste, Abschnitt 1.4). Bis dahin bindet die Website das Bundle über ein Composer-`path`-Repository aus dem Geschwisterordner `../contao-kulturnetz-bundle` ein (siehe README der Website). Eine Zwischenstufe zwischen 1.x und 2.x gibt es nicht: Bundle und Contao werden gemeinsam angehoben.

Der Stand 2.0.0 ist auf Contao 5.7 ohne Legacy-Pfade gebaut: Fragment-Controller mit Twig statt `Contao\Module` und `.html5`, Services mit Autowiring statt statischer Helfer, Doctrine DBAL statt `$this->Database`, DCA-Callbacks als Services, Routen statt Skripten unter `files/`. Was sich in 5.7 nicht ablösen lässt, steht unter „Contao-6-Hinweise“.

## Installation

```bash
composer require se-kultur/contao-kulturnetz
php vendor/bin/contao-console contao:migrate
```

`contao:migrate` ohne `--with-deletes` gleicht Schema und Daten an (Migrationen siehe unten). Spalten oder Tabellen, die nicht mehr im DCA stehen, löscht nur ein bewusster Lauf mit `--with-deletes` nach Prüfung des Dry-Runs (`contao:migrate --dry-run --with-deletes`) und nach einem vollständigen Dump. Im Contao Manager sind die Löschvorgänge entsprechend abzuwählen.

Die Abhängigkeiten stehen vollständig in `composer.json` (u. a. Symfony 7.4, Twig 3, Doctrine DBAL, Symfony Mailer, `tecnickcom/tc-lib-pdf` für den PDF-Export).

## Aufbau

```
config/
  services.yaml          Services verzeichnisbasiert, Autowiring und Autokonfiguration
  prepend/*.yaml         Vorgaben für andere Bundles (Bildgrößen, Backend-CSS)
contao/
  config/config.php      nur $GLOBALS['TL_MODELS'] und $GLOBALS['BE_MOD'] (Restpunkt R14)
  dca/                   Data Container der Bundle-Tabellen, tl_member, tl_page
  languages/{de,en}/     Sprachdateien
  templates/             Twig-Templates (.twig-root, siehe unten)
public/                  Backend-Assets (CSS, Karte, Mitgliederinfo, Symbole), per assets:install unter bundles/contaokulturnetz/
src/
  ContaoKulturnetzBundle.php   AbstractBundle mit semantischer Konfiguration "kulturnetz"
  ContaoManager/Plugin.php     Bundle- und Routing-Plugin (Attribut-Routen aus src/Controller/)
  Config/                      KulturnetzConfig (typisierter Zugriff auf die Konfiguration)
  Controller/FrontendModule/   27 Frontend-Module als Fragment-Controller (Profile, Event, Posting)
  Controller/Endpoint/         Frontend-Routen für Mitglieder (Media, Social, Write, Search)
  Controller/Backend/          Backend-Exporte (CSV, PDF)
  EventListener/DataContainer/ DCA-Callbacks als Services (#[AsCallback])
  EventListener/Member/        Anmeldung per E-Mail-Adresse (Ersatz für terminal42/contao-mailusername)
  Migration/                   Datenmigrationen (siehe unten)
  Models/                      Contao-Models ohne eigene Logik
  Security/, Http/             CSRF-Prüfung, Besitzprüfung, JSON-Fehlerantworten
  Service/                     Fachlogik (Profile, Event, Media, Gallery, Social, Write, Search, Export, Mail, Text, Image, Page, Member)
  Twig/                        Twig-Funktionen des Bundles
```

Alle Klassen unter `src/` sind Services, ausgenommen die Bundle-Klasse, das Manager-Plugin, Wertobjekte unter `**/Value/` und die Models. Neue Klassen brauchen keinen Eintrag in `config/services.yaml`; Contao- und Symfony-Attribute (`AsFrontendModule`, `AsCallback`, `AsHook`, `AsEventListener`, `Route`) und `MigrationInterface` werden über die Autokonfiguration erkannt.

### Templates

`contao/templates/.twig-root` macht den Ordner zur Twig-Wurzel; alle Templates sind als `@Contao/...` erreichbar und in der Website per gleichnamiger Datei unter `templates/` überschreibbar.

- **Module:** `frontend_module/kulturnetz_<name>.html.twig`. Varianten für das Feld `customTpl` müssen als Unterordner mit dem Namen des Modul-Templates angelegt werden (z. B. `frontend_module/kulturnetz_profile_list/kompakt.html.twig`).
- **Partials:** `kulturnetz/<bereich>/_<name>.html.twig` mit den Bereichen `media` (Uploader, Galerie), `form` (Formularrahmen, Editor, Hilfekasten, Haftungshinweis), `social` (Reiter, Folgen-Dialog, Beiträge), `profile` und `event`. Partials erscheinen nie in der Template-Auswahl.
- **Backend:** `backend/` (Karte, Mitgliederinfo).

Regeln: Autoescape, HTML-Felder nur über `sanitize_html('contao')`, keine Inline-Skripte. Skripte und Stile kommen aus dem Theme-Build der Website (Asset-Paket `se_kultur_theme`) und werden per `{% add %}` mit `defer` eingebunden.

## Konfiguration `kulturnetz`

Alle Werte haben Standardwerte (die bisherigen Werte der Website), eine Konfiguration ist nur für Abweichungen nötig. Vollständige Referenz: `php vendor/bin/contao-console config:dump-reference kulturnetz`.

```yaml
# config/config.yaml der Website (Beispiel, Standardwerte)
kulturnetz:
    pages:                       # Seiten per Alias, nie per ID
        own_profiles: eigene-profile
        own_events: veranstaltungen
        terms: teilnahmebedingungen
        signup: sekultur-event-anlegen      # steuert die Sichtbarkeit der Anmeldelinks der SE-KulturTage
        types:
            artists:   { detail: detailansicht-kuenstlerprofil, edit: kuenstlerprofil-bearbeiten, add: kuenstlerprofil-anlegen, list: kuenstler-innen }
            # hosts, locations, events, sekevents analog
    teaser:                      # Übersichtsseite mit gekürzten Listen
        page: se-kulturnetz
        profile_limit: 3
        posting_limit: 5
    limits:
        profile_directory: 100
        posting_feed: 50
        member_feed: 25
        profile_postings: 25
        event_directory: 0       # 0 = unbegrenzt
        sekevent_directory: 200
    sample_profiles: { artists: 17, hosts: 7, locations: 16 }
    kulturrouten:
        url: 'https://se-kultur.de/kulturrouten.html'
        events: { 83: 1, 65: 1 }   # Veranstaltungs-ID (tl_sekevents) => Nummer der KulturRoute
    asset_package: se_kultur_theme
    mail:
        sender_address: noreply@se-kultur.de
        sender_name: SE-Kultur.de
        editorial_recipient: '%env(KULTURNETZ_MAIL_EDITORIAL_RECIPIENT)%'
        editorial_recipient_name: SE-Kultur.de Admin
        editorial_bcc: '%env(KULTURNETZ_MAIL_EDITORIAL_BCC)%'
    upload:
        max_file_size: 0         # 0 = es gelten die PHP-Grenzen
        mime_types: [image/jpeg, image/png]
```

### Umgebungsvariablen

| Variable | Wirkung | Standard |
| --- | --- | --- |
| `MAILER_DSN` | Versand aller Mails (Symfony Mailer): Redaktionsmails, Freischaltungsmails, Registrierung | ohne Wert kein funktionierender Versand; lokal und auf Staging `null://null` |
| `KULTURNETZ_MAIL_EDITORIAL_RECIPIENT` | Empfänger der Redaktionsmail bei neuen Profilen und Veranstaltungen | `info@se-kultur.de`; ein leerer Wert schaltet die Redaktionsmail ab |
| `KULTURNETZ_MAIL_EDITORIAL_BCC` | Blindkopien der Redaktionsmail, mehrere Adressen mit Komma | leer |

Persönliche Adressen gehören nur in die `.env.local` der jeweiligen Instanz, nie in den Code.

## Frontend-Module

Die Modultypen (`tl_module.type`) sind dieselben wie in 1.x, bestehende Module laufen ohne Migration weiter. Alle stehen in der Gruppe „eProjekt“ der Modulauswahl.

| Typen | Controller (`src/Controller/FrontendModule/`) | Template |
| --- | --- | --- |
| `ModuleArtist`, `ModuleHost`, `ModuleLocation` | `Profile/*DetailController` | `kulturnetz_{artist,host,location}_detail` |
| `ModuleArtistAdd`/`Edit`, `ModuleHostAdd`/`Edit`, `ModuleLocationAdd`/`Edit` | `Profile/*AddController`, `*EditController` | `kulturnetz_{artist,host,location}_form` |
| `ModuleArtistsList`, `ModuleHostsList`, `ModuleLocationsList` (eigene Profile) | `Profile/*OwnListController` | `kulturnetz_profile_list` |
| `ModuleArtistsListAll`, `ModuleHostsListAll`, `ModuleLocationsListAll` (Verzeichnisse) | `Profile/*DirectoryController` | `kulturnetz_profile_list` |
| `ModuleEvent`, `ModuleSekEvent` | `Event/EventDetailController`, `SekEventDetailController` | `kulturnetz_event_detail`, `kulturnetz_sekevent_detail` |
| `ModuleEventAdd`/`Edit`, `ModuleSekEventAdd`/`Edit` | `Event/*AddController`, `*EditController` | `kulturnetz_event_form`, `kulturnetz_sekevent_form` |
| `ModuleEventsList`, `ModuleSekEventsList` (eigene Veranstaltungen) | `Event/*OwnListController` | `kulturnetz_event_list` |
| `ModuleEventsListAll`, `ModuleSekEventsListAll` (Verzeichnisse mit Filter) | `Event/*DirectoryController` | `kulturnetz_event_directory`, `kulturnetz_sekevent_directory` |
| `ModulePostingsList` (eigener Feed), `ModulePostingsListAll` (öffentlicher Feed) | `Posting/OwnFeedController`, `PublicFeedController` | `kulturnetz_posting_feed` |

Detailseiten lesen den Alias aus `auto_item` über `Service\Page\AutoItemResolver` (Restpunkt R17). Seiten-URLs entstehen über `Service\Page\PageUrlResolver` aus den Seiten-Aliasen der Konfiguration, Bilder über benannte Bildgrößen `kulturnetz_*` (`config/prepend/image_sizes.yaml`) und `Service\Image\FigureFactory`.

## Routen und Sicherheit

### Frontend (Mitglieder)

| Route | Pfad | Methode | Zugriff |
| --- | --- | --- | --- |
| `kulturnetz_datei_hochladen`, `_bearbeiten`, `_abrufen`, `_loeschen` | `/_kulturnetz/dateien/{hochladen,bearbeiten,abrufen,loeschen}` | POST | Mitglied |
| `kulturnetz_profil_anlegen`, `kulturnetz_profil_aendern` | `/_kulturnetz/{typ}/{anlegen,aendern}` (`artists`, `hosts`, `locations`, `events`, `sekevents`) | POST | Mitglied |
| `kulturnetz_beitrag` | `/_kulturnetz/beitraege` | POST | Mitglied |
| `kulturnetz_folgen` | `/_kulturnetz/folgen` | POST | Mitglied |
| `kulturnetz_suche_profile` | `/_kulturnetz/suche/profile/{typ}` | GET | Mitglied |
| `kulturnetz_suche_events`, `kulturnetz_suche_sekevents` | `/_kulturnetz/suche/{veranstaltungen,sekultur-events}` | GET | öffentlich, nur freigeschaltete Einträge |

Prüfreihenfolge der schreibenden Routen:

1. Mitglied aus der Sitzung (nie aus dem Formular), sonst **401**.
2. `REQUEST_TOKEN` (oder Header `X-CSRF-Token`) über `Security\CsrfRequestValidator`, sonst **403**.
3. Typ und Art gegen eine feste Liste, sonst **422**.
4. Datensatz bzw. Datei vorhanden, sonst **404**.
5. Besitz, Mitgliedsordner (`homeDir`) und Schreibschutz (`readonly`) aus der Datenbank (`Security\OwnershipChecker`), sonst **403**.

Ungültige Eingaben liefern **422** mit den betroffenen Feldern. Erfolgsantworten haben dasselbe Format wie die früheren Skripte, Fehler sind JSON (`Http\JsonErrorResponse`). Geschrieben wird nur eine Feldliste je Typ, über DBAL mit gebundenen Parametern, Text im Contao-Speicherformat (`Service\Text\TextNormalizer`), HTML über den Contao-HTML-Sanitizer. Dateien laufen ausschließlich über das Contao-Dateisystem (`contao.filesystem.virtual.files`), zulässig sind JPEG und PNG mit Inhaltsprüfung. Die Endpunkt-URLs und das Token stehen als `data-*`-Attribute im Markup, nie fest im JavaScript.

### Backend (Exporte)

| Route | Pfad | Ausgabe |
| --- | --- | --- |
| `kulturnetz_export_artists_csv` | `/contao/kulturnetz/export/kuenstler.csv` | Künstler\*innen |
| `kulturnetz_export_hosts_csv` | `/contao/kulturnetz/export/veranstalter.csv` | Veranstalter\*innen |
| `kulturnetz_export_locations_csv` | `/contao/kulturnetz/export/locations.csv` | Locations |
| `kulturnetz_export_members_csv` | `/contao/kulturnetz/export/mitglieder.csv` | Mitglieder ohne Passwort, Sitzungs- und Sicherheitsfelder |
| `kulturnetz_export_sekevents_csv`, `_pdf` | `/contao/kulturnetz/export/sekultur-events.{csv,pdf}` | Veranstaltungen der SE-KulturTage |

Die Exporte verlangen eine Backend-Anmeldung (sonst Weiterleitung zur Anmeldung) und das Recht auf das jeweilige Backend-Modul (sonst 403). Die Redaktion erreicht sie über die Schaltflächen „CSV-Download“ und „PDF-Download“ in den Listen (globale Operationen mit `route`). CSV: UTF-8 mit BOM, Semikolon, Dateiname mit Datum wie bisher.

## Backend

- **Module:** Gruppe „KulturNetz“ mit `Kuenstler`, `- Postings`, `Veranstalter`, `Locations`, `Veranstaltungen` sowie Gruppe „KulturTage“ mit `Events`. Die Schlüssel stehen in den `do=`-URLs und bleiben unverändert.
- **Callbacks:** Services unter `src/EventListener/DataContainer/` (Alias, Listenbeschriftung, Spaltenauswahl, Freischaltungsmail, Mitgliederinfo, Karte). Sie nutzen DBAL, `$dc->getCurrentRecord()`, den `RequestStack` und Twig.
- **Karte und Mitgliederinfo:** Twig-Templates unter `contao/templates/backend/`, Skripte und Stile unter `public/`, eingebunden über `contao.backend.custom_css` (`config/prepend/backend.yaml`). Leaflet kommt lokal aus dem Theme-Build der Website.
- **Mitglieder:** Anmeldung per E-Mail-Adresse. `EventListener\Member\MailUsernameListener` setzt den Benutzernamen gleich der E-Mail-Adresse (Registrierung, Backend, Persönliche Daten); er bleibt untätig, solange `terminal42/contao-mailusername` installiert ist.

## Migrationen

Alle Migrationen laufen bei `contao:migrate` automatisch, sind idempotent (ein zweiter Lauf meldet nichts mehr) und auf dem Live-Datenbestand erprobt.

| Migration | Zweck |
| --- | --- |
| „KulturNetz: Basic Entities in den Bundle-Tabellen umwandeln“ (`BasicEntitiesMigration`) | Contao 4.13 speicherte Platzhalter wie `[nbsp]` und `[&]`, Contao 5 ersetzt sie nicht mehr. Läuft nur vor der Bereinigung mit `--with-deletes` (erkennt den 4.13-Stand an `tl_article.keywords` und `tl_theme.vars`). |
| „KulturNetz: Galerie-Reihenfolge von orderSRC nach multiSRC übertragen“ (`GalleryOrderMigration`) | Contao 5 kennt `eval.orderField` nicht mehr; die Reihenfolge steht danach nur in `multiSRC`. Die Spalte `orderSRC` entfällt bei der bewussten Bereinigung (`--with-deletes`), danach überspringt die Migration die Tabellen. |
| „KulturNetz: Preise der Veranstaltungen mit "€" statt "u20ac"“ (`Price\PriceCurrencyMigration`) | Die früheren Skripte haben das Euro-Zeichen als `u20ac` gespeichert. |
| „KulturNetz: Textkodierung der Bundle-Tabellen auf das Contao-Format bringen“ (`Text\BundleTextEncodingMigration`) | Überführt benannte HTML-Entities der früheren Skripte (`&ouml;` usw.) in das Speicherformat, das Contao 5.7 selbst schreibt. HTML-Felder bleiben unberührt. |

Archivtabellen `tl_sekevents_<Jahr>` (z. B. `tl_sekevents_2024`) berühren die Migrationen nicht; die Website schützt sie per `doctrine.dbal.schema_filter` vor dem Schemaabgleich.

Ältere Stände in `tl_version` und `tl_undo` enthalten das alte Format weiter. Wer eine alte Version im Backend wiederherstellt, bringt es zurück (wie beim Contao-Kern für seine eigenen Tabellen).

## Contao-6-Hinweise

Der Stand erzeugt im Dev-Modus keine Deprecation aus eigenem Code, mit Ausnahme der unten genannten offenen Punkte. Beim Umstieg auf Contao 6 sind diese Stellen zu prüfen (Nummern aus der Restpunktliste des Upgrade-Projekts):

| Punkt | Stelle | Maßnahme beim Sprung |
| --- | --- | --- |
| R1 | Twig-Filter `insert_tag_raw` | in 6.0 `insert_tag_html` (nach `sanitize_html`) |
| R2 | `label_callback` liefern HTML | in 6.0 `RecordLabel` zurückgeben |
| R14 | `$GLOBALS['TL_MODELS']`, `$GLOBALS['BE_MOD']` in `contao/config/config.php` | prüfen, ob es einen Attribut-Nachfolger gibt |
| R17 | `Service\Page\AutoItemResolver` ruft `Input::get('auto_item')` | einzige `Input`-Stelle; mit Contao 6/7 dort ersetzen |
| R19 | `Service\Text\TextNormalizer` speichert input-kodiert wie Contao 5.7 | auf Rohtext umstellen (eine Stelle), Contao-Migration `OutputEncodingMigration` vor den Bundle-Migrationen |
| R20 | `Service\Media\MemberMediaStorage` nutzt das in 5.7 als experimentell markierte Contao-Dateisystem | Schnittstelle gegen 6.x prüfen (eine Klasse) |
| R21 | `decodeEntities` an `prices`, `dates`, `linked_*` in `tl_events`/`tl_sekevents` | nach dem 6.0-Migrationslauf entfernen |
| R22 | DCA-Zugriff zur Laufzeit über `$GLOBALS['TL_DCA']` (Spaltenauswahl, Beschriftungen) | prüfen, ob es eine DCA-API gibt |

Contao 6 verlangt die Migrationen 5.x nicht mehr: Vor dem Sprung muss `contao:migrate` auf 5.7 vollständig gelaufen sein.

## Entwicklung

Das Bundle liegt als Geschwisterordner neben der Website. Deren Docker-Umgebung bindet es ein, Änderungen wirken nach `cache:clear`. Prüfbefehle (im Container der Website):

```bash
find /var/www/contao-kulturnetz-bundle/src -name '*.php' -print0 | xargs -0 -n1 php -l
php vendor/bin/contao-console lint:twig /var/www/contao-kulturnetz-bundle/contao/templates
php vendor/bin/contao-console lint:container
php vendor/bin/contao-console debug:router | grep kulturnetz
php vendor/bin/contao-console debug:config kulturnetz
```

Deprecations misst die Website im Dev-Container (README der Website, Abschnitt „Deprecations prüfen“).

## Gefördert vom Land Schleswig-Holstein

Die Entwicklung wurde vom Land Schleswig-Holstein finanziell gefördert. Die Erweiterung steht unter der GNU General Public License v3.0 als Open Source zur freien Verfügung.

## Lizenz

GPL-3.0-or-later
