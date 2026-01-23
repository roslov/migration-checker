<?php

declare(strict_types=1);

namespace Db\Db;

use Codeception\Attribute\DataProvider;
use Codeception\Test\Unit;
use PHPUnit\Framework\Attributes\CoversClass;
use Roslov\MigrationChecker\Db\PostgreSqlDumper;
use Roslov\MigrationChecker\Db\SqlQuery;
use Roslov\MigrationChecker\Tests\Support\DbTester;

/**
 * Tests the PostgreSql dump fetcher.
 */
#[CoversClass(PostgreSqlDumper::class)]
final class PostgreSqlDumpTest extends Unit
{
    /**
     * @var DbTester Tester
     */
    protected DbTester $tester;

    /**
     * Tests database dumps.
     *
     * @param string $imageTag Image tag for the database version in the container
     */
    #[DataProvider('dbProvider')]
    public function testGetDump(string $imageTag): void
    {
        $imageType = 'postgresql';
        $I = $this->tester;
        $I->startDb($imageType, $imageTag);
        $I->waitForDbReadiness($imageType, $imageTag);

        $query = new SqlQuery(
            $I->getDsn($imageType),
            $I->getUsername($imageType),
            $I->getPassword(),
        );
        $dumper = new PostgreSqlDumper($query);
        foreach ($this->getMigrations() as $migration) {
            codecept_debug($migration);
            $query->execute($migration);
        }
        $expectedDump = $this->trimRowWhitespaces($this->getExpectedDump($imageTag));
        $dump = $this->trimRowWhitespaces($dumper->getDump()->toString());
        self::assertSame(
            trim($expectedDump),
            $dump,
        );
    }

    /**
     * Stops previously started containers before a test.
     */
    // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    public function _before(): void
    {
        $I = $this->tester;
        $I->stopDb();
    }

    /**
     * Stops running containers.
     */
    // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    public function _after(): void
    {
        $I = $this->tester;
        $I->stopDb();
    }

    /**
     * Returns test cases.
     *
     * @return string[][] Test cases
     */
    public static function dbProvider(): array
    {
        $tests = [
            ['18.1'],
            ['17.7'],
            ['16.11'],
            ['15.15'],
            ['14.20'],
            ['13.23'],
            ['12.22'],
            ['11.22'],
        ];
        $namedTests = [];
        foreach ($tests as $test) {
            $version = $test[0];
            $key = 'postgresql-' . $version;
            $namedTests[$key] = [$version . '-alpine'];
        }

        return $namedTests;
    }

    /**
     * Returns migration SQL for testing.
     *
     * @return string[] Migration SQLs
     */
    private function getMigrations(): array
    {
        return [
            <<<'SQL_WRAPPER'
                SET client_encoding = 'UTF8'
                SQL_WRAPPER,
            <<<'SQL_WRAPPER'
                CREATE TYPE pet_type AS ENUM ('dog', 'cat')
                SQL_WRAPPER,
            <<<'SQL_WRAPPER'
                CREATE TABLE info (
                    id SERIAL PRIMARY KEY,
                    name varchar(255) NOT NULL
                )
                SQL_WRAPPER,
            <<<'SQL_WRAPPER'
                COMMENT ON TABLE info IS 'Pet info'
                SQL_WRAPPER,
            <<<'SQL_WRAPPER'
                CREATE TABLE owner (
                    id SERIAL PRIMARY KEY,
                    first_name varchar(50) NOT NULL,
                    last_name varchar(100) NOT NULL,
                    code char(8) NOT NULL,
                    CONSTRAINT idx_name UNIQUE (first_name, last_name)
                )
                SQL_WRAPPER,
            <<<'SQL_WRAPPER'
                COMMENT ON TABLE owner IS 'Owner'
                SQL_WRAPPER,
            <<<'SQL_WRAPPER'
                CREATE INDEX idx_last_name ON owner (last_name)
                SQL_WRAPPER,
            <<<'SQL_WRAPPER'
                CREATE INDEX idx_first_name ON owner (first_name)
                SQL_WRAPPER,
            <<<'SQL_WRAPPER'
                CREATE TABLE pet (
                    id SERIAL PRIMARY KEY,
                    type pet_type NOT NULL,
                    info_id int NOT NULL,
                    owner_id int NOT NULL,
                    CONSTRAINT fk_owner FOREIGN KEY (owner_id) REFERENCES owner(id)
                        ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT fk_info FOREIGN KEY (info_id) REFERENCES info(id)
                        ON DELETE CASCADE ON UPDATE CASCADE
                )
                SQL_WRAPPER,
            <<<'SQL_WRAPPER'
                COMMENT ON TABLE pet IS 'Pets'
                SQL_WRAPPER,
            <<<'SQL_WRAPPER'
                COMMENT ON COLUMN pet.type IS 'Pet type'
                SQL_WRAPPER,
            <<<'SQL_WRAPPER'
                CREATE VIEW vw_pet AS
                    SELECT id, type FROM pet
                SQL_WRAPPER,
            <<<'SQL_WRAPPER'
                CREATE OR REPLACE FUNCTION before_owner_update_func()
                RETURNS TRIGGER AS $$
                BEGIN
                    IF NEW.first_name = 'John' THEN
                        NEW.last_name := 'Doe';
                    END IF;
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql
                SQL_WRAPPER,
            <<<'SQL_WRAPPER'
                CREATE TRIGGER before_owner_update
                    BEFORE UPDATE ON owner
                    FOR EACH ROW
                    EXECUTE FUNCTION before_owner_update_func()
                SQL_WRAPPER,
            <<<'SQL_WRAPPER'
                CREATE OR REPLACE PROCEDURE rename_to_john_doe(owner_id_param int)
                LANGUAGE sql
                AS $$
                    UPDATE owner SET first_name = 'John', last_name = 'Doe' WHERE id = owner_id_param;
                $$
                SQL_WRAPPER,
            <<<'SQL_WRAPPER'
                CREATE OR REPLACE FUNCTION get_full_name(owner_id_param INT)
                    RETURNS varchar(150)
                    LANGUAGE plpgsql
                AS $$
                DECLARE
                    full_name varchar(150);
                BEGIN
                    SELECT concat_ws(' ', first_name, last_name)
                        INTO full_name
                        FROM owner
                        WHERE id = owner_id_param;
                    RETURN full_name;
                END;
                $$
                SQL_WRAPPER,
        ];
    }

    /**
     * Returns expected dump SQL for testing.
     *
     * @param string $imageTag Image tag for the database version in the container
     *
     * @return string Expected dump SQL
     */
    private function getExpectedDump(string $imageTag): string
    {
        $majorVersion = explode('.', $imageTag)[0];
        $filename = match ($majorVersion) {
            '17', '16' => 'postgresql.dump.16.expected.sql',
            '15', '14' => 'postgresql.dump.14.expected.sql',
            '13', '12' => 'postgresql.dump.12.expected.sql',
            '11' => 'postgresql.dump.11.expected.sql',
            default => 'postgresql.dump.18.expected.sql',
        };

        return (string) file_get_contents(codecept_data_dir($filename));
    }

    /**
     * Trims leading and trailing whitespaces from each row in the dump.
     *
     * @return string Trimmed dump
     */
    private function trimRowWhitespaces(string $dump): string
    {
        return (string) preg_replace('#\s*\n\s*#', "\n", $dump);
    }
}
