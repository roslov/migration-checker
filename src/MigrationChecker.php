<?php

declare(strict_types=1);

namespace Roslov\MigrationChecker;

use Psr\Log\LoggerInterface;
use Roslov\MigrationChecker\Contract\DatabaseDetectorInterface;
use Roslov\MigrationChecker\Contract\EnvironmentInterface;
use Roslov\MigrationChecker\Contract\MigrationInterface;
use Roslov\MigrationChecker\Contract\PrinterInterface;
use Roslov\MigrationChecker\Contract\SchemaStateComparerInterface;
use Roslov\MigrationChecker\Exception\SchemaDiffersException;
use Throwable;

/**
 * Checks whether all up and down migrations run without errors.
 */
final class MigrationChecker
{
    /**
     * Constructor.
     *
     * @param LoggerInterface $logger Logger
     * @param EnvironmentInterface $environment Database environment
     * @param MigrationInterface $migration Migration handler
     * @param SchemaStateComparerInterface $comparer Database schema comparer
     * @param PrinterInterface $printer Schema difference printer
     * @param DatabaseDetectorInterface|null $detector Database detector (if needed for debug information)
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly EnvironmentInterface $environment,
        private readonly MigrationInterface $migration,
        private readonly SchemaStateComparerInterface $comparer,
        private readonly PrinterInterface $printer,
        private readonly ?DatabaseDetectorInterface $detector = null,
    ) {
    }

    /**
     * Checks whether all up and down migrations run without errors.
     *
     * @throws Throwable On failure
     */
    public function check(): void
    {
        $this->logger->info('Migration check started.');

        if ($this->detector !== null) {
            $dbType = $this->detector->getType()->value;
            $dbVersion = $this->detector->getVersion();
            $this->logger->info(sprintf('Database type: %s', $dbType));
            $this->logger->info(sprintf('Database version: %s', $dbVersion ?? 'n/a'));
        }

        $this->logger->info('Preparing migration environment...');
        $this->environment->prepare();

        while ($this->canMigrate()) {
            $this->logger->info('Saving the current state...');
            $this->comparer->saveState();

            $this->logger->info('Applying the up migration...');
            $this->migration->up();

            $this->logger->info('Applying the down migration...');
            $this->migration->down();

            $this->logger->info('Saving the state after up and down migrations...');
            $this->comparer->saveState();

            $this->logger->info('Comparing the states...');
            if (!$this->comparer->statesEqual()) {
                $this->logger->error('The down migration has resulted in a different schema state after rollback.');
                $this->printer->displayDiff($this->comparer->getPreviousState(), $this->comparer->getCurrentState());

                throw new SchemaDiffersException(
                    'The up and down migrations have resulted in a different schema state after rollback.',
                );
            }
            $this->logger->info('The up and down migrations have been applied successfully without any state changes.');

            $this->logger->info('Applying the up migration before the next step...');
            $this->migration->up();
        }

        $this->logger->info('Cleaning up migration environment...');
        $this->environment->cleanUp();
        $this->logger->info('Migration check completed successfully.');
    }

    /**
     * Determines if a new migration can be applied.
     *
     * @return bool True if a migration can be applied, false otherwise
     */
    private function canMigrate(): bool
    {
        $this->logger->info('Checking if another migration can be applied...');
        $canMigrate = $this->migration->canUp();
        if (!$canMigrate) {
            $this->logger->info('There are no migrations available.');
        }

        return $canMigrate;
    }
}
