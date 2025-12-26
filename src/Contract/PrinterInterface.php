<?php

declare(strict_types=1);

namespace Roslov\MigrationChecker\Contract;

/**
 * Interface: Prints schema state changes.
 */
interface PrinterInterface
{
    /**
     * Prints the differences between two schema states.
     *
     * @param StateInterface $previousState Previous schema state
     * @param StateInterface $currentState Current schema state
     */
    public function displayDiff(StateInterface $previousState, StateInterface $currentState): void;
}
