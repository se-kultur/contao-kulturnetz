<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Write;

use Contao\CoreBundle\Monolog\ContaoContext;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use SeKultur\ContaoKulturnetzBundle\Service\Mail\NotificationMailer;
use SeKultur\ContaoKulturnetzBundle\Service\Text\AliasGenerator;
use SeKultur\ContaoKulturnetzBundle\Service\Write\Value\CreatedRecord;
use SeKultur\ContaoKulturnetzBundle\Service\Write\Value\RecordSchema;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Legt Profile und Veranstaltungen aus dem Frontend an und ändert sie
 * (Ersatz für files/se-kultur.de/code/insert<Typ>.php).
 *
 * Anlegen: Mitglied aus der Sitzung (nicht aus dem Formular), disable "1"
 * (Freischaltung durch die Redaktion), created und tstamp, eindeutiger Alias über
 * AliasGenerator (E32; bei gleichzeitiger Anlage desselben Namens neuer Versuch
 * mit dem nächsten Zähler), danach die Hinweismail an die Redaktion. Ein Fehler beim
 * Mailversand wird protokolliert; der Datensatz ist dann schon gespeichert, das
 * Anlegen gilt deshalb als erfolgreich.
 *
 * Bearbeiten: nur die Whitelist-Felder, tstamp wird gesetzt (E33; bisher blieb er
 * unverändert), Alias, disable, member_id und created bleiben. Besitz und
 * Schreibschutz prüft der Aufrufer vorher (OwnershipChecker).
 */
final class RecordWriter
{
    /**
     * Versuche beim Einfügen, wenn der Alias inzwischen vergeben ist.
     */
    private const MAX_INSERT_ATTEMPTS = 5;

    public function __construct(
        private readonly Connection $connection,
        private readonly RecordInputMapper $mapper,
        private readonly AliasGenerator $aliasGenerator,
        private readonly NotificationMailer $notificationMailer,
        private readonly ClockInterface $clock,
        #[Autowire(service: 'monolog.logger.contao.error')]
        private readonly LoggerInterface $errorLogger,
    ) {
    }

    /**
     * Gespeicherte Zeile oder null.
     *
     * @return array<string, mixed>|null
     */
    public function find(RecordSchema $schema, int $id): array|null
    {
        if ($id < 1) {
            return null;
        }

        $row = $this->connection->fetchAssociative(
            \sprintf('SELECT * FROM %s WHERE id = ?', $schema->table()),
            [$id],
            [ParameterType::INTEGER],
        );

        return false === $row ? null : $row;
    }

    /**
     * Legt einen Datensatz für das Mitglied an.
     *
     * @param array<string, mixed> $input POST-Daten
     *
     * @throws RecordValidationException
     * @throws RecordConflictException wenn der Alias auch nach mehreren Versuchen vergeben ist
     */
    public function create(RecordSchema $schema, array $input, int $memberId): CreatedRecord
    {
        $values = $this->mapper->map($schema, $input, $memberId);
        $now = $this->clock->now()->getTimestamp();
        $name = (string) ($values[$schema->nameField] ?? '');

        // AliasGenerator prüft vor dem Einfügen, ohne Sperre. Legt eine gleichzeitige
        // Anfrage (z. B. doppeltes Absenden) denselben Alias zuerst an, scheitert das
        // INSERT am UNIQUE-Index; der nächste Versuch sieht deren Datensatz und wählt
        // den nächsten Zähler.
        for ($attempt = 1; ; ++$attempt) {
            $alias = $this->aliasGenerator->generate($schema->type, $name);

            try {
                $id = $this->insert($schema, [
                    'member_id' => $memberId,
                    'alias' => $alias,
                    'tstamp' => $now,
                    'created' => $now,
                    'disable' => '1',
                    ...$values,
                ]);

                break;
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt >= self::MAX_INSERT_ATTEMPTS) {
                    $this->errorLogger->error(
                        \sprintf('KulturNetz: Neuer Datensatz (%s) nach %d Versuchen ohne eindeutigen Alias: %s', $schema->type, $attempt, $exception->getMessage()),
                        ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR), 'exception' => $exception],
                    );

                    throw new RecordConflictException($exception);
                }
            }
        }

        return new CreatedRecord($id, $alias, $this->notify($schema->type, $alias));
    }

    /**
     * Ändert einen Datensatz.
     *
     * @param array<string, mixed> $current gespeicherte Zeile (find())
     * @param array<string, mixed> $input   POST-Daten
     *
     * @throws RecordValidationException
     */
    public function update(RecordSchema $schema, array $current, array $input, int $memberId): void
    {
        $values = $this->mapper->map($schema, $input, $memberId, $current);
        $values['tstamp'] = $this->clock->now()->getTimestamp();

        $columns = array_keys($values);

        $this->connection->executeStatement(
            \sprintf(
                'UPDATE %s SET %s WHERE id = ?',
                $schema->table(),
                implode(', ', array_map(fn (string $column): string => $this->quote($column).' = ?', $columns)),
            ),
            [...array_values($values), (int) $current['id']],
            [...$this->types($values), ParameterType::INTEGER],
        );
    }

    /**
     * Fügt die Zeile ein und liefert die neue ID.
     *
     * @param array<string, string|int|null> $row
     *
     * @throws UniqueConstraintViolationException
     */
    private function insert(RecordSchema $schema, array $row): int
    {
        $columns = array_keys($row);

        $this->connection->executeStatement(
            \sprintf(
                'INSERT INTO %s (%s) VALUES (%s)',
                $schema->table(),
                implode(', ', array_map($this->quote(...), $columns)),
                implode(', ', array_fill(0, \count($columns), '?')),
            ),
            array_values($row),
            $this->types($row),
        );

        return (int) $this->connection->lastInsertId();
    }

    private function notify(string $type, string $alias): bool
    {
        try {
            return $this->notificationMailer->notifyNewRecord($type, $alias);
        } catch (\Throwable $exception) {
            // Der Datensatz ist gespeichert; ein Fehler beim Versand darf das Anlegen
            // nicht als gescheitert melden (das Mitglied würde erneut absenden).
            $this->errorLogger->error(
                \sprintf('KulturNetz: Redaktionsmail zum neuen Datensatz "%s" (%s) fehlgeschlagen: %s', $alias, $type, $exception->getMessage()),
                ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR), 'exception' => $exception],
            );

            return false;
        }
    }

    private function quote(string $column): string
    {
        return $this->connection->getDatabasePlatform()->quoteSingleIdentifier($column);
    }

    /**
     * Parametertypen zu den Spaltenwerten: Titelbild binär, Zahlen als Ganzzahl.
     *
     * @param array<string, string|int|null> $row
     *
     * @return list<ParameterType>
     */
    private function types(array $row): array
    {
        $types = [];

        foreach ($row as $column => $value) {
            $types[] = match (true) {
                'avatar' === $column => ParameterType::BINARY,
                \is_int($value) => ParameterType::INTEGER,
                default => ParameterType::STRING,
            };
        }

        return $types;
    }
}
