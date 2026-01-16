<?php

declare(strict_types=1);

namespace Roslov\MigrationChecker\Enum;

/**
 * Enum: Database type.
 */
enum DatabaseType: string
{
    /**
     * Type: MySQL
     */
    case MySql = 'MySQL';

    /**
     * Type: MariaDB
     */
    case MariaDd = 'MariaDB';

    /**
     * Type: PostgreSQL
     */
    case PostgreSql = 'PostgreSQL';

    /**
     * Type: Oracle
     */
    case Oracle = 'Oracle';

    /**
     * Type: SQLite
     */
    case SqLite = 'SQLite';

    /**
     * Type: SQL Server
     */
    case SqlServer = 'SQL Server';

    /**
     * Type: Unknown
     */
    case Unknown = 'Unknown';
}
