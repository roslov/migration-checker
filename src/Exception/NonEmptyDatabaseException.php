<?php

declare(strict_types=1);

namespace Roslov\MigrationChecker\Exception;

/**
 * Exception: The database is not empty.
 *
 * This exception is thrown when a migration check is attempted on a database that is not empty.
 */
final class NonEmptyDatabaseException extends Exception
{
}
