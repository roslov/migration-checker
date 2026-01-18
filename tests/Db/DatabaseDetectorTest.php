<?php

declare(strict_types=1);

namespace Roslov\MigrationChecker\Tests\Db;

use Codeception\Attribute\DataProvider;
use Codeception\Test\Unit;
use InvalidArgumentException;
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

        $dsn = $this->getDsn($imageType);
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
            ['postgresql', '14.20-alpine', 'PostgreSQL', '14.20'],
            ['postgresql', '15.15-alpine', 'PostgreSQL', '15.15'],
            ['postgresql', '16.11-alpine', 'PostgreSQL', '16.11'],
            ['postgresql', '17.7-alpine', 'PostgreSQL', '17.7'],
            ['postgresql', '18.1-alpine', 'PostgreSQL', '18.1'],
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
        codecept_debug('Starting the test DB...');
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
        codecept_debug('Stopping the test DB...');
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
        return match ($imageType) {
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
            'postgresql' => [
                'docker',
                'run',
                '--name', self::CONTAINER,
                '-d',
                '--rm',
                '-e', 'POSTGRES_DB=' . self::DATABASE,
                '-e', 'POSTGRES_USER=' . self::USER,
                '-e', 'POSTGRES_PASSWORD=' . self::PASSWORD,
                '-p', self::PORT . ':5432',
                'postgres:' . $imageTag,
            ],
            // TODO: add sqlserver
//            'sqlserver' => [],
            // TODO: add oracle
//            'oracle' => [],
            'sqlite' => null,
            default => throw new InvalidArgumentException('Invalid image type.'),
        };
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
        $commandKey = $imageType;
        if ($imageType === 'mariadb') {
            $majorVersion = (int) explode('.', $imageTag)[0];
            $commandKey = $majorVersion >= 11 ? 'mariadb11+' : 'mariadb';
        }
        $user = self::USER;
        $encodedUser = urlencode($user);
        $password = self::PASSWORD;
        $encodedPassword = urlencode($password);
        $db = self::DATABASE;

        return match ($commandKey) {
            'mysql', 'mariadb' => "mysql -u '$user' '-p$password' -P 3306 -e 'SELECT 1'",
            'mariadb11+' => "mariadb -u '$user' '-p$password' -P 3306 -e 'SELECT 1'",
            'postgresql' => "psql -X 'postgresql://$encodedUser:$encodedPassword@127.0.0.1:5432/$db' -c 'SELECT 1'",
            // TODO: add sqlserver
//            'sqlserver' => '',
            // TODO: add oracle
//            'oracle' => '',
            'sqlite' => null,
            default => throw new InvalidArgumentException('Invalid image type.'),
        };
    }

    /**
     * Returns DSN.
     *
     * @param string $imageType Image type for container creation
     *
     * @return string DSN
     */
    private function getDsn(string $imageType): string
    {
        $host = self::HOST;
        $port = self::PORT;
        $db = self::DATABASE;

        return match ($imageType) {
            'mysql', 'mariadb' => "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",
            'postgresql' => "pgsql:host=$host;port=$port;dbname=$db",
            // TODO: add sqlserver
//            'sqlserver' => '',
            // TODO: add oracle
//            'oracle' => '',
            'sqlite' => 'sqlite::memory:',
            default => throw new InvalidArgumentException('Invalid image type.'),
        };
    }
}
