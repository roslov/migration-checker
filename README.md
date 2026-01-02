Database Migration Checker
==========================

This package validates database migrations. It checks whether all up and down migrations run without errors.

It also validates that down migrations revert all changes, so there is no diff in the database after running them.

This package contains the main algorithm and some helpers. You can implement its interfaces and helpers to be used with
different frameworks or ORMs.


## Requirements

- PHP 8.1 or higher.


## Installation

The package could be installed with composer:

```shell
composer require --dev roslov/migration-checker
```


## General usage

Below, there is an example of usage with the Symfony framework.

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
use App\Migration\MySqlQuery;
use App\Migration\Printer;
use Override;
use Roslov\MigrationChecker\Db\MySqlDump;
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
        private readonly MySqlQuery $query,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $logger = new ConsoleLogger($output);
        $dump = new MySqlDump($this->query);
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
    }
}
```

```php
# src/Migration/MySqlQuery.php

<?php

declare(strict_types=1);

namespace App\Migration;

use Doctrine\ORM\EntityManagerInterface;
use Roslov\MigrationChecker\Contract\QueryInterface;

/**
 * Fetches data from MySQL.
 */
final class MySqlQuery implements QueryInterface
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
        return $this->em->getConnection()->prepare($query)->executeQuery($params)->fetchAllAssociative();
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
docker compose exec site bin/console app:check-migrations --env=test -vv
```

Be careful to run it in the test environment, otherwise you can damage your data.

Also, ensure that you run this command on an empty database.

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


+Table: event
+
+Create Table: CREATE TABLE `event` (
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


## Testing

### Unit testing

The package is tested with [PHPUnit](https://phpunit.de/). To run tests:

```shell
./vendor/bin/phpunit
```

### Code style analysis

The code style is analyzed with [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) and
[PSR-12 Ext coding standard](https://github.com/roslov/psr12ext). To run code style analysis:

```shell
./vendor/bin/phpcs --extensions=php --colors --standard=PSR12Ext --ignore=vendor/* -p -s .
```

