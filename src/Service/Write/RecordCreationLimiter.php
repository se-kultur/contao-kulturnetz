<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Write;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Ratenlimit für neue Profile und Veranstaltungen je Mitglied.
 *
 * Jede Anlage sendet eine Hinweismail an die Redaktion; das Limit begrenzt
 * Missbrauch und Fehlbedienung, ohne normale Nutzung zu stören. Gezählt werden
 * nur erfolgreich angelegte Einträge, alle Typen zusammen; abgelehnte Eingaben
 * (422) verbrauchen nichts. Grenze und Zeitfenster stehen in der
 * Bundle-Konfiguration (kulturnetz.records.create_rate_limit, Standard 20 je
 * Stunde, gleitendes Fenster), der Limiter wird in
 * ContaoKulturnetzBundle::loadExtension() angelegt.
 *
 * Prüfen und Zählen sind getrennt (isAllowed() vor dem Anlegen, hit() danach).
 * Zwei gleichzeitige Anfragen können deshalb beide noch durchgehen; das Limit ist
 * eine Betriebsgrenze, keine harte Quote.
 */
final class RecordCreationLimiter
{
    public function __construct(
        #[Autowire(service: 'kulturnetz.rate_limiter.record_create')]
        private readonly RateLimiterFactoryInterface $factory,
    ) {
    }

    /**
     * Null, wenn das Mitglied noch einen Eintrag anlegen darf, sonst der Zeitpunkt,
     * ab dem es wieder möglich ist.
     */
    public function getBlockedUntil(int $memberId): \DateTimeImmutable|null
    {
        $rateLimit = $this->factory->create($this->key($memberId))->consume(0);

        return $rateLimit->getRemainingTokens() > 0 ? null : $rateLimit->getRetryAfter();
    }

    /**
     * Zählt einen angelegten Eintrag.
     */
    public function hit(int $memberId): void
    {
        $this->factory->create($this->key($memberId))->consume();
    }

    private function key(int $memberId): string
    {
        return 'member-'.$memberId;
    }
}
