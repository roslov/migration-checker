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
    public function convertToString(): string;
}
