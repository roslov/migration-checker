<?php

declare(strict_types=1);

namespace Roslov\MigrationChecker\Contract;

use Roslov\MigrationChecker\Enum\DatabaseType;

/**
 * Interface: Detects the database type and version for migration compatibility checks.
 */
interface DatabaseDetectorInterface
{
    /**
     * Retrieves the type of the database.
     *
     * @return DatabaseType The type of the database
     */
    public function getType(): DatabaseType;

    /**
     * Retrieves the version of the database.
     *
     * @return string|null The version of the database in X.Y format (e.g., `8.4`)
     */
    public function getVersion(): ?string;
}
