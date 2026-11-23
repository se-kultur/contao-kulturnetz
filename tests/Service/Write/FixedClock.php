<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Tests\Service\Write;

use Psr\Clock\ClockInterface;

/**
 * Uhr mit festem Zeitpunkt für die Tests.
 */
final class FixedClock implements ClockInterface
{
    private readonly \DateTimeImmutable $now;

    public function __construct(string $now)
    {
        $this->now = new \DateTimeImmutable($now, new \DateTimeZone('Europe/Berlin'));
    }

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }
}
