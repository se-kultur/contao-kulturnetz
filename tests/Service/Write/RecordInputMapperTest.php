<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Tests\Service\Write;

use Contao\CoreBundle\Routing\PageFinder;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SeKultur\ContaoKulturnetzBundle\Config\KulturnetzConfig;
use SeKultur\ContaoKulturnetzBundle\Security\OwnershipChecker;
use SeKultur\ContaoKulturnetzBundle\Service\Text\HtmlSanitizer;
use SeKultur\ContaoKulturnetzBundle\Service\Text\TextNormalizer;
use SeKultur\ContaoKulturnetzBundle\Service\Write\ColumnLimits;
use SeKultur\ContaoKulturnetzBundle\Service\Write\EventDateRange;
use SeKultur\ContaoKulturnetzBundle\Service\Write\FormChoices;
use SeKultur\ContaoKulturnetzBundle\Service\Write\FormMessages;
use SeKultur\ContaoKulturnetzBundle\Service\Write\LinkedProfileFilter;
use SeKultur\ContaoKulturnetzBundle\Service\Write\RecordInputMapper;
use SeKultur\ContaoKulturnetzBundle\Service\Write\RecordSchemaRegistry;
use SeKultur\ContaoKulturnetzBundle\Service\Write\RecordValidationException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Serverprüfung der Anlegen- und Bearbeiten-Formulare: Pflichtfelder (auch ein
 * ganz fehlendes Feld), Höchstlängen des Formulars und der Zeitraum der Termine.
 * Die Meldungen kommen aus contao/languages/de/default.php.
 */
final class RecordInputMapperTest extends TestCase
{
    private RecordSchemaRegistry $schemas;

    private RecordInputMapper $mapper;

    protected function setUp(): void
    {
        $GLOBALS['TL_LANG']['ERR'] = [];
        require __DIR__.'/../../../contao/languages/de/default.php';
        $messages = $GLOBALS['TL_LANG']['ERR'];

        $translator = new class($messages) implements TranslatorInterface {
            /** @param array<string, string> $messages */
            public function __construct(private readonly array $messages)
            {
            }

            public function trans(string $id, array $parameters = [], string|null $domain = null, string|null $locale = null): string
            {
                $text = $this->messages[substr($id, 4)] ?? $id;

                return [] === $parameters ? $text : vsprintf($text, $parameters);
            }

            public function getLocale(): string
            {
                return 'de';
            }
        };

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $formMessages = new FormMessages($translator, new RequestStack(), $this->createMock(PageFinder::class));
        $clock = new FixedClock('2026-11-23 12:00:00');

        $this->schemas = new RecordSchemaRegistry(new FormChoices(), $formMessages);
        $this->mapper = new RecordInputMapper(
            new TextNormalizer(),
            new HtmlSanitizer(),
            new OwnershipChecker($connection),
            new LinkedProfileFilter($connection),
            new ColumnLimits($connection),
            $connection,
            new KulturnetzConfig(['records' => ['max_dates' => 20, 'max_prices' => 10]]),
            $formMessages,
            $clock,
            new EventDateRange($clock),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function event(): array
    {
        return [
            'name' => 'Testveranstaltung',
            'sparten' => 'Musik',
            'kurzbeschreibung' => 'Kurz.',
            'beschreibung' => '<p>'.str_repeat('Lange Beschreibung. ', 3).'</p>',
            'eventdate' => ['05.12.2026'],
            'eventtimestart' => ['19:00'],
            'eventtimeend' => ['21:00'],
            'eventcomment' => [''],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function artist(): array
    {
        return [
            'kuenstlername' => 'Testkünstlerin',
            'sparten' => 'Musik',
            'themen' => 'Themen',
            'kurzbeschreibung' => 'Kurz.',
            'beschreibung' => '<p>'.str_repeat('Lange Beschreibung. ', 3).'</p>',
        ];
    }

    /**
     * @param array<string, mixed>      $input
     * @param array<string, mixed>|null $current
     *
     * @return array<string, string>
     */
    private function errors(string $type, array $input, array|null $current = null): array
    {
        try {
            $this->mapper->map($this->schemas->get($type), $input, 38, $current);
        } catch (RecordValidationException $exception) {
            return $exception->getFieldErrors();
        }

        return [];
    }

    public function testValidInputPasses(): void
    {
        self::assertSame([], $this->errors('events', self::event()));
        self::assertSame([], $this->errors('artists', self::artist()));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function dates(): iterable
    {
        yield 'Jahr 2999' => ['05.12.2999', 'eventdate[]'];
        yield 'Jahr 1999' => ['05.12.1999', 'eventdate[]'];
        yield 'gestern' => ['22.11.2026', 'eventdate[]'];
        yield 'nach dem übernächsten Jahr' => ['01.01.2029', 'eventdate[]'];
    }

    #[DataProvider('dates')]
    public function testDateOutsideRangeIsRejected(string $date, string $field): void
    {
        $errors = $this->errors('events', ['eventdate' => [$date]] + self::event());

        self::assertArrayHasKey($field, $errors);
        self::assertSame('Termin 1: Bitte geben Sie ein Datum zwischen dem 23.11.2026 und dem 31.12.2028 an.', $errors[$field]);
    }

    public function testBoundaryDatesAreAccepted(): void
    {
        self::assertSame([], $this->errors('events', ['eventdate' => ['23.11.2026', '31.12.2028'], 'eventtimestart' => ['19:00', '19:00'], 'eventtimeend' => ['20:00', '20:00'], 'eventcomment' => ['', '']] + self::event()));
    }

    public function testEndBeforeStartOnTheSameDayStaysAllowed(): void
    {
        self::assertSame([], $this->errors('events', ['eventtimestart' => ['20:00'], 'eventtimeend' => ['19:00']] + self::event()));
    }

    public function testYearlessSekEventDatesAreNotChecked(): void
    {
        $input = ['eventdate' => ['15.03.'], 'sparten' => 'Musik', 'kulturform' => 'Ausstellung'] + self::event();

        self::assertArrayNotHasKey('eventdate[]', $this->errors('sekevents', $input));
    }

    public function testUnchangedStoredPastDateStaysSavable(): void
    {
        $current = ['dates' => json_encode([['date' => '14.06.2025', 'start' => '18:00', 'end' => '22:00', 'comment' => '']], JSON_THROW_ON_ERROR)];
        $input = ['eventdate' => ['14.06.2025'], 'eventtimestart' => ['18:00'], 'eventtimeend' => ['22:00']] + self::event();

        self::assertSame([], $this->errors('events', $input, $current));
    }

    public function testNewPastDateIsRejectedWhenEditing(): void
    {
        $current = ['dates' => json_encode([['date' => '14.06.2025', 'start' => '18:00', 'end' => '22:00', 'comment' => '']], JSON_THROW_ON_ERROR)];
        $input = ['eventdate' => ['15.06.2025'], 'eventtimestart' => ['18:00'], 'eventtimeend' => ['22:00']] + self::event();

        self::assertArrayHasKey('eventdate[]', $this->errors('events', $input, $current));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function requiredEventFields(): iterable
    {
        yield 'name' => ['name', 'name'];
        yield 'kurzbeschreibung' => ['kurzbeschreibung', 'kurzbeschreibung'];
        yield 'beschreibung' => ['beschreibung', 'beschreibung'];
        yield 'sparten' => ['sparten', 'sparten'];
        yield 'termine' => ['eventdate', 'eventdate[]'];
    }

    #[DataProvider('requiredEventFields')]
    public function testMissingRequiredEventFieldIsRejectedOnCreateAndUpdate(string $omit, string $field): void
    {
        $input = self::event();
        unset($input[$omit]);

        self::assertArrayHasKey($field, $this->errors('events', $input), 'anlegen');
        self::assertArrayHasKey($field, $this->errors('events', $input, ['name' => 'Alt', 'dates' => '[]']), 'bearbeiten');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function requiredArtistFields(): iterable
    {
        foreach (['kuenstlername', 'sparten', 'themen', 'kurzbeschreibung', 'beschreibung'] as $field) {
            yield $field => [$field];
        }
    }

    #[DataProvider('requiredArtistFields')]
    public function testMissingRequiredArtistFieldIsRejectedOnCreateAndUpdate(string $omit): void
    {
        $input = self::artist();
        unset($input[$omit]);

        self::assertArrayHasKey($omit, $this->errors('artists', $input), 'anlegen');
        self::assertArrayHasKey($omit, $this->errors('artists', $input, ['name' => 'Alt']), 'bearbeiten');
    }

    public function testBlankTopicsAreRejectedForAllProfileTypes(): void
    {
        self::assertArrayHasKey('themen', $this->errors('artists', ['themen' => '  '] + self::artist()));

        foreach (['hosts', 'locations'] as $type) {
            $input = ['name' => 'Name'] + self::artist();
            unset($input['kuenstlername'], $input['sparten']);

            self::assertSame([], $this->errors($type, $input), $type);
            self::assertArrayHasKey('themen', $this->errors($type, ['themen' => ''] + $input), $type);
        }
    }

    public function testOptionalFieldMissingOnUpdateStaysUntouched(): void
    {
        $input = self::artist();
        $values = $this->mapper->map($this->schemas->get('artists'), $input, 38, ['name' => 'Alt']);

        self::assertArrayNotHasKey('zitat', $values);
        self::assertArrayNotHasKey('website', $values);
    }

    public function testFormMaxLengthOfPriceCommentAndCooperationPartner(): void
    {
        $limit = RecordSchemaRegistry::PRICE_COMMENT_MAX_LENGTH;
        self::assertSame([], $this->errors('events', ['pricecomment' => str_repeat('p', $limit)] + self::event()));
        self::assertArrayHasKey('pricecomment', $this->errors('events', ['pricecomment' => str_repeat('p', $limit + 1)] + self::event()));

        $limit = RecordSchemaRegistry::COOPERATION_PARTNER_MAX_LENGTH;
        self::assertSame([], $this->errors('events', ['kooperationspartner' => str_repeat('k', $limit)] + self::event()));
        self::assertArrayHasKey('kooperationspartner', $this->errors('events', ['kooperationspartner' => str_repeat('k', $limit + 1)] + self::event()));
    }

    public function testStoredOverlongValueStaysSavableUnchanged(): void
    {
        $long = str_repeat('k', 300);
        $current = ['name' => 'Alt', 'kooperationspartner' => $long, 'dates' => '[]'];

        self::assertArrayNotHasKey('kooperationspartner', $this->errors('events', ['kooperationspartner' => $long] + self::event(), $current));
    }
}
