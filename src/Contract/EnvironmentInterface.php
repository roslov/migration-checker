<?php

declare(strict_types=1);

namespace Roslov\MigrationChecker\Contract;

/**
 * Interface: Prepares the database for migration checks.
 */
interface EnvironmentInterface
{
    /**
     * Prepares the initial environment for migration checks.
     */
    public function prepare(): void;

    /**
     * Cleans up the environment after migration checks.
     */
    public function cleanup(): void;
}
