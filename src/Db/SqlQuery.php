<?php

declare(strict_types=1);

namespace Roslov\MigrationChecker\Db;

use PDO;
use Roslov\MigrationChecker\Contract\QueryInterface;
use Roslov\MigrationChecker\Exception\PdoNotFoundException;

/**
 * Fetches data from SQL database via PDO connection.
 */
final class SqlQuery implements QueryInterface
{
    /**
     * PDO instance
     */
    private ?PDO $PDO = null;

    /**
     * Constructor.
     *
     * @param string $dsn PDO DSN (format: `mysql:host=$host;port=$port;dbname=$db;charset=$charset`)
     * @param string $user PDO username
     * @param string $password PDO password
     */
    public function __construct(
        private readonly string $dsn,
        private readonly string $user,
        private readonly string $password,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(string $query, array $params = []): array
    {
        $stmt = $this->getPdo()->prepare($query);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Returns the PDO instance, creating it if necessary.
     *
     * @return PDO PDO instance
     */
    private function getPdo(): PDO
    {
        if (!class_exists(PDO::class)) {
            throw new PdoNotFoundException('PDO extension is not installed.');
        }
        if ($this->PDO === null) {
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $this->PDO = new PDO($this->dsn, $this->user, $this->password, $options);
        }

        return $this->PDO;
    }
}
