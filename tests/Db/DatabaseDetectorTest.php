<?php

declare(strict_types=1);

namespace Roslov\MigrationChecker\Tests\Db;

use Codeception\Attribute\DataProvider;
use Codeception\Test\Unit;
use Roslov\MigrationChecker\Db\DatabaseDetector;
use Roslov\MigrationChecker\Db\SqlQuery;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Tests database detection against different databases
 */
final class DatabaseDetectorTest extends Unit
{
    /**
     * Container name
     */
    private const CONTAINER = 'test-db';

    /**
     * Database name
     */
    private const DATABASE = 'db_test';

    /**
     * User name
     */
    private const USER = 'db_user';

    /**
     * User password
     */
    private const PASSWORD = 'Password123!';

    /**
     * Database host in the local network
     */
    private const HOST = 'host.docker.internal';

    /**
     * External port to connect the database
     */
    private const PORT = 10166;

    /**
     * Database readiness timeout in seconds
     */
    private const READINESS_TIMEOUT = 30;

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
        $this->startDb($imageType, $imageTag);
        $this->waitForDbReadiness($imageType, $imageTag);

        $dsn = $imageType !== 'sqlite'
            ? 'mysql:host=' . self::HOST . ';port=' . self::PORT . ';dbname=' . self::DATABASE . ';charset=utf8mb4'
            : 'sqlite::memory:';
        $query = new SqlQuery($dsn, self::USER, self::PASSWORD);

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
        $this->stopDb();
    }

    /**
     * Stops running containers.
     */
    // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    public function _after(): void
    {
        $this->stopDb();
    }

    /**
     * Returns test cases for type and version detection.
     *
     * @return array{0: int, 1: string[]}[] Test cases
     */
    public static function dbProvider(): array
    {
        $tests = [
            ['mysql', '8.4.5', 'MySQL', '8.4'],
            ['mysql', '8.0.44', 'MySQL', '8.0'],
            ['mysql', '9.5.0', 'MySQL', '9.5'],
            ['mysql', '9.0.1', 'MySQL', '9.0'],
            ['mysql', '5.6.50', 'MySQL', '5.6'],
            ['mysql', '5.5.62', 'MySQL', '5.5'],
            // Version of SQLite is equal to `SQLITE_VERSION` in `docker/Dockerfile`
            ['sqlite', '', 'SQLite', '3.46'],
            ['mariadb', '10.3.39', 'MariaDB', '10.3'],
            ['mariadb', '10.6.24', 'MariaDB', '10.6'],
            ['mariadb', '10.11.15', 'MariaDB', '10.11'],
            ['mariadb', '11.8.5', 'MariaDB', '11.8'],
            ['mariadb', '12.1.2', 'MariaDB', '12.1'],
            // TODO: add postgresql
            // TODO: add sqlserver
            // TODO: add oracle
        ];
        $namedTests = [];
        foreach ($tests as $test) {
            $key = $test[0] . '-' . $test[3];
            $namedTests[$key] = $test;
        }

        return $namedTests;
    }

    /**
     * Starts a database container for testing.
     *
     * @param string $imageType Image type for container creation
     * @param string $imageTag Image tag for the database container
     */
    private function startDb(string $imageType, string $imageTag): void
    {
        $command = $this->getContainerRunCommand($imageType, $imageTag);
        if ($command === null) {
            return;
        }

        $process = new Process($command);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        codecept_debug($process->getOutput());
    }

    /**
     * Stops a database container for testing.
     */
    private function stopDb(): void
    {
        $process = Process::fromShellCommandline('docker rm -f ' . self::CONTAINER . ' || true');
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        codecept_debug($process->getOutput());
    }

    /**
     * Waits until a database is ready for queries.
     *
     * @param string $imageType Image type for container creation
     * @param string $imageTag Image tag for the database container
     */
    private function waitForDbReadiness(string $imageType, string $imageTag): void
    {
        $command = $this->getWaitCommand($imageType, $imageTag);
        if ($command === null) {
            return;
        }

        $container = self::CONTAINER;
        $script = <<<"SCRIPT"
            while ! docker exec '$container' \
                $command \
                >/dev/null 2>&1; do
                echo 'Waiting for database connection...'
                sleep 1
            done
            SCRIPT;

        $process = Process::fromShellCommandline($script);
        $process->setTimeout(self::READINESS_TIMEOUT);
        // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed
        $process->run(static function ($type, $buffer): void {
            codecept_debug($buffer);
        });
    }

    /**
     * Returns the command for database container run.
     *
     * @param string $imageType Image type for container creation
     * @param string $imageTag Image tag for the database container
     *
     * @return string[]|null Command for container run, or null if a container should not be started
     */
    private function getContainerRunCommand(string $imageType, string $imageTag): ?array
    {
        $commands = [
            'mysql' => [
                'docker',
                'run',
                '--name', self::CONTAINER,
                '-d',
                '--rm',
                '-e', 'MYSQL_ROOT_PASSWORD=root_password',
                '-e', 'MYSQL_DATABASE=' . self::DATABASE,
                '-e', 'MYSQL_USER=' . self::USER,
                '-e', 'MYSQL_PASSWORD=' . self::PASSWORD,
                '-p', self::PORT . ':3306',
                'mysql:' . $imageTag,
                '--character-set-server=utf8mb4',
                '--collation-server=utf8mb4_unicode_ci',
                '--log_bin_trust_function_creators=1',
            ],
            'mariadb' => [
                'docker',
                'run',
                '--name', self::CONTAINER,
                '-d',
                '--rm',
                '-e', 'MYSQL_ROOT_PASSWORD=root_password',
                '-e', 'MYSQL_DATABASE=' . self::DATABASE,
                '-e', 'MYSQL_USER=' . self::USER,
                '-e', 'MYSQL_PASSWORD=' . self::PASSWORD,
                '-p', self::PORT . ':3306',
                'mariadb:' . $imageTag,
                '--character-set-server=utf8mb4',
                '--collation-server=utf8mb4_unicode_ci',
            ],
            // TODO: add postgresql
//            'postgresql' => [],
            // TODO: add sqlserver
//            'sqlserver' => [],
            // TODO: add oracle
//            'oracle' => [],
            'sqlite' => null,
        ];

        return $commands[$imageType];
    }

    /**
     * Returns the command for waiting for database readiness.
     *
     * @param string $imageType Image type for container creation
     * @param string $imageTag Image tag for the database container
     *
     * @return string|null Command for waiting, or null if no need to wait
     */
    private function getWaitCommand(string $imageType, string $imageTag): ?string
    {
        $commands = [
            'mysql' => "mysql -u '" . self::USER . "' '-p" . self::PASSWORD . "' -P 3306 -e 'SELECT 1'",
            'mariadb' => "mysql -u '" . self::USER . "' '-p" . self::PASSWORD . "' -P 3306 -e 'SELECT 1'",
            'mariadb11+' => "mariadb -u '" . self::USER . "' '-p" . self::PASSWORD . "' -P 3306 -e 'SELECT 1'",
            // TODO: add postgresql
//            'postgresql' => '',
            // TODO: add sqlserver
//            'sqlserver' => '',
            // TODO: add oracle
//            'oracle' => '',
            'sqlite' => null,
        ];

        $commandKey = $imageType;
        if ($imageType === 'mariadb') {
            $majorVersion = (int) explode('.', $imageTag)[0];
            $commandKey = $majorVersion >= 11 ? 'mariadb11+' : 'mariadb';
        }

        return $commands[$commandKey];
    }
}
