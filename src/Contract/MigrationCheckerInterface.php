<?php

declare(strict_types=1);

namespace Roslov\MigrationChecker\Contract;

use Throwable;

/**
 * Interface: Checks whether all up and down migrations run without errors.
 */
interface MigrationCheckerInterface
{
    /**
     * Checks whether all up and down migrations run without errors.
     *
     * @throws Throwable On failure
     */
    public function check(): void;
}
