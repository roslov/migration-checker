<?php

declare(strict_types=1);

namespace Roslov\MigrationChecker;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Roslov\MigrationChecker\Contract\DatabaseDetectorInterface;
use Roslov\MigrationChecker\Contract\EnvironmentInterface;
use Roslov\MigrationChecker\Contract\MigrationCheckerInterface;
use Roslov\MigrationChecker\Contract\MigrationInterface;
use Roslov\MigrationChecker\Contract\PrinterInterface;
use Roslov\MigrationChecker\Contract\SchemaStateComparerInterface;
use Roslov\MigrationChecker\Exception\NonEmptyDatabaseException;
use Roslov\MigrationChecker\Exception\SchemaDiffersException;

/**
 * Checks whether all up and down migrations run without errors.
 */
final class MigrationChecker implements MigrationCheckerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Constructor.
     *
     * @param EnvironmentInterface $environment Database environment
     * @param MigrationInterface $migration Migration handler
     * @param SchemaStateComparerInterface $comparer Database schema comparer
     * @param PrinterInterface $printer Schema difference printer
     * @param DatabaseDetectorInterface|null $detector Database detector (if needed for debug information)
     * @param LoggerInterface|null $logger Logger
     */
    public function __construct(
        private readonly EnvironmentInterface $environment,
        private readonly MigrationInterface $migration,
        private readonly SchemaStateComparerInterface $comparer,
        private readonly PrinterInterface $printer,
        private readonly ?DatabaseDetectorInterface $detector = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->setLogger($logger ?? new NullLogger());
    }

    /**
     * @inheritDoc
     */
    public function check(): void
    {
        $logger = $this->getLogger();
        $logger->info('Migration check started.');

        if ($this->detector instanceof DatabaseDetectorInterface) {
            $dbType = $this->detector->getType()->value;
            $dbVersion = $this->detector->getVersion();
            $logger->info(sprintf('Database type: %s', $dbType));
            $logger->info(sprintf('Database version: %s', $dbVersion ?? 'n/a'));
        }

        $logger->info('Checking if database is empty before running migrations...');
        if (!$this->isEmpty()) {
            throw new NonEmptyDatabaseException('The check should only be run on an empty database.');
        }

        $logger->info('Preparing migration environment...');
        $this->environment->prepare();

        while ($this->canMigrate()) {
            $logger->info('Saving the current state...');
            $this->comparer->saveState();

            $logger->info('Applying the up migration...');
            $this->migration->up();

            $logger->info('Applying the down migration...');
            $this->migration->down();

            $logger->info('Saving the state after up and down migrations...');
            $this->comparer->saveState();

            $logger->info('Comparing the states...');
            if (!$this->comparer->statesEqual()) {
                $logger->error('The down migration has resulted in a different schema state after rollback.');
                $this->printer->displayDiff($this->comparer->getPreviousState(), $this->comparer->getCurrentState());

                throw new SchemaDiffersException(
                    'The up and down migrations have resulted in a different schema state after rollback.',
                );
            }
            $logger->info('The up and down migrations have been applied successfully without any state changes.');

            $logger->info('Applying the up migration before the next step...');
            $this->migration->up();
        }

        $logger->info('Cleaning up migration environment...');
        $this->environment->cleanUp();
        $logger->info('Migration check completed successfully.');
    }

    /**
     * Determines if a new migration can be applied.
     *
     * @return bool True if a migration can be applied, false otherwise
     */
    private function canMigrate(): bool
    {
        $logger = $this->getLogger();
        $logger->info('Checking if another migration can be applied...');
        $canMigrate = $this->migration->canUp();
        if (!$canMigrate) {
            $logger->info('There are no migrations available.');
        }

        return $canMigrate;
    }

    /**
     * Return the logger.
     *
     * @return LoggerInterface Logger
     */
    private function getLogger(): LoggerInterface
    {
        if (!$this->logger instanceof LoggerInterface) {
            $this->logger = new NullLogger();
        }

        return $this->logger;
    }

    /**
     * Checks if the database is empty.
     *
     * @return bool True if the database is empty, false otherwise
     */
    private function isEmpty(): bool
    {
        $this->comparer->saveState();

        return $this->comparer->getCurrentState()->isEmpty();
    }
}
