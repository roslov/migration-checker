<?php

declare(strict_types=1);

namespace Db;

use Codeception\Attribute\DataProvider;
use Codeception\Test\Unit;
use Roslov\MigrationChecker\Contract\EnvironmentInterface;
use Roslov\MigrationChecker\Contract\MigrationInterface;
use Roslov\MigrationChecker\Contract\PrinterInterface;
use Roslov\MigrationChecker\Db\DatabaseDetector;
use Roslov\MigrationChecker\Db\Dumper;
use Roslov\MigrationChecker\Db\SchemaStateComparer;
use Roslov\MigrationChecker\Db\SqlQuery;
use Roslov\MigrationChecker\MigrationChecker;
use Roslov\MigrationChecker\Tests\Support\DbTester;

/**
 * Tests migration checker run.
 */
final class MigrationCheckerTest extends Unit
{
    /**
     * @var DbTester Tester
     */
    protected DbTester $tester;

    /**
     * Tests migration checker run.
     *
     * It runs the migration checker against different databases to ensure it does not fail.
     *
     * @param string $imageType Image type for container creation
     * @param string $imageTag Image tag for the database version in the container
     */
    #[DataProvider('dbProvider')]
    public function testCheck(string $imageType, string $imageTag): void
    {
        $I = $this->tester;
        $I->startDb($imageType, $imageTag);
        $I->waitForDbReadiness($imageType, $imageTag);

        $query = new SqlQuery(
            $I->getDsn($imageType),
            $I->getUsername($imageType),
            $I->getPassword(),
        );
        $environment = $this->createStub(EnvironmentInterface::class);
        $migration = $this->createStub(MigrationInterface::class);
        $detector = new DatabaseDetector($query);
        $dumper = new Dumper($query, $detector);
        $comparer = new SchemaStateComparer($dumper);
        $printer = $this->createStub(PrinterInterface::class);
        $checker = new MigrationChecker($environment, $migration, $comparer, $printer, $detector);
        $checker->check();
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
     * Returns test cases for migration checks.
     *
     * @return array<string, string[]> Test cases
     */
    public static function dbProvider(): array
    {
        $tests = [
            ['mysql', '8.4.5'],
            ['mariadb', '10.11.15'],
            ['postgresql', '17.7-alpine'],
//            ['sqlite', ''],
//            ['sqlserver', '2022-CU21-ubuntu-22.04'],
//            ['oracle', '21.3.0-slim'],
        ];
        $namedTests = [];
        foreach ($tests as $test) {
            $key = $test[0];
            $namedTests[$key] = $test;
        }

        return $namedTests;
    }
}
