<?php

declare(strict_types=1);

namespace Roslov\MigrationChecker\Db;

use Roslov\MigrationChecker\Contract\DatabaseDetectorInterface;
use Roslov\MigrationChecker\Contract\QueryInterface;
use Roslov\MigrationChecker\Enum\DatabaseType;
use Throwable;

/**
 * Detects the database type and version for migration compatibility checks.
 */
final class DatabaseDetector implements DatabaseDetectorInterface
{
    /**
     * List of strategies.
     */
    private const STRATEGIES = [
        ['name' => DatabaseType::Oracle, 'query' => 'SELECT * FROM v$version', 'keyword' => 'Oracle'],
        ['name' => DatabaseType::SqLite, 'query' => 'SELECT sqlite_version()', 'keyword' => '.'],
        ['name' => DatabaseType::SqlServer, 'query' => 'SELECT @@VERSION', 'keyword' => 'Microsoft'],
        ['name' => DatabaseType::PostgreSql, 'query' => 'SELECT version()', 'keyword' => 'PostgreSQL'],
        ['name' => DatabaseType::MariaDd, 'query' => 'SELECT VERSION()', 'keyword' => 'MariaDB'],
        ['name' => DatabaseType::MySql, 'query' => 'SELECT VERSION()', 'keyword' => ''],
    ];

    /**
     * Constructor.
     *
     * @param QueryInterface $query Query fetcher
     */
    public function __construct(private readonly QueryInterface $query)
    {
    }

    /**
     * @inheritDoc
     */
    public function getType(): DatabaseType
    {
        return $this->getTypeAndVersion()[0];
    }

    /**
     * @inheritDoc
     */
    public function getVersion(): ?string
    {
        return $this->getTypeAndVersion()[1];
    }

    /**
     * Retrieves the type and version of the database.
     *
     * @return array{0: DatabaseType, 1: ?string} The type and version of the database
     */
    private function getTypeAndVersion(): array
    {
        foreach (self::STRATEGIES as $strategy) {
            try {
                $result = $this->query->execute($strategy['query'])[0] ?? [];
                $versionString = (string) reset($result);
                if (stripos($versionString, $strategy['keyword']) !== false) {
                    return [$strategy['name'], $this->extractVersionFromString($versionString)];
                }
            } catch (Throwable) {
                continue;
            }
        }

        return [DatabaseType::Unknown, null];
    }

    /**
     * Retrieves the version from the version string.
     *
     * @return string|null The version in X.Y format (e.g., `8.4`)
     */
    private function extractVersionFromString(string $versionString): ?string
    {
        if (preg_match('/\d+\.\d+/', $versionString, $matches)) {
            return $matches[0];
        }

        return null;
    }
}
