<?php

declare(strict_types=1);

namespace Db\Db;

use Codeception\Attribute\DataProvider;
use Codeception\Test\Unit;
use PHPUnit\Framework\Attributes\CoversClass;
use Roslov\MigrationChecker\Db\MySqlDumper;
use Roslov\MigrationChecker\Db\SqlQuery;
use Roslov\MigrationChecker\Tests\Support\DbTester;

/**
 * Tests the MySQL dump fetcher.
 */
#[CoversClass(MySqlDumper::class)]
final class MySqlDumperTest extends Unit
{
    /**
     * @var DbTester Tester
     */
    protected DbTester $tester;

    /**
     * Tests database dumps.
     *
     * @param string $imageType Image type for container creation
     * @param string $imageTag Image tag for the database version in the container
     */
    #[DataProvider('dbProvider')]
    public function testGetDump(
        string $imageType,
        string $imageTag,
    ): void {
        $I = $this->tester;
        $I->startDb($imageType, $imageTag);
        $I->waitForDbReadiness($imageType, $imageTag);

        $query = new SqlQuery(
            $I->getDsn($imageType),
            $I->getUsername($imageType),
            $I->getPassword(),
        );
        $dumper = new MySqlDumper($query);
        foreach ($this->getMigrations() as $migration) {
            codecept_debug($migration);
            $query->execute($migration);
        }
        self::assertSame(
            trim($this->getExpectedDump($imageType, $imageTag)),
            $dumper->getDump()->toString(),
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
     * @return array<string, string[]> Test cases
     */
    public static function dbProvider(): array
    {
        $tests = [
            ['mysql', '9.5.0'],
            ['mysql', '9.0.1'],
            ['mysql', '8.4.5'],
            ['mysql', '8.0.44'],
            ['mysql', '5.6.50'],
            ['mysql', '5.5.62'],

            ['mariadb', '12.1.2'],
            ['mariadb', '11.8.5'],
            ['mariadb', '10.11.15'],
            ['mariadb', '10.6.24'],
            ['mariadb', '10.3.39'],
        ];
        $namedTests = [];
        foreach ($tests as $test) {
            $key = implode('-', $test);
            $namedTests[$key] = $test;
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
            <<<'SQL_WRAP'
                SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci
                SQL_WRAP,
            <<<'SQL_WRAP'
                CREATE TABLE pet (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    type enum('dog', 'cat') NOT NULL COMMENT 'Pet type',
                    info_id int(11) NOT NULL,
                    owner_id int(11) NOT NULL,
                    PRIMARY KEY (id)
                ) COMMENT='Pets'
                SQL_WRAP,
            <<<'SQL_WRAP'
                CREATE TABLE info (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    name varchar(255) NOT NULL,
                    PRIMARY KEY (id)
                ) COMMENT='Pet info'
                SQL_WRAP,
            <<<'SQL_WRAP'
                CREATE TABLE owner (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    first_name varchar(50) NOT NULL,
                    last_name varchar(100) NOT NULL,
                    code char(8) NOT NULL,
                    PRIMARY KEY (`id`)
                ) COMMENT='Owner'
                SQL_WRAP,
            <<<'SQL_WRAP'
                ALTER TABLE pet
                    ADD CONSTRAINT fk_owner FOREIGN KEY (owner_id) REFERENCES owner(id)
                        ON DELETE CASCADE ON UPDATE CASCADE,
                    ADD CONSTRAINT fk_info FOREIGN KEY (info_id) REFERENCES info(id)
                        ON DELETE CASCADE ON UPDATE CASCADE
                SQL_WRAP,
            <<<'SQL_WRAP'
                ALTER TABLE owner
                    ADD UNIQUE KEY idx_name (first_name, last_name)
                SQL_WRAP,
            <<<'SQL_WRAP'
                ALTER TABLE owner
                    ADD KEY idx_last_name (last_name),
                    ADD KEY idx_first_name (first_name)
                SQL_WRAP,
            <<<'SQL_WRAP'
                CREATE
                    SQL SECURITY INVOKER
                VIEW vw_pet
                AS
                    SELECT id, type FROM pet
                SQL_WRAP,
            <<<'SQL_WRAP'
                CREATE TRIGGER `before_owner_update` BEFORE UPDATE ON `owner` FOR EACH ROW BEGIN
                    IF new.first_name = 'John' THEN
                        SET new.last_name = 'Doe';
                    END IF;
                END
                SQL_WRAP,
            <<<'SQL_WRAP'
                CREATE PROCEDURE rename_to_john_doe(IN owner_id int)
                    SQL SECURITY INVOKER
                BEGIN
                    UPDATE owner SET first_name = 'John', last_name = 'Doe' WHERE id = owner_id;
                END
                SQL_WRAP,
            <<<'SQL_WRAP'
                CREATE FUNCTION get_full_name(owner_id INT)
                    RETURNS varchar(150)
                    SQL SECURITY INVOKER
                BEGIN
                    DECLARE full_name varchar(150);
                    SELECT CONCAT_WS(' ', first_name, last_name)
                        INTO full_name
                        FROM owner
                        WHERE id = owner_id;
                    RETURN full_name;
                END
                SQL_WRAP,
            <<<'SQL_WRAP'
                CREATE
                EVENT rename_first_owner
                    ON SCHEDULE EVERY '1' DAY
                    STARTS '2026-01-20 17:00:00'
                    DO
                BEGIN
                    CALL rename_to_john_doe(1);
                END
                SQL_WRAP,
            <<<'SQL_WRAP'
                ALTER EVENT rename_first_owner ENABLE
                SQL_WRAP,
        ];
    }

    /**
     * Returns expected dump SQL for testing.
     *
     * @param string $imageType Image type for container creation
     * @param string $imageTag Image tag for the database version in the container
     *
     * @return string Expected dump SQL
     */
    private function getExpectedDump(string $imageType, string $imageTag): string
    {
        $user = $this->tester->getUsername('mysql');
        $version = preg_replace('/^(\d+\.\d+)\..*/', '$1', $imageTag);
        $defaultSqlMode = $imageType === 'mysql'
            ? [
                'ONLY_FULL_GROUP_BY',
                'STRICT_TRANS_TABLES',
                'NO_ZERO_IN_DATE',
                'NO_ZERO_DATE',
                'ERROR_FOR_DIVISION_BY_ZERO',
                'NO_ENGINE_SUBSTITUTION',
            ]
            : [
                'STRICT_TRANS_TABLES',
                'ERROR_FOR_DIVISION_BY_ZERO',
                'NO_AUTO_CREATE_USER',
                'NO_ENGINE_SUBSTITUTION',
            ];
        $sqlMode = match ("$imageType|$version") {
            'mysql|5.6' => 'NO_ENGINE_SUBSTITUTION',
            'mysql|5.5' => '',
            default => implode(',', $defaultSqlMode),
        };
        $dump = file_get_contents(codecept_data_dir(
            $imageType === 'mysql' ? 'mysql.dump.expected.txt' : 'mariadb.dump.expected.txt',
        ));
        $dump = str_replace(
            [
                '{SQL_MODE}',
                'DEFINER=`user`@`%`',
            ],
            [
                $sqlMode,
                "DEFINER=`$user`@`%`",
            ],
            (string) $dump,
        );

        // Minor dump change for versions before MySQL 8 of for MariaDB
        $majorVersion = (int) explode('.', (string) $version)[0];
        if ($imageType === 'mysql' && $majorVersion < 8 || $imageType === 'mariadb') {
            $dump = str_replace('int ', 'int(11) ', $dump);
        }

        return $dump;
    }
}
