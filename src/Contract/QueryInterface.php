<?php

declare(strict_types=1);

namespace Roslov\MigrationChecker\Contract;

/**
 * Interface: Fetches data from a source
 */
interface QueryInterface
{
    /**
     * Executes the query and returns the result as an array
     *
     * @param string $query Query to execute
     * @param array<int|string, mixed> $params Parameters
     *
     * @return array<string, scalar>[] Result of the query
     */
    public function execute(string $query, array $params = []): array;
}
