<?php

declare(strict_types=1);

namespace SitePortal\Core\Database;

use InvalidArgumentException;
use PDO;
use PDOStatement;
use Throwable;

final class Connection
{
    private PDO $pdo;
    private string $driver;

    public function __construct(PDO $pdo, string $driver)
    {
        $this->pdo = $pdo;
        $this->driver = strtolower($driver);
    }

    /**
     * @param array{
     *     driver?: string,
     *     host?: string,
     *     port?: int,
     *     database?: string,
     *     username?: string,
     *     password?: string,
     *     charset?: string,
     *     options?: array<int, mixed>
     * } $config
     */
    public static function create(array $config): self
    {
        $driver = $config['driver'] ?? 'mysql';
        $options = $config['options'] ?? [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        if ($driver === 'sqlite') {
            $database = $config['database'] ?? ':memory:';
            $dsn = 'sqlite:' . $database;
            $pdo = new PDO($dsn, null, null, $options);
            return new self($pdo, 'sqlite');
        }

        if ($driver === 'mysql') {
            $host = $config['host'] ?? '127.0.0.1';
            $port = (int) ($config['port'] ?? 3306);
            $database = $config['database'] ?? 'siteportal';
            $charset = $config['charset'] ?? 'utf8mb4';
            $username = $config['username'] ?? 'siteportal';
            $password = $config['password'] ?? '';

            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
            $pdo = new PDO($dsn, $username, $password, $options);
            return new self($pdo, 'mysql');
        }

        throw new InvalidArgumentException("Desteklenmeyen veritabani surucusu: {$driver}");
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function getDriver(): string
    {
        return $this->driver;
    }

    public function isSqlite(): bool
    {
        return $this->driver === 'sqlite';
    }

    public function isMysql(): bool
    {
        return $this->driver === 'mysql';
    }

    /**
     * @param array<int|string, mixed> $params
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement;
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function fetch(string $sql, array $params = []): ?array
    {
        $statement = $this->query($sql, $params);
        $result = $statement->fetch();
        if ($result === false) {
            return null;
        }

        /** @var array<string, mixed> $result */
        return $result;
    }

    /**
     * @param array<int|string, mixed> $params
     * @return list<array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $statement = $this->query($sql, $params);
        /** @var list<array<string, mixed>> $result */
        $result = $statement->fetchAll();
        return $result;
    }

    /**
     * @param array<int|string, mixed> $params
     */
    public function execute(string $sql, array $params = []): int
    {
        $statement = $this->query($sql, $params);
        return $statement->rowCount();
    }

    public function lastInsertId(?string $name = null): string
    {
        return (string) $this->pdo->lastInsertId($name);
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollBack(): void
    {
        $this->pdo->rollBack();
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    /**
     * @template T
     * @param callable(self): T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (Throwable $e) {
            if ($this->inTransaction()) {
                $this->rollBack();
            }
            throw $e;
        }
    }
}
