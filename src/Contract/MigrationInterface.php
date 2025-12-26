<?php

declare(strict_types=1);

namespace Roslov\MigrationChecker\Contract;

/**
 * Interface: Handles database migrations.
 */
interface MigrationInterface
{
    /**
     * Checks whether the next migration exists and can be applied.
     */
    public function canUp(): bool;

    /**
     * Applies the up migration.
     */
    public function up(): void;

    /**
     * Applies the down migration.
     */
    public function down(): void;
}
