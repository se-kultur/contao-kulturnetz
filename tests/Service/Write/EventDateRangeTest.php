<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Tests\Service\Write;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SeKultur\ContaoKulturnetzBundle\Service\Write\EventDateRange;

final class EventDateRangeTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function dates(): iterable
    {
        yield 'heute' => ['23.11.2026', true];
        yield 'gestern' => ['22.11.2026', false];
        yield 'morgen' => ['24.11.2026', true];
        yield 'Jahresende des übernächsten Jahres' => ['31.12.2028', true];
        yield 'Tag danach' => ['01.01.2029', false];
        yield 'Jahr 1999' => ['05.12.1999', false];
        yield 'Jahr 2999' => ['05.12.2999', false];
        yield 'kein Datum' => ['abc', false];
        yield 'ISO-Format' => ['2026-12-05', false];
        yield 'zweistelliges Jahr' => ['05.12.26', false];
    }

    #[DataProvider('dates')]
    public function testContains(string $date, bool $expected): void
    {
        $range = new EventDateRange(new FixedClock('2026-11-23 15:30:00'));

        self::assertSame($expected, $range->contains($date), $date);
    }

    public function testLimitsFollowTheClock(): void
    {
        $range = new EventDateRange(new FixedClock('2027-03-01 00:00:00'));

        self::assertSame('01.03.2027', $range->getMin()->format('d.m.Y'));
        self::assertSame('31.12.2029', $range->getMax()->format('d.m.Y'));
        self::assertTrue($range->contains('31.12.2029'));
        self::assertFalse($range->contains('28.02.2027'));
    }

    public function testTodayCountsUntilMidnight(): void
    {
        $range = new EventDateRange(new FixedClock('2026-11-23 23:59:59'));

        self::assertTrue($range->contains('23.11.2026'));
    }
}
