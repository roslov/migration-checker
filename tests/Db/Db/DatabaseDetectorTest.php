<?php

declare(strict_types=1);

namespace Roslov\MigrationChecker\Tests\Db\Db;

use Codeception\Attribute\DataProvider;
use Codeception\Test\Unit;
use Roslov\MigrationChecker\Db\DatabaseDetector;
use Roslov\MigrationChecker\Db\SqlQuery;
use Roslov\MigrationChecker\Tests\Support\DbTester;

/**
 * Tests database detection against different databases.
 */
final class DatabaseDetectorTest extends Unit
{
    /**
     * @var DbTester Tester
     */
    protected DbTester $tester;

    /**
     * Tests database type and version detection from real databases.
     *
     * @param string $imageType Image type for container creation
     * @param string $imageTag Image tag for the database version in the container
     * @param string $expectedType Expected database type
     * @param string $expectedVersion Expected database version
     */
    #[DataProvider('dbProvider')]
    public function testTypeAndVersion(
        string $imageType,
        string $imageTag,
        string $expectedType,
        string $expectedVersion,
    ): void {
        $I = $this->tester;
        $I->startDb($imageType, $imageTag);
        $I->waitForDbReadiness($imageType, $imageTag);

        $query = new SqlQuery(
            $I->getDsn($imageType),
            $I->getUsername($imageType),
            $I->getPassword(),
        );
        $detector = new DatabaseDetector($query);
        self::assertSame($expectedType, $detector->getType()->value);
        self::assertSame($expectedVersion, $detector->getVersion());
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
     * Returns test cases for type and version detection.
     *
     * @return array<string, string[]> Test cases
     */
    public static function dbProvider(): array
    {
        $tests = [
            ['mysql', '9.5.0', 'MySQL', '9.5'],
            ['mysql', '9.0.1', 'MySQL', '9.0'],
            ['mysql', '8.4.5', 'MySQL', '8.4'],
            ['mysql', '8.0.44', 'MySQL', '8.0'],
            ['mysql', '5.6.50', 'MySQL', '5.6'],
            ['mysql', '5.5.62', 'MySQL', '5.5'],

            // Version of SQLite is equal to `SQLITE_VERSION` in `docker/Dockerfile`
            ['sqlite', '', 'SQLite', '3.46'],

            ['mariadb', '12.1.2', 'MariaDB', '12.1'],
            ['mariadb', '11.8.5', 'MariaDB', '11.8'],
            ['mariadb', '10.11.15', 'MariaDB', '10.11'],
            ['mariadb', '10.3.39', 'MariaDB', '10.3'],

            ['postgresql', '18.1-alpine', 'PostgreSQL', '18.1'],
            ['postgresql', '17.7-alpine', 'PostgreSQL', '17.7'],
            ['postgresql', '15.15-alpine', 'PostgreSQL', '15.15'],
            ['postgresql', '14.20-alpine', 'PostgreSQL', '14.20'],
            ['postgresql', '11.22-alpine', 'PostgreSQL', '11.22'],

            ['sqlserver', '2022-CU21-ubuntu-22.04', 'SQL Server', '16.0'],
            ['sqlserver', '2019-CU32-ubuntu-20.04', 'SQL Server', '15.0'],
            ['sqlserver', '2017-CU31-ubuntu-18.04', 'SQL Server', '14.0'],

            ['oracle', '21.3.0-slim', 'Oracle', '21.0'],
            ['oracle', '18.4.0-slim', 'Oracle', '18.0'],
            ['oracle', '11.2.0.2-slim', 'Oracle', '11.2'],
        ];
        $namedTests = [];
        foreach ($tests as $test) {
            $key = implode('-', array_filter([$test[0], $test[1]]));
            $namedTests[$key] = $test;
        }

        return $namedTests;
    }
}
