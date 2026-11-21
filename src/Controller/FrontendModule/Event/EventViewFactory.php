<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\FrontendModule\Event;

use SeKultur\ContaoKulturnetzBundle\Service\Event\EventFormatter;
use SeKultur\ContaoKulturnetzBundle\Service\Event\EventType;
use SeKultur\ContaoKulturnetzBundle\Service\Image\FigureFactory;
use SeKultur\ContaoKulturnetzBundle\Service\Image\ImageSize;
use SeKultur\ContaoKulturnetzBundle\Service\Text\TextNormalizer;
use Symfony\Component\Uid\Uuid;

/**
 * Bereitet die Darstellungsdaten der zehn Veranstaltungsmodule auf, damit die
 * Twig-Templates nur noch ausgeben (bisher stand diese Logik in den
 * mod_event*.html5- und mod_sekevent*.html5-Templates).
 *
 * Bilder kommen über den FigureFactory mit den benannten Größen des Bundles, die
 * Termin- und Preisdaten über den EventFormatter. Veranstaltungen sind Arrays im
 * Format von Service\Event\EventFormatter.
 */
final class EventViewFactory
{
    /**
     * Veranstaltungsformate der SE-KulturTage (Auswahl im Suchformular und im
     * Formular zum Anlegen und Bearbeiten, bisher in den Templates ausgeschrieben).
     */
    public const SEKEVENT_FORMATS = ['SE-KulturFenster', 'SE-KulturDorf', 'SE-KulturNacht', 'SE-JungeKunst'];

    private const DAY_IN_SECONDS = 86400;

    public function __construct(
        private readonly FigureFactory $figureFactory,
        private readonly TextNormalizer $textNormalizer,
        private readonly EventFormatter $formatter,
    ) {
    }

    /**
     * Kodiert die Klammern von Insert-Tags ("{{" und "}}") als Entities, bisher
     * Input::encodeInsertTags(). Für Werte aus der Anfrage (Suchfelder), die ins Markup
     * zurückgeschrieben werden: Contao ersetzt Insert-Tags in der fertigen Seite.
     */
    public function encodeInsertTags(string $value): string
    {
        return str_replace(['{{', '}}'], ['&#123;&#123;', '&#125;&#125;'], $value);
    }

    /**
     * Bild einer Datei (UUID, binär oder Text) in einer benannten Größe, ohne Datei
     * das Ersatzbild.
     *
     * @return array{src: string, alt: string}
     */
    public function image(string|null $uuid, ImageSize $size): array
    {
        $source = null === $uuid || '' === $uuid ? null : $uuid;

        return [
            'src' => (string) $this->figureFactory->getImageUrl($source, $size),
            'alt' => (string) $this->figureFactory->getMetadata($source)?->getAlt(),
        ];
    }

    /**
     * Titelbild der Detailansicht mit Lightbox-Ziel und Bildbeschreibung (Titel und
     * Copyright aus den Metadaten der Datei; Klartext, das Template maskiert).
     *
     * @param array<string, mixed> $event
     *
     * @return array{src: string, lightbox_src: string, alt: string, title: string, caption: string}
     */
    public function titleImage(array $event): array
    {
        $uuid = isset($event['avatar_file']) && \is_array($event['avatar_file']) ? ($event['avatar'] ?? null) : null;
        $source = null === $uuid || '' === $uuid ? null : $uuid;

        $src = (string) $this->figureFactory->getImageUrl($source, ImageSize::Detail);
        $metadata = $this->figureFactory->getMetadata($source);

        return [
            'src' => $src,
            'lightbox_src' => (string) ($this->figureFactory->getImageUrl($source, ImageSize::Lightbox) ?? $src),
            'alt' => (string) $metadata?->getAlt(),
            'title' => $this->plain($metadata?->getTitle()),
            'caption' => $this->plain($metadata?->getCaption()),
        ];
    }

    /**
     * Termine der Detailansicht (Datum als Text, Beginn, Ende, Hinweis) als Klartext.
     *
     * Die Felder "dates" und "prices" sind JSON mit decodeEntities: Contao kodiert dort
     * nur das Zeichen "<" (als "&#60;"), ">" und Anführungszeichen bleiben roh. Der
     * Contao-Escaper von Twig kodiert Zeichenketten mit rohem ">" oder Anführungszeichen
     * doppelt und würde "&#60;" sichtbar ausgeben; Klartext wird dagegen vollständig und
     * richtig maskiert.
     *
     * @param array<string, mixed> $event
     *
     * @return list<array{text: string, start: string, end: string, comment: string}>
     */
    public function dates(array $event): array
    {
        $dates = [];

        foreach ($event['dates_formatted'] ?? [] as $date) {
            $dates[] = [
                'text' => (string) ($date['text'] ?? ''),
                'start' => $this->plain($date['start'] ?? null),
                'end' => $this->plain($date['end'] ?? null),
                'comment' => $this->plain($date['comment'] ?? null),
            ];
        }

        return $dates;
    }

    /**
     * Preiszeilen der Detailansicht (Preis, Bezeichnung) als Klartext, siehe dates().
     *
     * @param array<string, mixed> $event
     *
     * @return list<array{pricetag: string, price: string}>
     */
    public function prices(array $event): array
    {
        $prices = [];

        foreach ($event['prices_formatted'] ?? [] as $price) {
            $prices[] = [
                'pricetag' => $this->plain($price['pricetag'] ?? null),
                'price' => $this->plain($price['price'] ?? null),
            ];
        }

        return $prices;
    }

    /**
     * Verknüpfte Profile der Veranstaltung mit ihrem kleinen Profilbild.
     *
     * @param array<string, mixed> $event
     *
     * @return array{locations: list<array<string, mixed>>, artists: list<array<string, mixed>>, hosts: list<array<string, mixed>>}
     */
    public function linkedProfiles(array $event): array
    {
        $result = ['locations' => [], 'artists' => [], 'hosts' => []];

        foreach (['locations' => 'linked_locations_data', 'artists' => 'linked_artists_data', 'hosts' => 'linked_hosts_data'] as $key => $column) {
            foreach ($event[$column] ?? [] as $profile) {
                $result[$key][] = [
                    'id' => (int) $profile['id'],
                    'name' => (string) ($profile['name'] ?? ''),
                    'alias' => (string) ($profile['alias'] ?? ''),
                    'image' => $this->image($profile['avatar'] ?? null, ImageSize::Avatar)['src'],
                ];
            }
        }

        return $result;
    }

    /**
     * Kontaktblock von Location ("location") oder Veranstalter:in ("host") einer
     * Veranstaltung: Adresse mit Link auf die Kartensuche, Telefon, E-Mail und
     * Webseite wie bisher in mod_event.html5.
     *
     * @param array<string, mixed> $event
     *
     * @return array{address: string, maps_url: string, phone: string, phone_digits: string, email: string, website: string}
     */
    public function contact(array $event, string $prefix): array
    {
        $address = ($event[$prefix.'_adresse'] ?? '').', '.($event[$prefix.'_plz'] ?? '').' '.($event[$prefix.'_ort'] ?? '');
        $phone = (string) ($event[$prefix.'_telefon'] ?? '');

        return [
            'address' => $address,
            // Die Adresse ist im Contao-Speicherformat abgelegt; die Suche braucht Klartext.
            'maps_url' => 'https://www.google.de/maps/place/'.urlencode($this->textNormalizer->toPlainText($address)),
            'phone' => $phone,
            'phone_digits' => (string) preg_replace('/[^0-9]+/', '', $this->textNormalizer->toPlainText($phone)),
            'email' => (string) ($event[$prefix.'_email'] ?? ''),
            'website' => (string) ($event[$prefix.'_website'] ?? ''),
        ];
    }

    /**
     * Teilt die Veranstaltungen eines Mitglieds in kommende und vergangene (kein
     * künftiger Termin) wie bisher mod_events_list.html5 bzw.
     * mod_sekevents_list.html5. Das Mitglied sieht auch seine nicht freigeschalteten
     * Veranstaltungen.
     *
     * @param array<string, array<string, mixed>> $events Ergebnis von EventRepository::findByMemberId()
     *
     * @return array{upcoming: list<array<string, mixed>>, past: list<array<string, mixed>>}
     */
    public function ownListGroups(array $events): array
    {
        $upcoming = [];
        $past = [];

        foreach ($events as $event) {
            $card = ['event' => $event, 'image' => $this->image($event['avatar'] ?? null, ImageSize::List)];

            if (false === $event['nextdate'] || (int) $event['nextdate'] < 1) {
                $past[] = $card;
            } else {
                $upcoming[] = $card;
            }
        }

        return ['upcoming' => $upcoming, 'past' => $past];
    }

    /**
     * Gruppiert die Treffer der Übersicht nach Kalendertag (Zwischenüberschrift
     * "Events am TT.MM.JJJJ") und ermittelt je Karte die Zeiten dieses Tages.
     *
     * Der Tag ergibt sich wie bisher aus dem Schlüssel "<Zeitstempel>_<ID>" der
     * Suchergebnisse. Bei den SE-KulturTagen trägt der Schlüssel den Zeitzonenversatz
     * (siehe Service\Event\Date\YearlessDates::resultKeyTimestamp()), deshalb wird
     * der Tag dort wie bisher über date() auf Mitternacht zurückgerechnet. Die
     * Zeiten sind Termine strikt nach Tagesbeginn und vor dem Folgetag.
     *
     * @param array<string, array<string, mixed>> $events Ergebnis von EventSearch::search()
     *
     * @return list<array{date: string, entries: list<array<string, mixed>>}>
     */
    public function directoryDays(EventType $type, array $events): array
    {
        $days = [];
        $currentDate = null;

        foreach ($events as $key => $event) {
            $keyStamp = (int) explode('_', (string) $key)[0];
            $dayStart = $type->datesStoredWithoutYear() ? (int) strtotime(date('Y-m-d', $keyStamp)) : $keyStamp;
            $label = date('d.m.Y', $dayStart);

            if ($label !== $currentDate) {
                $days[] = ['date' => $label, 'entries' => []];
                $currentDate = $label;
            }

            $days[array_key_last($days)]['entries'][] = [
                'event' => $event,
                'image' => $this->image($event['avatar'] ?? null, ImageSize::List),
                'times' => $this->dayTimes($event, $dayStart),
            ];
        }

        return $days;
    }

    /**
     * Werte für das Formular zum Anlegen und Bearbeiten.
     *
     * Ohne Datensatz (Anlegen, fremde oder unbekannte Veranstaltung) sind alle
     * Felder leer; "linked_*" tragen dann die leere Liste, damit das Formular-JS
     * sie auswerten kann (bisher leer, was einen Skriptfehler auslöste).
     *
     * @param array<string, mixed>|null $event
     *
     * @return array<string, mixed>
     */
    public function formValues(EventType $type, string $mode, array|null $event): array
    {
        $values = [
            'id' => '',
            'alias' => '',
            'avatar' => '',
            'avatar_src' => $this->figureFactory->getPlaceholderPath(),
            'name' => '',
            'sparten' => '',
            'kulturform' => '',
            'zielgruppen' => '',
            'artists' => '',
            'linked_artists' => '[]',
            'eintrittfrei' => '',
            'pricecomment' => '',
            'spenden' => '',
            'anmeldung' => '',
            'anmeldestelle' => '',
            'locations' => '',
            'linked_locations' => '[]',
            'location_adresse' => '',
            'location_plz' => '',
            'location_ort' => '',
            'location_barrierefreiheit' => '',
            'location_telefon' => '',
            'location_email' => '',
            'location_website' => '',
            'hosts' => '',
            'linked_hosts' => '[]',
            'host_adresse' => '',
            'host_plz' => '',
            'host_ort' => '',
            'host_telefon' => '',
            'host_email' => '',
            'host_website' => '',
            'kurzbeschreibung' => '',
            'beschreibung' => '',
            'kooperationspartner' => '',
            'wimpelketten' => '',
            'plakatentwurf' => '',
            'programmhefte' => '',
            'werbeplakate_a4' => '',
            'werbeplakate_a3' => '',
            'werbeplakate_a1' => '',
            'werbeplakate_a0' => '',
        ];

        if (null !== $event) {
            foreach ($values as $column => $default) {
                if (isset($event[$column]) && (\is_string($event[$column]) || \is_int($event[$column]))) {
                    $values[$column] = (string) $event[$column];
                }
            }

            foreach (['linked_artists', 'linked_locations', 'linked_hosts'] as $column) {
                if ('' === $values[$column]) {
                    $values[$column] = '[]';
                }
            }

            if (isset($event['avatar_file']['path']) && \is_string($event['avatar_file']['path'])) {
                $values['avatar_src'] = $event['avatar_file']['path'];
                $values['avatar'] = $this->toUuidString((string) ($event['avatar'] ?? ''));
            }
        } elseif ('add' === $mode) {
            // Neue Veranstaltung: Eintritt frei vorbelegt, Werbemittel mit 0.
            $values['eintrittfrei'] = '1';

            foreach (['programmhefte', 'werbeplakate_a4', 'werbeplakate_a3', 'werbeplakate_a1', 'werbeplakate_a0'] as $column) {
                $values[$column] = '0';
            }
        }

        // Preise und Termine als Klartext (Felder mit decodeEntities, siehe dates()); ein
        // Eingabefeld zeigt den Wert sonst mit sichtbaren Entities.
        $formValues = $this->formatter->getFormValues($event);
        $values['price_rows'] = array_map(fn (array $row): array => array_map($this->plain(...), $row), $formValues['prices']);
        $values['date_rows'] = 'add' === $mode
            ? [['date' => '', 'start' => '', 'end' => '', 'comment' => '']]
            : array_map(fn (array $row): array => array_map($this->plain(...), $row), $formValues['dates']);
        $values['linked'] = null === $event
            ? ['locations' => [], 'artists' => [], 'hosts' => []]
            : $this->linkedProfiles($event);

        return $values;
    }

    /**
     * Zeiten einer Veranstaltung an einem Tag: erster Termin mit Datum, weitere nur
     * mit Uhrzeit ("+ 19:00 Uhr").
     *
     * @param array<string, mixed> $event
     *
     * @return array{first: int|null, more: list<int>}
     */
    private function dayTimes(array $event, int $dayStart): array
    {
        $first = null;
        $more = [];

        foreach (array_keys($event['dates_formatted'] ?? []) as $key) {
            $tstamp = (int) explode('_', (string) $key)[0];

            if ($tstamp > $dayStart && $tstamp < $dayStart + self::DAY_IN_SECONDS) {
                if (null === $first) {
                    $first = $tstamp;
                } else {
                    $more[] = $tstamp;
                }
            }
        }

        return ['first' => $first, 'more' => $more];
    }

    /**
     * Wert im Contao-Speicherformat als Klartext für die Ausgabe in Twig. Die Klammern von
     * Insert-Tags bleiben kodiert: Der Klartext enthielte sonst "{{...}}", und Contao ersetzt
     * Insert-Tags in der fertigen Seite, ein Mitglied könnte so Insert-Tags einschleusen.
     */
    private function plain(mixed $value): string
    {
        return $this->encodeInsertTags($this->textNormalizer->toPlainText(\is_scalar($value) ? (string) $value : ''));
    }

    private function toUuidString(string $binary): string
    {
        try {
            return (16 === \strlen($binary) ? Uuid::fromBinary($binary) : Uuid::fromString($binary))->toRfc4122();
        } catch (\InvalidArgumentException) {
            return '';
        }
    }
}
