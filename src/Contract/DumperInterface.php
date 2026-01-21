<?php

declare(strict_types=1);

namespace Roslov\MigrationChecker\Contract;

/**
 * Interface: Fetches the database schema/dump.
 */
interface DumperInterface
{
    /**
     * Returns the database dump suitable for comparison.
     *
     * @return StateInterface Dump
     */
    public function getDump(): StateInterface;
}
