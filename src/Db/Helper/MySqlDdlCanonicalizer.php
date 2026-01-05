<?php

declare(strict_types=1);

namespace Roslov\MigrationChecker\Db\Helper;

/**
 * Converts MySQL DDL queries to canonical ones.
 *
 * It is used for better dump comparison.
 *
 * @todo Refactor
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
        $ddl = $originalDdl;
        $ddl = str_replace("\r\n", "\n", trim($ddl));

        // Splits into: header "CREATE TABLE ... (", body lines, footer ") ENGINE=..."
        if (!preg_match('/\A(.*?\(\n)(.*)(\n\)\s*.*)\z/s', $ddl, $matches)) {
            // In case there is no newline right after "("
            if (!preg_match('/\A(.*?\()(.*)(\)\s*.*)\z/s', $ddl, $matches2)) {
                return $originalDdl;
            }
            [, $header, $body, $footer] = $matches2;
        } else {
            [, $header, $body, $footer] = $matches;
        }

        [$columns, $primaryKeys, $keys, $constraints, $others] = $this->extractColumnsAndKeys($body);

        // Sorts KEY and CONSTRAINT deterministically by their name (or full line fallback)
        usort(
            $keys,
            fn ($a, $b) => strcmp($this->extractKeyName($a), $this->extractKeyName($b)),
        );
        usort(
            $constraints,
            fn ($a, $b) => strcmp($this->extractConstraintName($a), $this->extractConstraintName($b)),
        );
        sort($others);

        // Reassembles with consistent comma placement
        $out = array_merge($columns, $primaryKeys, $keys, $constraints, $others);
        $out = $this->ensureCommas($out);

        // Normalizes footer a bit (optional): collapse whitespace
        $footer = preg_replace('/[ \t]+/', ' ', $footer);

        return $header . implode("\n", $out) . $footer;
    }

    /**
     * Splits the body into columns, primary keys, keys, constraints, and others.
     *
     * @param string $body Body
     *
     * @return array{
     *     0: string,
     *     1: string,
     *     2: string,
     *     3: string,
     *     4: string
     * }[] Columns, primary keys, keys, constraints, and others
     *
     * @todo Decrease cognitive complexity
     */
    // phpcs:disable SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh
    private function extractColumnsAndKeys(string $body): array
    {
        $lines = $this->splitBodyLines($body);

        $columns = [];
        $primaryKeys = [];
        $keys = [];
        $constraints = [];
        // checks, etc. (if present). We’ll keep but sort by line
        $others = [];

        foreach ($lines as $line) {
            $trimmedLine = ltrim($line);

            if ($trimmedLine === '') {
                continue;
            }

            // Column lines usually start with a backtick: `col` ...
            if (str_starts_with($trimmedLine, '`')) {
                $columns[] = $this->normalizeWhitespace($line);

                continue;
            }

            if (str_starts_with($trimmedLine, 'PRIMARY KEY')) {
                $primaryKeys[] = $this->normalizeWhitespace($line);

                continue;
            }

            if (preg_match('/^(UNIQUE KEY|KEY|FULLTEXT KEY|SPATIAL KEY)\s+`/i', $trimmedLine)) {
                $keys[] = $this->normalizeWhitespace($line);

                continue;
            }

            if (preg_match('/^CONSTRAINT\s+`/i', $trimmedLine)) {
                $constraints[] = $this->normalizeWhitespace($line);

                continue;
            }

            // Everything else: CHECK, etc.
            $others[] = $this->normalizeWhitespace($line);
        }

        return [$columns, $primaryKeys, $keys, $constraints, $others];
    }
    // phpcs:enable SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh

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
            $lines[] = $line;
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
        if (!$lineCount) {
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
