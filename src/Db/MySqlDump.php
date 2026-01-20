<?php

declare(strict_types=1);

namespace Roslov\MigrationChecker\Db;

use Roslov\MigrationChecker\Contract\DumpInterface;
use Roslov\MigrationChecker\Contract\QueryInterface;
use Roslov\MigrationChecker\Contract\StateInterface;
use Roslov\MigrationChecker\Db\Helper\MySqlDdlCanonicalizer;
use Roslov\MigrationChecker\Exception\NoDatabaseUsedException;

/**
 * Fetches the MySQL dump.
 */
final class MySqlDump implements DumpInterface
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
        $tables = $this->getTableDump();
        $views = $this->getViewDump();
        $triggers = $this->getTriggerDump();
        $proceduresAndFunctions = $this->getProcedureAndFunctionDump();
        $events = $this->getEventDump();
        $dump = <<<DUMP
            -- ### Tables ###
            $tables

            -- ### Views ###
            $views

            -- ### Triggers ###
            $triggers

            -- ### Procedures and functions ###
            $proceduresAndFunctions

            -- ### Events ###
            $events
            DUMP;

        return new State(trim($dump));
    }

    /**
     * Dumps tables.
     *
     * @return string The dumped tables
     */
    private function getTableDump(): string
    {
        $sql = <<<'SQL'
            SELECT table_name AS table_name, table_type AS table_type
            FROM information_schema.tables
            WHERE table_schema = :dbName AND TABLE_TYPE = 'BASE TABLE'
            ORDER BY table_name;
            SQL;
        $tables = $this->query->execute($sql, ['dbName' => $this->getDbName()]);
        $dump = [];
        foreach ($tables as $row) {
            $sql = sprintf('SHOW CREATE TABLE `%s`', $row['table_name']);
            $entry = $this->query->execute($sql)[0] ?? [];
            if (isset($entry['Create Table'])) {
                $entry['Create Table'] = $this->canonicalizeCreateTable($entry['Create Table']);
            }
            $rowDump = $this->dumpRow($entry);
            $dump[] = $this->removeAutoIncrement($rowDump);
        }

        return implode("\n\n", $dump);
    }

    /**
     * Dumps views.
     *
     * @return string The dumped views
     */
    private function getViewDump(): string
    {
        $sql = <<<'SQL'
            SELECT table_name AS table_name, table_type AS table_type
            FROM information_schema.tables
            WHERE table_schema = :dbName AND TABLE_TYPE = 'VIEW'
            ORDER BY table_name;
            SQL;
        $views = $this->query->execute($sql, ['dbName' => $this->getDbName()]);
        $dump = [];
        foreach ($views as $row) {
            $sql = sprintf('SHOW CREATE VIEW `%s`', $row['table_name']);
            $dump[] = $this->dumpRow($this->query->execute($sql)[0] ?? []);
        }

        return implode("\n\n", $dump);
    }

    /**
     * Dumps triggers.
     *
     * @return string The dumped triggers
     */
    private function getTriggerDump(): string
    {
        $sql = <<<'SQL'
            SELECT trigger_name AS trigger_name, event_object_table AS event_object_table
            FROM information_schema.triggers
            WHERE trigger_schema = :dbName
            ORDER BY trigger_name, event_object_table;
            SQL;
        $triggers = $this->query->execute($sql, ['dbName' => $this->getDbName()]);
        $dump = [];
        foreach ($triggers as $row) {
            $sql = sprintf('SHOW CREATE TRIGGER `%s`', $row['trigger_name']);
            $entry = $this->query->execute($sql)[0] ?? [];
            if (isset($entry['Created'])) {
                unset($entry['Created']);
            }
            $dump[] = $this->dumpRow($entry);
        }

        return implode("\n\n", $dump);
    }

    /**
     * Dumps procedures and functions.
     *
     * @return string The dumped procedures and functions
     */
    private function getProcedureAndFunctionDump(): string
    {
        $sql = <<<'SQL'
            SELECT routine_type AS routine_type, routine_name AS routine_name
            FROM information_schema.routines
            WHERE routine_schema = :dbName
            ORDER BY routine_type, routine_name;
            SQL;
        $procedureFunctions = $this->query->execute($sql, ['dbName' => $this->getDbName()]);
        $dump = [];
        foreach ($procedureFunctions as $row) {
            $sql = sprintf('SHOW CREATE %s `%s`', $row['routine_type'], $row['routine_name']);
            $dump[] = $this->dumpRow($this->query->execute($sql)[0] ?? []);
        }

        return implode("\n\n", $dump);
    }

    /**
     * Dumps events.
     *
     * @return string The dumped events
     */
    private function getEventDump(): string
    {
        $sql = <<<'SQL'
            SELECT event_name AS event_name, event_definition AS event_definition, status AS status
            FROM information_schema.events
            WHERE event_schema = :dbName
            ORDER BY event_name, event_definition, status;
            SQL;
        $events = $this->query->execute($sql, ['dbName' => $this->getDbName()]);
        $dump = [];
        foreach ($events as $row) {
            $sql = sprintf('SHOW CREATE EVENT `%s`', $row['event_name']);
            $dump[] = $this->dumpRow($this->query->execute($sql)[0] ?? []);
        }

        return implode("\n\n", $dump);
    }

    /**
     * Returns the current database name.
     *
     * @throws NoDatabaseUsedException If no database is used
     */
    private function getDbName(): string
    {
        $row = $this->query->execute('SELECT DATABASE()')[0]
        ?? throw new NoDatabaseUsedException('Use a database first.');

        return reset($row) ?: throw new NoDatabaseUsedException('Cannot get the database name.');
    }

    /**
     * Dumps the row into a string representation.
     *
     * @param array<string, scalar> $row The row from the query result
     *
     * @return string The dump of the row
     */
    private function dumpRow(array $row): string
    {
        $dump = [];
        foreach ($row as $field => $value) {
            $dump[] = "-- $field:\n$value\n";
        }

        return implode("\n", $dump);
    }

    /**
     * Removes the auto-increment property from a given row dump.
     *
     * @param string $sql The SQL dump of a database row
     *
     * @return string The modified row dump with the auto-increment property removed
     */
    private function removeAutoIncrement(string $sql): string
    {
        return preg_replace('/\s+AUTO_INCREMENT *= *\d+(\s+)/i', '$1', $sql);
    }

    /**
     * Canonicalizes the CREATE TABLE query.
     *
     * @param string $sql The CREATE TABLE query
     *
     * @return string The canonized CREATE TABLE query
     */
    private function canonicalizeCreateTable(string $sql): string
    {
        return (new MySqlDdlCanonicalizer())->canonicalizeCreateTable($sql);
    }
}
