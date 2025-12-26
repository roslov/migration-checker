<?php

declare(strict_types=1);

namespace Roslov\MigrationChecker\Contract;

/**
 * Interface: Handles database schema comparison.
 */
interface SchemaStateComparerInterface
{
    /**
     * Saves the current schema state.
     *
     * It saves the current state, but the previously saved state is also kept for future comparison.
     */
    public function saveState(): void;

    /**
     * Compares the current and previous states.
     *
     * @return bool Whether states are equal (true if there are no differences, false otherwise)
     */
    public function statesEqual(): bool;

    /**
     * Returns the current schema state.
     *
     * @return StateInterface The current schema state
     */
    public function getCurrentState(): StateInterface;

    /**
     * Returns the previous schema state.
     *
     * @return StateInterface The previous schema state
     */
    public function getPreviousState(): StateInterface;
}
