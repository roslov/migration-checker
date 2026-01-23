<?php

declare(strict_types=1);

namespace Roslov\MigrationChecker\Tests\Unit\Db\Helper;

use Codeception\Attribute\DataProvider;
use Codeception\Test\Unit;
use PHPUnit\Framework\Attributes\CoversClass;
use Roslov\MigrationChecker\Db\Helper\MySqlDdlCanonicalizer;

/**
 * Test canonical table creation conversion.
 */
#[CoversClass(MySqlDdlCanonicalizer::class)]
final class MySqlDdlCanonicalizerTest extends Unit
{
    /**
     * Test canonical table creation conversion.
     *
     * @param string $originalCreateTable Original CREATE TABLE query
     * @param string $expectedCreateTable Expected CREATE TABLE query
     */
    #[DataProvider('tableProvider')]
    public function testCanonicalizeCreateTable(string $originalCreateTable, string $expectedCreateTable): void
    {
        $ddl = new MySqlDdlCanonicalizer();
        self::assertEquals($expectedCreateTable, $ddl->canonicalizeCreateTable($originalCreateTable));
    }

    /**
     * Returns test cases for table conversion.
     *
     * @return array{0: string, 1: string}[] Test cases
     */
    public static function tableProvider(): array
    {
        // phpcs:disable Generic.Files.LineLength.TooLong
        return [
            'non-sorted keys' => [
                <<<'SQL_WRAP'
                    CREATE TABLE `campaign` (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `tenant_id` int(11) NOT NULL COMMENT 'Tenant',
                      `client_id` int(11) NOT NULL COMMENT 'Client who owns the campaign',
                      `user_id` int(11) NOT NULL COMMENT 'User who created the campaign',
                      `name` varchar(200) NOT NULL COMMENT 'Name',
                      `status` enum('draft','ready','active','paused','canceled','completed') NOT NULL DEFAULT 'draft' COMMENT 'Status',
                      `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Creation timestamp',
                      `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Update timestamp',
                      KEY `fk_campaign_user` (`user_id`),
                      KEY `fk_campaign_client` (`client_id`),
                      CONSTRAINT `fk_campaign_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenant` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                      PRIMARY KEY (`id`),
                      KEY `fk_campaign_tenant` (`tenant_id`),
                      CONSTRAINT `fk_campaign_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                      CONSTRAINT `fk_campaign_client` FOREIGN KEY (`client_id`) REFERENCES `client` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Campaigns'
                    SQL_WRAP,
                <<<'SQL_WRAP'
                    CREATE TABLE `campaign` (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `tenant_id` int(11) NOT NULL COMMENT 'Tenant',
                      `client_id` int(11) NOT NULL COMMENT 'Client who owns the campaign',
                      `user_id` int(11) NOT NULL COMMENT 'User who created the campaign',
                      `name` varchar(200) NOT NULL COMMENT 'Name',
                      `status` enum('draft','ready','active','paused','canceled','completed') NOT NULL DEFAULT 'draft' COMMENT 'Status',
                      `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Creation timestamp',
                      `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Update timestamp',
                      PRIMARY KEY (`id`),
                      KEY `fk_campaign_client` (`client_id`),
                      KEY `fk_campaign_tenant` (`tenant_id`),
                      KEY `fk_campaign_user` (`user_id`),
                      CONSTRAINT `fk_campaign_client` FOREIGN KEY (`client_id`) REFERENCES `client` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                      CONSTRAINT `fk_campaign_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenant` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                      CONSTRAINT `fk_campaign_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Campaigns'
                    SQL_WRAP,
            ],
            'sorted keys' => [
                <<<'SQL_WRAP'
                    CREATE TABLE `campaign` (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `tenant_id` int(11) NOT NULL COMMENT 'Tenant',
                      `client_id` int(11) NOT NULL COMMENT 'Client who owns the campaign',
                      `user_id` int(11) NOT NULL COMMENT 'User who created the campaign',
                      `name` varchar(200) NOT NULL COMMENT 'Name',
                      `status` enum('draft','ready','active','paused','canceled','completed') NOT NULL DEFAULT 'draft' COMMENT 'Status',
                      `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Creation timestamp',
                      `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Update timestamp',
                      PRIMARY KEY (`id`),
                      KEY `fk_campaign_client` (`client_id`),
                      KEY `fk_campaign_tenant` (`tenant_id`),
                      KEY `fk_campaign_user` (`user_id`),
                      CONSTRAINT `fk_campaign_client` FOREIGN KEY (`client_id`) REFERENCES `client` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                      CONSTRAINT `fk_campaign_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenant` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                      CONSTRAINT `fk_campaign_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Campaigns'
                    SQL_WRAP,
                <<<'SQL_WRAP'
                    CREATE TABLE `campaign` (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `tenant_id` int(11) NOT NULL COMMENT 'Tenant',
                      `client_id` int(11) NOT NULL COMMENT 'Client who owns the campaign',
                      `user_id` int(11) NOT NULL COMMENT 'User who created the campaign',
                      `name` varchar(200) NOT NULL COMMENT 'Name',
                      `status` enum('draft','ready','active','paused','canceled','completed') NOT NULL DEFAULT 'draft' COMMENT 'Status',
                      `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Creation timestamp',
                      `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Update timestamp',
                      PRIMARY KEY (`id`),
                      KEY `fk_campaign_client` (`client_id`),
                      KEY `fk_campaign_tenant` (`tenant_id`),
                      KEY `fk_campaign_user` (`user_id`),
                      CONSTRAINT `fk_campaign_client` FOREIGN KEY (`client_id`) REFERENCES `client` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                      CONSTRAINT `fk_campaign_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenant` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                      CONSTRAINT `fk_campaign_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Campaigns'
                    SQL_WRAP,
            ],
            'non-table' => [
                <<<'SQL_WRAP'
                    CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`%` SQL SECURITY INVOKER VIEW `vw_api_clients` AS
                    select
                        `client`.`id` AS `clientId`,
                        `client`.`tokenHash` AS `tokenHash`,
                        `client`.`tag` AS `tag`
                    from `client`
                    SQL_WRAP,
                <<<'SQL_WRAP'
                    CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`%` SQL SECURITY INVOKER VIEW `vw_api_clients` AS
                    select
                        `client`.`id` AS `clientId`,
                        `client`.`tokenHash` AS `tokenHash`,
                        `client`.`tag` AS `tag`
                    from `client`
                    SQL_WRAP,
            ],
        ];
        // phpcs:enable Generic.Files.LineLength.TooLong
    }
}
