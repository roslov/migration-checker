Database Migration Checker
==========================

Database Migration Checker validates your database migrations end-to-end. It runs each migration up and down and
verifies that the schema after rolling back is identical to the schema before the migration was applied. This helps you
catch migrations that leave behind tables, columns, indexes, or other schema artifacts.

The package focuses on the core algorithm and exposes framework-agnostic contracts. You implement a few small interfaces
to connect it to your ORM or framework, and it will take care of executing migrations, capturing schema state, comparing
states, and printing readable diffs.


## Requirements

- PHP 8.1 or higher.


## Supported database types and versions

- MySQL 5.5 to 9.5+
- MariaDB 10.0 to 12.1+


## Installation

The package could be installed with composer:

```shell
composer require --dev roslov/migration-checker
```


## How it works

The checker performs the following loop for each pending migration:

1. Capture the current schema state.
2. Run the next migration up.
3. Run the same migration down.
4. Capture the schema state again.
5. Compare the two states and fail if any differences are found.
6. Re-apply the migration up so the next migration can build on it.

If a down migration does not fully revert the changes, the checker prints a unified diff to help you locate the problem.


## Core concepts

You connect the checker to your app by implementing these contracts from namespace `\Roslov\MigrationChecker\Contract`:

- `EnvironmentInterface`
    prepares and cleans up the environment (ensure metadata tables exist, reset caches, etc.).
- `MigrationInterface`
    applies the next migration up, apply the last migration down, and indicate if more migrations exist.
- `QueryInterface` executes queries (used by schema dumpers).
- `PrinterInterface` renders schema diffs when changes are detected.
- `DatabaseDetectorInterface` _(optional)_ detects database type and version.

The checker ships with SQL helpers from namespace `\Roslov\MigrationChecker\Db`:

- `SchemaStateComparer` compares two schema dumps.
- `Dump` automatically detects the database type and dumps its schema.
    It uses `DatabaseDetector` to determine which schema dumper to use:
    - `MySqlDump` dumps the schema for MySQL or MariaDB,
    - `PostgreSqlDump` dumps the schema for PostgreSQL.
- `SqlQuery` fetches data from SQL database via PDO connection.


## General usage

Below is an example of usage with the Symfony framework and Doctrine Migrations. The key idea is to wire the checker into
your framework and run it in a safe environment (typically the test database).

Install [sebastian/diff](https://github.com/sebastianbergmann/diff) if not installed:

```shell
composer require --dev sebastian/diff
```

Add the changes below to your project.

```yaml
# config/services.yaml

services:
    App\Migration\Migration:
        arguments:
            $dependencyFactory: '@doctrine.migrations.dependency_factory'
    App\Migration\Environment:
        arguments:
            $dependencyFactory: '@doctrine.migrations.dependency_factory'
```

```php
# src/Command/CheckMigrationsCommand.php

<?php

declare(strict_types=1);

namespace App\Command;

use App\Migration\Environment;
use App\Migration\Migration;
use App\Migration\Printer;
use App\Migration\SqlQuery;
use Override;
use Roslov\MigrationChecker\Db\DatabaseDetector;
use Roslov\MigrationChecker\Db\Dump;
use Roslov\MigrationChecker\Db\SchemaStateComparer;
use Roslov\MigrationChecker\MigrationChecker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command: Checks migrations
 */
#[AsCommand('app:check-migrations', 'Checks migrations.')]
final class CheckMigrationsCommand extends Command
{
    public function __construct(
        private readonly Environment $environment,
        private readonly Migration $migration,
        private readonly Printer $printer,
        private readonly SqlQuery $query,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $logger = new ConsoleLogger($output);
        $detector = new DatabaseDetector($this->query);
        $dump = new Dump($this->query, $detector);
        $comparer = new SchemaStateComparer($dump);

        $checker = new MigrationChecker(
            $logger,
            $this->environment,
            $this->migration,
            $comparer,
            $this->printer,
        );

        $checker->check();

        return Command::SUCCESS;
    }
}
```

```php
# src/Migration/Environment.php

<?php

declare(strict_types=1);

namespace App\Migration;

use Doctrine\Migrations\DependencyFactory;
use Roslov\MigrationChecker\Contract\EnvironmentInterface;

/**
 * Prepares the database for migration checks.
 */
final class Environment implements EnvironmentInterface
{
    /**
     * Constructor.
     *
     * @param DependencyFactory $dependencyFactory Dependency factory
     */
    public function __construct(private readonly DependencyFactory $dependencyFactory)
    {
    }

    /**
     * Prepares the initial environment for migration checks.
     */
    public function prepare(): void
    {
        $metadataStorage = $this->dependencyFactory->getMetadataStorage();
        $metadataStorage->ensureInitialized();
    }

    /**
     * Cleans up the environment after migration checks.
     */
    public function cleanup(): void
    {
        // No-op
    }
}
```

```php
# src/Migration/Migration.php

<?php

declare(strict_types=1);

namespace App\Migration;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\MigratorConfiguration;
use Doctrine\Migrations\Version\Direction;
use ReflectionClass;
use ReflectionException;
use Roslov\MigrationChecker\Contract\MigrationInterface;

/**
 * Handles database migrations.
 */
final class Migration implements MigrationInterface
{
    public function __construct(private readonly DependencyFactory $dependencyFactory)
    {
    }

    /**
     * Checks whether the next migration exists and can be applied.
     *
     * @return True if there are more migrations to be applied
     */
    public function canUp(): bool
    {
        $statusCalculator = $this->dependencyFactory->getMigrationStatusCalculator();
        $newMigrations = $statusCalculator->getNewMigrations();

        return count($newMigrations) > 0;
    }

    /**
     * Applies the up migration.
     */
    public function up(): void
    {
        $metadataStorage = $this->dependencyFactory->getMetadataStorage();
        $metadataStorage->ensureInitialized();
        $statusCalculator = $this->dependencyFactory->getMigrationStatusCalculator();
        $newMigrations = $statusCalculator->getNewMigrations();
        $firstMigrationPlan = $newMigrations->getItems()[0];
        $version = $firstMigrationPlan->getVersion();
        $planCalculator = $this->dependencyFactory->getMigrationPlanCalculator();
        $plan = $planCalculator->getPlanForVersions([$version], Direction::UP);
        $this->resetMigration($plan->getFirst()->getMigration());
        $migrator = $this->dependencyFactory->getMigrator();
        $migrator->migrate($plan, new MigratorConfiguration());
    }

    /**
     * Applies the down migration.
     */
    public function down(): void
    {
        $metadataStorage = $this->dependencyFactory->getMetadataStorage();
        $executedMigrations = $metadataStorage->getExecutedMigrations();
        $version = $executedMigrations->getLast()->getVersion();
        $planCalculator = $this->dependencyFactory->getMigrationPlanCalculator();
        $plan = $planCalculator->getPlanForVersions([$version], Direction::DOWN);
        $this->resetMigration($plan->getFirst()->getMigration());
        $migrator = $this->dependencyFactory->getMigrator();
        $migrator->migrate($plan, new MigratorConfiguration());
    }

    /**
     * Resets the migration for reuse.
     */
    private function resetMigration(AbstractMigration $migration): void
    {
        $reflection = new ReflectionClass(AbstractMigration::class);
        $property = $reflection->getProperty('plannedSql');
        $property->setValue($migration, []);
        // For newer versions and back-compatibility
        try {
            $property = $reflection->getProperty('frozen');
            $property->setValue($migration, false);
        } catch (ReflectionException) {
        }
    }
}
```

```php
# src/Migration/SqlQuery.php

<?php

declare(strict_types=1);

namespace App\Migration;

use Doctrine\ORM\EntityManagerInterface;
use Roslov\MigrationChecker\Contract\QueryInterface;

/**
 * Fetches data from SQL.
 */
final class SqlQuery implements QueryInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * Executes the query and returns the result as an array
     *
     * @param string $query Query to execute
     * @param array<int|string, mixed> $params Parameters
     *
     * @return list<array<string, scalar>> Result of the query
     */
    public function execute(string $query, array $params = []): array
    {
        $stmt = $this->em->getConnection()->prepare($query);
        foreach ($params as $param => $value) {
            $stmt->bindValue($param, $value);
        }
        return $stmt->executeQuery()->fetchAllAssociative();
    }
}
```

```php
# src/Migration/Printer.php

<?php

declare(strict_types=1);

namespace App\Migration;

use Roslov\MigrationChecker\Contract\PrinterInterface;
use Roslov\MigrationChecker\Contract\StateInterface;
use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\UnifiedDiffOutputBuilder;

/**
 * Prints schema state changes.
 */
final class Printer implements PrinterInterface
{
    /**
     * Prints the differences between two schema states.
     *
     * @param StateInterface $previousState Previous schema state
     * @param StateInterface $currentState Current schema state
     */
    public function displayDiff(StateInterface $previousState, StateInterface $currentState): void
    {
        $differ = new Differ(new UnifiedDiffOutputBuilder());
        echo $differ->diff($previousState->toString(), $currentState->toString());
    }
}
```

Now, you can run the command to check your migrations:

```shell
bin/console app:check-migrations --env=test -vv
```

### Usage tips

- **Run in a test database only.** The checker applies and rolls back migrations repeatedly.
- **Use an empty database.** The dump comparer assumes the schema is fully controlled by migrations.
- **Add it to CI.** Treat a failed check as a build failure so schema regressions are caught early.

Example CI step:

```shell
bin/console app:check-migrations --env=test -vv
```

The output example of the successful run:
```
[info] Migration check started.
[info] Preparing migration environment...
[info] Checking if another migration can be applied...
[info] Saving the current state...
[info] Applying the up migration...
[info] Applying the up migration "DoctrineMigrations\Version20241105145435"...
[info] Applying the down migration...
[info] Applying the down migration "DoctrineMigrations\Version20241105145435"...
[info] Saving the state after up and down migrations...
[info] Comparing the states...
[info] The up and down migrations have been applied successfully without any state changes.
[info] Applying the up migration before the next step...
[info] Applying the up migration "DoctrineMigrations\Version20241105145435"...
[info] Checking if another migration can be applied...
[info] There are no migrations available.
[info] Cleaning up migration environment...
[info] Migration check completed successfully.
```

The output example of the failed run:
```
[info] Migration check started.
[info] Preparing migration environment...
[info] Checking if another migration can be applied...
[info] Saving the current state...
[info] Applying the up migration...
[info] Applying the up migration "DoctrineMigrations\Version20241105145435"...
[info] Applying the down migration...
[info] Applying the down migration "DoctrineMigrations\Version20241105145435"...
[info] Saving the state after up and down migrations...
[info] Comparing the states...
[error] The down migration has resulted in a different schema state after rollback.
--- Original
+++ New
@@ @@
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci


+-- Table:
+event
+
+-- Create Table:
+CREATE TABLE `event` (
+  `id` bigint(20) NOT NULL AUTO_INCREMENT,
+  `microtime` double(16,6) NOT NULL COMMENT 'Unix timestamp with microseconds',
+  `producer_name` varchar(64) NOT NULL COMMENT 'Producer name',
+  `body` varchar(4096) NOT NULL COMMENT 'Full message body',
+  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Creation timestamp',
+  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Update timestamp',
+  PRIMARY KEY (`id`)
+) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Events (transactional outbox)'
+
+
 -- ### Triggers ###


In MigrationChecker.php line 67:

  [Roslov\MigrationChecker\Exception\SchemaDiffersException]
  The up and down migrations have resulted in a different schema state after rollback.
```


## Custom integration outline

If you are not using Symfony/Doctrine, create a small adapter layer that implements the required interfaces and call the
checker directly:

```php
use Psr\Log\NullLogger;
use Roslov\MigrationChecker\Db\DatabaseDetector;
use Roslov\MigrationChecker\Db\Dump;
use Roslov\MigrationChecker\Db\SchemaStateComparer;
use Roslov\MigrationChecker\MigrationChecker;

$environment = new YourEnvironmentAdapter();
$migration = new YourMigrationAdapter();
$query = new YourQueryAdapter();
$printer = new YourPrinterAdapter();

$dump = new Dump($query, new DatabaseDetector($query));
$comparer = new SchemaStateComparer($dump);

$checker = new MigrationChecker(
    new NullLogger(),
    $environment,
    $migration,
    $comparer,
    $printer,
);

$checker->check();
```

Replace the adapters with implementations for your framework or ORM.


## Testing

Tests are located in `tests` directory.
They are developed with [Codeception PHP Testing Framework](http://codeception.com/).

Tests can be executed by running
```sh
./vendor/bin/codecept run
```

inside a container, or

```shell
make test
```

from the host machine.

### Running tests

To execute tests, do the following:

```sh
# Run all available tests
codecept run
# Run unit tests
codecept run Unit
# Run only unit and functional tests
codecept run Unit,Functional
```

### Creating new tests

To create a new test, run one of the following commands:
```sh
codecept g:test Unit UserTest
codecept g:cest Functional ExampleCest
```

### Code coverage support

By default, code coverage is disabled until you enable XDebug.

You can run your tests and collect coverage with the following command:

```sh
# Collect coverage for all tests
XDEBUG_MODE=coverage codecept run --coverage --coverage-html --coverage-xml
```

You can see code coverage output under the `tests/_output` directory.


## Code style analysis

The code style is analyzed with [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) and
[PSR-12 Ext coding standard](https://github.com/roslov/psr12ext). To run code style analysis:

```shell
./vendor/bin/phpcs --extensions=php --colors --standard=PSR12Ext --runtime-set php_version 80100 --ignore=vendor/* -p -s .
```

inside a container, or

```shell
make phpcs
```

from the host machine.
