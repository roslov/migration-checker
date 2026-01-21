<?php

declare(strict_types=1);

namespace Roslov\MigrationChecker\Db;

use Roslov\MigrationChecker\Contract\DumperInterface;
use Roslov\MigrationChecker\Contract\SchemaStateComparerInterface;
use Roslov\MigrationChecker\Contract\StateInterface;

/**
 * Handles database schema comparison.
 */
final class SchemaStateComparer implements SchemaStateComparerInterface
{
    /**
     * Current state of the schema
     */
    private StateInterface $currentState;

    /**
     * Previous state of the schema
     */
    private StateInterface $previousState;

    /**
     * Constructor.
     *
     * @param DumperInterface $dumper Dump fetcher
     */
    public function __construct(private readonly DumperInterface $dumper)
    {
        $this->currentState = new State('');
        $this->previousState = new State('');
    }

    /**
     * @inheritDoc
     */
    public function saveState(): void
    {
        $this->previousState = $this->currentState;
        $this->currentState = $this->dumper->getDump();
    }

    /**
     * @inheritDoc
     */
    public function statesEqual(): bool
    {
        return $this->getCurrentState()->toString() === $this->getPreviousState()->toString();
    }

    /**
     * @inheritDoc
     */
    public function getCurrentState(): StateInterface
    {
        return $this->currentState;
    }

    /**
     * @inheritDoc
     */
    public function getPreviousState(): StateInterface
    {
        return $this->previousState;
    }
}
