<?php

declare(strict_types=1);

namespace Roslov\MigrationChecker\Contract;

/**
 * Interface: Database state object.
 */
interface StateInterface
{
    /**
     * Converts the state to a string representation.
     */
    public function toString(): string;

    /**
     * Checks if the state represents an empty database.
     */
    public function isEmpty(): bool;
}
