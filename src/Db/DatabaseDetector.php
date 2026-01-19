<?php

declare(strict_types=1);

namespace Roslov\MigrationChecker\Db;

use Roslov\MigrationChecker\Contract\DatabaseDetectorInterface;
use Roslov\MigrationChecker\Contract\QueryInterface;
use Roslov\MigrationChecker\Enum\DatabaseType;
use Roslov\MigrationChecker\Exception\DatabaseConnectionFailedException;
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
     * @throws DatabaseConnectionFailedException If the database connection fails
     */
    public function getType(): DatabaseType
    {
        return $this->getTypeAndVersion()[0];
    }

    /**
     * @inheritDoc
     * @throws DatabaseConnectionFailedException If the database connection fails
     */
    public function getVersion(): ?string
    {
        return $this->getTypeAndVersion()[1];
    }

    /**
     * Retrieves the type and version of the database.
     *
     * @return array{0: DatabaseType, 1: ?string} The type and version of the database
     *
     * @throws DatabaseConnectionFailedException If the database connection fails
     */
    private function getTypeAndVersion(): array
    {
        foreach (self::STRATEGIES as $strategy) {
            $result = $this->tryStrategy($strategy);
            if ($result !== null) {
                return $result;
            }
        }

        return [DatabaseType::Unknown, null];
    }

    /**
     * Tries a single detection strategy.
     *
     * @param array{name: DatabaseType, query: string, keyword: string} $strategy
     *
     * @return array{0: DatabaseType, 1: ?string}|null The type and version of the database
     *
     * @throws DatabaseConnectionFailedException If the database connection fails
     */
    private function tryStrategy(array $strategy): ?array
    {
        try {
            $versionString = $this->getVersionString($strategy['query']);
        } catch (DatabaseConnectionFailedException $e) {
            throw $e;
        } catch (Throwable) {
            return null;
        }

        if (stripos($versionString, $strategy['keyword']) !== false) {
            return [$strategy['name'], $this->extractVersionFromString($versionString)];
        }

        return null;
    }

    /**
     * Retrieves the version string from the query result.
     *
     * @param string $query The query to execute
     *
     * @return string The version string
     */
    private function getVersionString(string $query): string
    {
        $result = $this->query->execute($query)[0] ?? [];

        return (string) reset($result);
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
