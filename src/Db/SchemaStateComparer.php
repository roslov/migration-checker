<?php

declare(strict_types=1);

namespace Roslov\MigrationChecker\Db;

use Roslov\MigrationChecker\Contract\DumpInterface;
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
     * @param DumpInterface $dump Dump fetcher
     */
    public function __construct(private readonly DumpInterface $dump)
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
        $this->currentState = $this->dump->getDump();
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
