<?php

declare(strict_types=1);

namespace Roslov\MigrationChecker\Db;

use Roslov\MigrationChecker\Contract\StateInterface;

/**
 * State
 */
final class State implements StateInterface
{
    /**
     * Constructor.
     *
     * @param string $dump Dump
     */
    public function __construct(private readonly string $dump)
    {
    }

    /**
     * @inheritDoc
     */
    public function toString(): string
    {
        return $this->dump;
    }

    /**
     * @inheritDoc
     */
    public function isEmpty(): bool
    {
        return trim($this->dump) === '';
    }
}
