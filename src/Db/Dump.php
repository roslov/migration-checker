<?php

declare(strict_types=1);

namespace Roslov\MigrationChecker\Db;

use Roslov\MigrationChecker\Contract\DatabaseDetectorInterface;
use Roslov\MigrationChecker\Contract\DumpInterface;
use Roslov\MigrationChecker\Contract\QueryInterface;
use Roslov\MigrationChecker\Contract\StateInterface;
use Roslov\MigrationChecker\Enum\DatabaseType;
use Roslov\MigrationChecker\Exception\UnknownDatabaseTypeException;
use Roslov\MigrationChecker\Exception\UnknownDatabaseVersionException;

/**
 * Detects the database type and fetches its dump.
 */
final class Dump implements DumpInterface
{
    /**
     * Constructor.
     *
     * @param QueryInterface $query Query fetcher
     * @param DatabaseDetectorInterface $detector Database detector
     */
    public function __construct(
        private readonly QueryInterface $query,
        private readonly DatabaseDetectorInterface $detector,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getDump(): StateInterface
    {
        return $this->getDumper()->getDump();
    }

    /**
     * Returns the appropriate dumper for the detected database type.
     *
     * @return DumpInterface Dumper
     *
     * @throws UnknownDatabaseTypeException If the database type is not supported
     * @throws UnknownDatabaseVersionException If the database version is not supported
     */
    private function getDumper(): DumpInterface
    {
        $dbType = $this->detector->getType();

        return match ($dbType) {
            DatabaseType::MySql,
            DatabaseType::MariaDd => new MySqlDump($this->query),
            DatabaseType::PostgreSql => new PostgreSqlDump($this->query),
            default => throw new UnknownDatabaseTypeException(
                sprintf('Unsupported database type: %s', $dbType->value),
            ),
        };
    }
}
