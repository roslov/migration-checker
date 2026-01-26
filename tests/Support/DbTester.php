<?php

declare(strict_types=1);

namespace Roslov\MigrationChecker\Tests\Support;

use Codeception\Actor;
use InvalidArgumentException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Inherited Methods
 *
 * @method void wantTo($text)
 * @method void wantToTest($text)
 * @method void execute($callable)
 * @method void expectTo($prediction)
 * @method void expect($prediction)
 * @method void amGoingTo($argumentation)
 * @method void am($role)
 * @method void lookForwardTo($achieveValue)
 * @method void comment($description)
 * @method void pause($vars = [])
 * @SuppressWarnings(PHPMD)
 */
final class DbTester extends Actor
{
    use _generated\DbTesterActions;

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
    private const PASSWORD = 'Password123_';

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
    private const READINESS_TIMEOUT = 120;

    /**
     * Database preparation timeout in seconds
     */
    private const SETUP_TIMEOUT = 600;

    /**
     * Additional delay for MySQL server readiness in seconds to bypass the error
     * `SQLSTATE[HY000] [2006] MySQL server has gone away`
     */
    private const MYSQL_DELAY = 4;

    /**
     * Returns PDO DSN.
     *
     * @param string $imageType Image type for container creation
     *
     * @return string DSN
     */
    public function getDsn(string $imageType): string
    {
        $host = self::HOST;
        $port = self::PORT;
        $db = self::DATABASE;

        return match ($imageType) {
            'mysql', 'mariadb' => "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",
            'postgresql' => "pgsql:host=$host;port=$port;dbname=$db",
            'sqlserver' => "sqlsrv:Server=$host,$port;TrustServerCertificate=yes",
            'oracle' => "oci:dbname=//$host:$port/XE",
            'sqlite' => 'sqlite::memory:',
            default => throw new InvalidArgumentException('Invalid image type.'),
        };
    }

    /**
     * Returns the database username.
     *
     * @param string $imageType Image type for container creation
     *
     * @return string Username
     */
    public function getUsername(string $imageType): string
    {
        return match ($imageType) {
            'sqlserver' => 'sa',
            'oracle' => 'SYSTEM',
            default => self::USER,
        };
    }

    /**
     * Returns the database password.
     *
     * @return string Password
     */
    public function getPassword(): string
    {
        return self::PASSWORD;
    }

    /**
     * Starts a database container for testing.
     *
     * @param string $imageType Image type for container creation
     * @param string $imageTag Image tag for the database container
     */
    public function startDb(string $imageType, string $imageTag): void
    {
        codecept_debug('Starting the test DB...');
        $command = $this->getContainerRunCommand($imageType, $imageTag);
        if ($command === null) {
            return;
        }

        $process = new Process($command);
        $process->setTimeout(self::SETUP_TIMEOUT);
        $this->debugCommand($process);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        codecept_debug($process->getOutput());
    }

    /**
     * Stops a database container for testing.
     */
    public function stopDb(): void
    {
        codecept_debug('Stopping the test DB...');
        $process = Process::fromShellCommandline('docker rm -f ' . self::CONTAINER . ' || true');
        $process->setTimeout(self::SETUP_TIMEOUT);
        $this->debugCommand($process);
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
    public function waitForDbReadiness(string $imageType, string $imageTag): void
    {
        $command = $this->getWaitCommand($imageType, $imageTag);
        if ($command === null) {
            return;
        }

        $container = self::CONTAINER;
        $script = <<<"SCRIPT"
            while ! docker exec '$container' $command; do
                echo 'Waiting for database connection...'
                sleep 1
            done
            SCRIPT;
        if ($imageType === 'mysql') {
            $delay = self::MYSQL_DELAY;
            $script = <<<"SCRIPT"
                $script
                echo 'Waiting additional $delay seconds for MySQL server...'
                sleep $delay
                SCRIPT;
        }

        $process = Process::fromShellCommandline($script);
        $process->setTimeout(self::READINESS_TIMEOUT);
        $this->debugCommand($process);
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
                'docker', 'run', '--name', self::CONTAINER, '-d', '--rm',
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
                'docker', 'run', '--name', self::CONTAINER, '-d', '--rm',
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
                'docker', 'run', '--name', self::CONTAINER, '-d', '--rm',
                '-e', 'POSTGRES_DB=' . self::DATABASE,
                '-e', 'POSTGRES_USER=' . self::USER,
                '-e', 'POSTGRES_PASSWORD=' . self::PASSWORD,
                '-p', self::PORT . ':5432',
                'postgres:' . $imageTag,
            ],
            'sqlserver' => [
                'docker', 'run', '--name', self::CONTAINER, '-d', '--rm',
                '-e', 'ACCEPT_EULA=Y',
                '-e', 'MSSQL_SA_PASSWORD=' . self::PASSWORD,
                '-p', self::PORT . ':1433',
                'mcr.microsoft.com/mssql/server:' . $imageTag,
            ],
            'oracle' => [
                'docker', 'run', '--name', self::CONTAINER, '-d', '--rm',
                '-e', 'ORACLE_PASSWORD=' . self::PASSWORD,
                '-p', self::PORT . ':1521',
                'gvenzl/oracle-xe:' . $imageTag,
            ],
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
        $user = self::USER;
        $encodedUser = urlencode($user);
        $password = self::PASSWORD;
        $encodedPassword = urlencode($password);
        $db = self::DATABASE;
        $commandKey = $this->getCommandKey($imageType, $imageTag);

        return match ($commandKey) {
            'mysql', 'mariadb' => "mysql -u '$user' '-p$password' -P 3306 -e 'SELECT 1'",
            'mariadb11+' => "mariadb -u '$user' '-p$password' -P 3306 -e 'SELECT 1'",
            'postgresql' => "psql -X 'postgresql://$encodedUser:$encodedPassword@127.0.0.1:5432/$db' -c 'SELECT 1'",
            'sqlserver' => "/opt/mssql-tools/bin/sqlcmd -S 127.0.0.1 -U sa -P '$password' -C -Q 'SELECT 1'",
            'sqlserver2019' => "/opt/mssql-tools18/bin/sqlcmd -S 127.0.0.1 -U sa -P '$password' -C -Q 'SELECT 1'",
            'oracle' => <<<COMMAND
                sh -c "echo 'exit' | sqlplus -L 'SYSTEM/$password@//127.0.0.1:1521/XE'"
                COMMAND,
            'sqlite' => null,
            default => throw new InvalidArgumentException('Invalid image type.'),
        };
    }

    /**
     * Debugs the command line of a process.
     *
     * @param Process $process Process to debug
     */
    private function debugCommand(Process $process): void
    {
        codecept_debug('Command: ' . $process->getCommandLine());
    }

    /**
     * Returns the command key for the wait command based on the image type and tag.
     *
     * @param string $imageType Image type for container creation
     * @param string $imageTag Image tag for the database container
     *
     * @return string Command key for the wait command
     */
    private function getCommandKey(string $imageType, string $imageTag): string
    {
        return match ($imageType) {
            'mariadb' => $this->getCommandKeyForMariaDb($imageTag),
            'sqlserver' => $this->getCommandKeyForSqlServer($imageTag),
            default => $imageType,
        };
    }

    /**
     * Returns the command key for the wait command based on the MariaDB image tag.
     *
     * @param string $imageTag Image tag for the database container
     *
     * @return string Command key for the wait command
     */
    private function getCommandKeyForMariaDb(string $imageTag): string
    {
        $majorVersion = (int) explode('.', $imageTag)[0];

        return $majorVersion >= 11 ? 'mariadb11+' : 'mariadb';
    }

    /**
     * Returns the command key for the wait command based on the SQL Server image tag.
     *
     * @param string $imageTag Image tag for the database container
     *
     * @return string Command key for the wait command
     */
    private function getCommandKeyForSqlServer(string $imageTag): string
    {
        $majorVersion = (int) explode('-', $imageTag)[0];

        return $majorVersion >= 2019 ? 'sqlserver2019' : 'sqlserver';
    }
}
