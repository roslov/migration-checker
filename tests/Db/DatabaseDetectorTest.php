<?php

declare(strict_types=1);

namespace Roslov\MigrationChecker\Tests\Db;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Roslov\MigrationChecker\Contract\QueryInterface;
use Roslov\MigrationChecker\Db\DatabaseDetector;
use RuntimeException;

/**
 * Tests database detector functionality.
 */
#[CoversClass(DatabaseDetector::class)]
final class DatabaseDetectorTest extends TestCase
{
    /**
     * Tests database type detection.
     *
     * @param string $supportedQuery Query that does not throw an exception (supported by database)
     * @param string $queryResponse Query response from the database, or exception if failed
     * @param string $expectedType Expected database type
     * @param string|null $expectedVersion Expected database version
     */
    #[DataProvider('dbProvider')]
    public function testTypeAndVersion(
        string $supportedQuery,
        string $queryResponse,
        string $expectedType,
        ?string $expectedVersion,
    ): void {
        $query = new class ($supportedQuery, $queryResponse) implements QueryInterface {
            public function __construct(private readonly string $supportedQuery, private readonly string $queryResponse)
            {
            }

            /**
             * @param string $query Query to execute
             * @param mixed[] $params Query parameters
             *
             * @return array<string, string>[] Query response
             */
            // phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClassAfterLastUsed
            public function execute(string $query, array $params = []): array
            {
                if (strtolower($query) === strtolower($this->supportedQuery)) {
                    return [
                        [
                            'column1' => $this->queryResponse,
                        ],
                    ];
                }

                throw new RuntimeException('Query failed.');
            }
            // phpcs:enable Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClassAfterLastUsed
        };

        $detector = new DatabaseDetector($query);

        self::assertSame($expectedType, $detector->getType()->value);
        self::assertSame($expectedVersion, $detector->getVersion());
    }

    /**
     * Returns test cases for type detection.
     *
     * @return array{0: string, 1: array{0: string, 1: string}}[] Test cases
     */
    public static function dbProvider(): array
    {
        return [
            'oracle' => [
                'SELECT * FROM v$version',
                'Oracle Database 21c Express Edition Release 21.0.0.0.0 - Production',
                'Oracle',
                '21.0',
            ],
            'sqlite' => [
                'SELECT sqlite_version()',
                '3.51.1',
                'SQLite',
                '3.51',
            ],
            'sql server' => [
                'SELECT @@VERSION',
                <<<'TXT'
                    Microsoft SQL Server 2022 (RTM-CU22-GDR) (KB5072936) - 16.0.4230.2 (X64)
                        Nov 25 2025 23:31:11
                        Copyright (C) 2022 Microsoft Corporation
                        Developer Edition (64-bit) on Linux (Ubuntu 22.04.5 LTS) <X64>
                    TXT,
                'SQL Server',
                '16.0',
            ],
            'postgresql' => [
                'SELECT version()',
                'PostgreSQL 15.15 on x86_64-pc-linux-musl, compiled by gcc (Alpine 15.2.0) 15.2.0, 64-bit',
                'PostgreSQL',
                '15.15',
            ],
            'mariadb' => [
                'SELECT VERSION()',
                '10.11.15-MariaDB-ubu2204',
                'MariaDB',
                '10.11',
            ],
            'mysql' => [
                'SELECT VERSION()',
                '8.0.44',
                'MySQL',
                '8.0',
            ],
            'unknown' => [
                'invalid',
                '',
                'Unknown',
                null,
            ],
        ];
    }
}
