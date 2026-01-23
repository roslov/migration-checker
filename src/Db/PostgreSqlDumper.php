<?php

declare(strict_types=1);

namespace Roslov\MigrationChecker\Db;

use Roslov\MigrationChecker\Contract\DumperInterface;
use Roslov\MigrationChecker\Contract\QueryInterface;
use Roslov\MigrationChecker\Contract\StateInterface;

/**
 * Fetches the PostgreSQL dump.
 */
final class PostgreSqlDumper implements DumperInterface
{
    /**
     * Constructor.
     *
     * @param QueryInterface $query Query fetcher
     */
    public function __construct(private readonly QueryInterface $query)
    {
    }

    /**
     * @inheritDoc
     */
    public function getDump(): StateInterface
    {
        $sql = <<<'SQL_WRAP'
            -- /* PostgreSQL 11, 12, 13, 14, 15, 16, 17, 18 Schema Dump Script */
            WITH schema_dump AS (
                -- 0. SEQUENCES
                SELECT
                    0 AS sort_order,
                    sequencename AS sort_name,
                    'CREATE SEQUENCE IF NOT EXISTS ' || sequencename ||
                    ' START WITH ' || start_value ||
                    ' INCREMENT BY ' || increment_by ||
                    ' MINVALUE ' || min_value ||
                    ' MAXVALUE ' || max_value ||
                    ' CACHE ' || cache_size ||
                    CASE WHEN cycle THEN ' CYCLE' ELSE ' NO CYCLE' END || ';' AS ddl
                FROM pg_sequences
                WHERE schemaname = 'public'

                UNION ALL

                -- 1. FUNCTIONS (prokind = 'f')
                SELECT
                    1 AS sort_order,
                    p.proname AS sort_name,
                    pg_get_functiondef(p.oid) || ';' AS ddl
                FROM pg_proc p
                JOIN pg_namespace n ON n.oid = p.pronamespace
                WHERE n.nspname = 'public'
                AND p.proname NOT LIKE 'pg_%'
                AND p.prokind = 'f'

                UNION ALL

                -- 1.5. PROCEDURES (prokind = 'p')
                SELECT
                    1.5 AS sort_order,
                    p.proname AS sort_name,
                    pg_get_functiondef(p.oid) || ';' AS ddl
                FROM pg_proc p
                JOIN pg_namespace n ON n.oid = p.pronamespace
                WHERE n.nspname = 'public'
                AND p.proname NOT LIKE 'pg_%'
                AND p.prokind = 'p'

                UNION ALL

                -- 2. TABLES
                SELECT
                    2 AS sort_order,
                    table_name AS sort_name,
                    'CREATE TABLE IF NOT EXISTS ' || table_name || ' (' ||
                    string_agg(
                        column_name || ' ' ||
                        data_type ||
                        CASE
                            WHEN character_maximum_length IS NOT NULL THEN '(' || character_maximum_length || ')'
                            ELSE ''
                        END ||
                        CASE
                            WHEN column_default IS NOT NULL THEN ' DEFAULT ' || column_default
                            ELSE ''
                        END ||
                        CASE
                            WHEN is_nullable = 'NO' THEN ' NOT NULL'
                            ELSE ''
                        END,
                        ', ' ORDER BY ordinal_position
                    ) || ');' AS ddl
                FROM information_schema.columns
                WHERE table_schema = 'public'
                GROUP BY table_name

                UNION ALL

                -- 3. CONSTRAINTS
                SELECT
                    3 AS sort_order,
                    c.relname || '_' || con.conname AS sort_name,
                    'ALTER TABLE ' || n.nspname || '.' || c.relname ||
                    ' ADD CONSTRAINT ' || con.conname || ' ' ||
                    pg_get_constraintdef(con.oid) || ';' AS ddl
                FROM pg_constraint con
                JOIN pg_class c ON con.conrelid = c.oid
                JOIN pg_namespace n ON n.oid = c.relnamespace
                WHERE n.nspname = 'public'

                UNION ALL

                -- 4. INDEXES
                SELECT
                    4 AS sort_order,
                    tablename || '_' || indexname AS sort_name,
                    indexdef || ';' AS ddl
                FROM pg_indexes
                WHERE schemaname = 'public'
                AND indexname NOT LIKE '%_pkey'

                UNION ALL

                -- 5. VIEWS
                SELECT
                    5 AS sort_order,
                    c.relname AS sort_name,
                    'CREATE OR REPLACE VIEW ' || c.relname || ' AS ' || pg_get_viewdef(c.oid) AS ddl
                FROM pg_class c
                JOIN pg_namespace n ON n.oid = c.relnamespace
                WHERE n.nspname = 'public' AND c.relkind = 'v'

                UNION ALL

                -- 6. TRIGGERS
                SELECT
                    6 AS sort_order,
                    c.relname || '_' || t.tgname AS sort_name,
                    pg_get_triggerdef(t.oid) || ';' AS ddl
                FROM pg_trigger t
                JOIN pg_class c ON t.tgrelid = c.oid
                JOIN pg_namespace n ON c.relnamespace = n.oid
                WHERE n.nspname = 'public' AND t.tgisinternal = FALSE
            )
            SELECT ddl
            FROM schema_dump
            ORDER BY sort_order, sort_name, ddl;
            SQL_WRAP;
        $rows = $this->query->execute($sql);
        $ddl = array_column($rows, 'ddl');
        $dump = trim(implode("\n", $ddl));

        return new State($dump);
    }
}
