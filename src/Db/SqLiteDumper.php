<?php

declare(strict_types=1);

namespace Roslov\MigrationChecker\Db;

use Roslov\MigrationChecker\Contract\DumperInterface;
use Roslov\MigrationChecker\Contract\QueryInterface;
use Roslov\MigrationChecker\Contract\StateInterface;

/**
 * Fetches the SQLite dump.
 */
final class SqLiteDumper implements DumperInterface
{
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
    public function getDump(): StateInterface
    {
        $sql = <<<'SQL_WRAP'
            SELECT type, name, `sql`
            FROM sqlite_master
            WHERE `sql` IS NOT NULL
            AND name NOT LIKE 'sqlite_%'
            ORDER BY
                CASE type
                    WHEN 'table' THEN 0
                    WHEN 'index' THEN 1
                    WHEN 'view' THEN 2
                    WHEN 'trigger' THEN 3
                    ELSE 4
                END,
                name,
                `sql`
            SQL_WRAP;
        $rows = $this->query->execute($sql);
        $statements = array_map(fn (array $row): string => $this->normalizeStatement((string) $row['sql']), $rows);
        $dump = trim(implode("\n", $statements));

        return new State($dump);
    }

    /**
     * Normalizes a statement by trimming and appending a semicolon if needed.
     *
     * @param string $statement Statement to normalize
     *
     * @return string Normalized statement
     */
    private function normalizeStatement(string $statement): string
    {
        $normalizedStatement = trim($statement);
        if (!str_ends_with($normalizedStatement, ';')) {
            $normalizedStatement .= ';';
        }

        return $normalizedStatement;
    }
}
