<?php

declare(strict_types=1);

namespace Db\Db;

use Codeception\Test\Unit;
use PHPUnit\Framework\Attributes\CoversClass;
use Roslov\MigrationChecker\Db\SqLiteDumper;
use Roslov\MigrationChecker\Db\SqlQuery;
use Roslov\MigrationChecker\Tests\Support\DbTester;

/**
 * Tests the SQLite dump fetcher.
 */
#[CoversClass(SqLiteDumper::class)]
final class SqLiteDumperTest extends Unit
{
    /**
     * @var DbTester Tester
     */
    protected DbTester $tester;

    /**
     * Tests database dumps.
     */
    public function testGetDump(): void
    {
        $I = $this->tester;

        $imageType = 'sqlite';
        $query = new SqlQuery(
            $I->getDsn($imageType),
            $I->getUsername($imageType),
            $I->getPassword(),
        );
        $dumper = new SqLiteDumper($query);
        foreach ($this->getMigrations() as $migration) {
            codecept_debug($migration);
            $query->execute($migration);
        }

        self::assertSame(
            $this->normalizeDump($this->getExpectedDump()),
            $this->normalizeDump($dumper->getDump()->toString()),
        );
    }

    /**
     * Returns migration SQL for testing.
     *
     * @return string[] Migration SQLs
     */
    private function getMigrations(): array
    {
        return [
            <<<'SQL_WRAP'
                PRAGMA foreign_keys = ON
                SQL_WRAP,
            <<<'SQL_WRAP'
                CREATE TABLE owner (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    first_name TEXT NOT NULL,
                    last_name TEXT NOT NULL,
                    code TEXT NOT NULL
                )
                SQL_WRAP,
            <<<'SQL_WRAP'
                CREATE UNIQUE INDEX idx_owner_name ON owner(first_name, last_name)
                SQL_WRAP,
            <<<'SQL_WRAP'
                CREATE TABLE info (id INTEGER PRIMARY KEY, name TEXT NOT NULL)
                SQL_WRAP,
            <<<'SQL_WRAP'
                CREATE TABLE pet (
                    id INTEGER PRIMARY KEY,
                    type TEXT NOT NULL,
                    owner_id INTEGER NOT NULL,
                    info_id INTEGER NOT NULL,
                    CONSTRAINT fk_owner FOREIGN KEY(owner_id) REFERENCES owner(id)
                        ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT fk_info FOREIGN KEY(info_id) REFERENCES info(id)
                        ON DELETE CASCADE ON UPDATE CASCADE
                )
                SQL_WRAP,
            <<<'SQL_WRAP'
                CREATE TABLE pet_tag (pet_id INTEGER NOT NULL, tag TEXT NOT NULL, PRIMARY KEY (pet_id, tag))
                    WITHOUT ROWID
                SQL_WRAP,
            <<<'SQL_WRAP'
                CREATE INDEX idx_pet_type ON pet(type) WHERE type IS NOT NULL
                SQL_WRAP,
            <<<'SQL_WRAP'
                CREATE VIEW vw_pet AS SELECT id, type FROM pet
                SQL_WRAP,
            <<<'SQL_WRAP'
                CREATE TRIGGER before_owner_update
                BEFORE UPDATE ON owner
                BEGIN
                    UPDATE owner SET last_name = 'Doe' WHERE id = NEW.id AND NEW.first_name = 'John';
                END
                SQL_WRAP,
        ];
    }

    /**
     * Returns expected dump SQL for testing.
     *
     * @return string Expected dump SQL
     */
    private function getExpectedDump(): string
    {
        return (string) file_get_contents(codecept_data_dir('sqlite.dump.expected.sql'));
    }

    /**
     * Normalizes dump strings for comparison.
     *
     * @param string $dump Dump to normalize
     *
     * @return string Normalized dump
     */
    private function normalizeDump(string $dump): string
    {
        $normalizedDump = preg_replace('/\s+/', ' ', $dump);

        return trim((string) $normalizedDump);
    }
}
