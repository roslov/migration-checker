<?php

declare(strict_types=1);

namespace Roslov\MigrationChecker\Db\Helper;

/**
 * Converts MySQL DDL queries to canonical ones.
 *
 * It is used for better dump comparison.
 */
final class MySqlDdlCanonicalizer
{
    /**
     * Returns the canonical CREATE TABLE with sorted KEY/CONSTRAINT lines, stable across MySQL/MariaDB internal
     * ordering.
     *
     * @param string $originalDdl Original CREATE TABLE query
     *
     * @return string Canonical CREATE TABLE query
     */
    public function canonicalizeCreateTable(string $originalDdl): string
    {
        $ddl = str_replace("\r\n", "\n", trim($originalDdl));

        $parts = $this->splitDdl($ddl);
        if ($parts === null) {
            return $originalDdl;
        }
        [$header, $body, $footer] = $parts;

        [$columns, $primaryKeys, $keys, $constraints, $others] = $this->extractColumnsAndKeys($body);

        // Sorts KEY and CONSTRAINT deterministically by their name (or full line fallback)
        usort(
            $keys,
            fn (string $a, string $b): int => strcmp($this->extractKeyName($a), $this->extractKeyName($b)),
        );
        usort(
            $constraints,
            fn (string $a, string $b): int => strcmp(
                $this->extractConstraintName($a),
                $this->extractConstraintName($b),
            ),
        );
        sort($others);

        // Reassembles with consistent comma placement
        $out = array_merge($columns, $primaryKeys, $keys, $constraints, $others);
        $out = $this->ensureCommas($out);

        // Normalizes footer a bit (optional): collapse whitespace
        $footer = (string) preg_replace('/[ \t]+/', ' ', $footer);

        return $header . implode("\n", $out) . $footer;
    }

    /**
     * Splits DDL into header, body, and footer.
     *
     * @param string $ddl DDL
     *
     * @return array{0: string, 1: string, 2: string}|null Header, body, and footer or null if not a table
     */
    private function splitDdl(string $ddl): ?array
    {
        // Splits into: header "CREATE TABLE ... (", body lines, footer ") ENGINE=..."
        if (preg_match('/\A(.*?\(\n)(.*)(\n\)\s*.*)\z/s', $ddl, $matches)) {
            return [$matches[1], $matches[2], $matches[3]];
        }
        // In case there is no newline right after "("
        if (preg_match('/\A(.*?\()(.*)(\)\s*.*)\z/s', $ddl, $matches)) {
            return [$matches[1], $matches[2], $matches[3]];
        }

        return null;
    }

    /**
     * Splits the body into columns, primary keys, keys, constraints, and others.
     *
     * @param string $body Body
     *
     * @return array{
     *     0: string[],
     *     1: string[],
     *     2: string[],
     *     3: string[],
     *     4: string[]
     * } Columns, primary keys, keys, constraints, and others
     */
    private function extractColumnsAndKeys(string $body): array
    {
        $lines = $this->splitBodyLines($body);

        $columns = [];
        $primaryKeys = [];
        $keys = [];
        $constraints = [];
        $others = [];

        foreach ($lines as $line) {
            $trimmedLine = ltrim($line);
            if ($trimmedLine === '') {
                continue;
            }

            $normalizedLine = $this->normalizeWhitespace($line);
            match (true) {
                str_starts_with($trimmedLine, '`') => $columns[] = $normalizedLine,
                str_starts_with($trimmedLine, 'PRIMARY KEY') => $primaryKeys[] = $normalizedLine,
                (bool) preg_match(
                    '/^(UNIQUE KEY|KEY|FULLTEXT KEY|SPATIAL KEY)\s+`/i',
                    $trimmedLine,
                ) => $keys[] = $normalizedLine,
                (bool) preg_match('/^CONSTRAINT\s+`/i', $trimmedLine) => $constraints[] = $normalizedLine,
                default => $others[] = $normalizedLine,
            };
        }

        return [$columns, $primaryKeys, $keys, $constraints, $others];
    }

    /**
     * Splits body lines.
     *
     * @param string $body Body
     *
     * @return string[] Body split into lines
     */
    private function splitBodyLines(string $body): array
    {
        $body = trim($body, "\n");

        // Splits on newlines (each line in SHOW CREATE is already separate)
        $raw = explode("\n", $body);

        // Trims trailing commas but keep indentation; we'll re-add commas consistently later
        $lines = [];
        foreach ($raw as $line) {
            $line = rtrim($line);
            $line = preg_replace('/,\s*\z/', '', $line);
            $lines[] = (string) $line;
        }

        return $lines;
    }

    /**
     * Normalizes whitespace in the line.
     *
     * @param string $line Line
     *
     * @return string Normalized line
     */
    private function normalizeWhitespace(string $line): string
    {
        // Keeps leading indentation, normalize internal multiple spaces
        $indent = '';
        if (preg_match('/\A(\s+)/', $line, $matches)) {
            $indent = $matches[1];
        }
        $trimmedLine = trim($line);
        $trimmedLine = preg_replace('/[ \t]+/', ' ', $trimmedLine);

        return $indent . $trimmedLine;
    }

    /**
     * Extracts the key name.
     *
     * @param string $line Line
     *
     * @return string Key name
     */
    private function extractKeyName(string $line): string
    {
        // KEY `name` ...
        if (preg_match('/\b(KEY|UNIQUE KEY|FULLTEXT KEY|SPATIAL KEY)\s+`([^`]+)`/i', $line, $matches)) {
            return strtolower($matches[2]);
        }

        return strtolower(trim($line));
    }

    /**
     * Extracts the constraint name.
     *
     * @param string $line Line
     *
     * @return string Key name
     */
    private function extractConstraintName(string $line): string
    {
        // CONSTRAINT `name` ...
        if (preg_match('/\bCONSTRAINT\s+`([^`]+)`/i', $line, $matches)) {
            return strtolower($matches[1]);
        }

        return strtolower(trim($line));
    }

    /**
     * Ensures that all trailing commas are placed correctly in the DDL query.
     *
     * @param string[] $lines Lines
     *
     * @return string[] Lines with commas (where needed)
     */
    private function ensureCommas(array $lines): array
    {
        $lineCount = count($lines);
        if ($lineCount === 0) {
            return $lines;
        }

        $out = [];
        foreach ($lines as $key => $line) {
            $processedLine = rtrim($line);
            if ($key < $lineCount - 1) {
                $processedLine .= ',';
            }
            $out[] = $processedLine;
        }

        return $out;
    }
}
