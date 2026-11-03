<?php

declare(strict_types=1);

namespace SitePortal\Core\Database;

use RuntimeException;

final class MigrationRunner
{
    private Connection $db;
    private string $migrationsPath;

    public function __construct(Connection $db, ?string $migrationsPath = null)
    {
        $this->db = $db;
        $this->migrationsPath = $migrationsPath ?? dirname(__DIR__, 3) . '/database/migrations';
    }

    public function ensureMigrationsTable(): void
    {
        if ($this->db->isSqlite()) {
            $sql = 'CREATE TABLE IF NOT EXISTS migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration VARCHAR(255) NOT NULL UNIQUE,
                batch INTEGER NOT NULL,
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )';
        } else {
            $sql = 'CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                batch INT NOT NULL,
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        }

        $this->db->query($sql);
    }

    /**
     * @return list<string>
     */
    public function migrate(): array
    {
        $this->ensureMigrationsTable();
        $applied = $this->getAppliedMap();
        $files = $this->getMigrationFiles();

        $pending = [];
        foreach ($files as $name => $path) {
            if (!isset($applied[$name])) {
                $pending[$name] = $path;
            }
        }

        if (empty($pending)) {
            return [];
        }

        $batch = $this->getNextBatchNumber();
        $migrated = [];

        foreach ($pending as $name => $filePath) {
            $instance = $this->loadMigrationInstance($filePath);
            $instance->up($this->db);

            $this->db->query(
                'INSERT INTO migrations (migration, batch) VALUES (:migration, :batch)',
                ['migration' => $name, 'batch' => $batch]
            );

            $migrated[] = $name;
        }

        return $migrated;
    }

    /**
     * @return list<string>
     */
    public function rollback(): array
    {
        $this->ensureMigrationsTable();
        $lastBatch = $this->getLastBatchNumber();
        if ($lastBatch === null) {
            return [];
        }

        $rows = $this->db->fetchAll(
            'SELECT migration FROM migrations WHERE batch = :batch ORDER BY id DESC',
            ['batch' => $lastBatch]
        );

        $files = $this->getMigrationFiles();
        $rolledBack = [];

        foreach ($rows as $row) {
            $name = (string) $row['migration'];
            if (isset($files[$name])) {
                $instance = $this->loadMigrationInstance($files[$name]);
                $instance->down($this->db);
            }

            $this->db->query(
                'DELETE FROM migrations WHERE migration = :migration',
                ['migration' => $name]
            );

            $rolledBack[] = $name;
        }

        return $rolledBack;
    }

    /**
     * @return list<string>
     */
    public function reset(): array
    {
        $this->ensureMigrationsTable();
        $rows = $this->db->fetchAll('SELECT migration, batch FROM migrations ORDER BY id DESC');
        $files = $this->getMigrationFiles();
        $rolledBack = [];

        foreach ($rows as $row) {
            $name = (string) $row['migration'];
            if (isset($files[$name])) {
                $instance = $this->loadMigrationInstance($files[$name]);
                $instance->down($this->db);
            }

            $this->db->query(
                'DELETE FROM migrations WHERE migration = :migration',
                ['migration' => $name]
            );

            $rolledBack[] = $name;
        }

        return $rolledBack;
    }

    /**
     * @return list<array{
     *     migration: string,
     *     applied: bool,
     *     batch: ?int,
     *     executed_at: ?string,
     *     description: string
     * }>
     */
    public function status(): array
    {
        $this->ensureMigrationsTable();
        $applied = $this->getAppliedMap();
        $files = $this->getMigrationFiles();

        $statusList = [];
        foreach ($files as $name => $path) {
            $instance = $this->loadMigrationInstance($path);
            $isApplied = isset($applied[$name]);

            $statusList[] = [
                'migration' => $name,
                'applied' => $isApplied,
                'batch' => $isApplied ? (int) $applied[$name]['batch'] : null,
                'executed_at' => $isApplied ? (string) $applied[$name]['executed_at'] : null,
                'description' => $instance->getDescription(),
            ];
        }

        return $statusList;
    }

    /**
     * @return array<string, string>
     */
    public function getMigrationFiles(): array
    {
        if (!is_dir($this->migrationsPath)) {
            return [];
        }

        $files = scandir($this->migrationsPath);
        if ($files === false) {
            return [];
        }

        $migrations = [];
        sort($files);

        foreach ($files as $file) {
            if (str_ends_with($file, '.php')) {
                $name = basename($file, '.php');
                $migrations[$name] = $this->migrationsPath . '/' . $file;
            }
        }

        return $migrations;
    }

    /**
     * @return array<string, array{batch: int, executed_at: string}>
     */
    private function getAppliedMap(): array
    {
        $rows = $this->db->fetchAll('SELECT migration, batch, executed_at FROM migrations ORDER BY id ASC');
        $map = [];
        foreach ($rows as $row) {
            $name = (string) $row['migration'];
            $map[$name] = [
                'batch' => (int) $row['batch'],
                'executed_at' => (string) $row['executed_at'],
            ];
        }

        return $map;
    }

    private function getNextBatchNumber(): int
    {
        $last = $this->getLastBatchNumber();
        return $last === null ? 1 : $last + 1;
    }

    private function getLastBatchNumber(): ?int
    {
        $row = $this->db->fetch('SELECT MAX(batch) as max_batch FROM migrations');
        if ($row === null || $row['max_batch'] === null) {
            return null;
        }

        return (int) $row['max_batch'];
    }

    private function loadMigrationInstance(string $filePath): MigrationInterface
    {
        $declaredClasses = get_declared_classes();
        require_once $filePath;
        $newClasses = array_diff(get_declared_classes(), $declaredClasses);

        foreach ($newClasses as $class) {
            if (is_subclass_of($class, MigrationInterface::class)) {
                /** @var MigrationInterface $instance */
                $instance = new $class();
                return $instance;
            }
        }

        // If class was already declared (e.g. earlier in process)
        $baseName = basename($filePath, '.php');
        $parts = explode('_', $baseName);
        array_shift($parts); // remove prefix like 0001
        $className = str_replace(' ', '', ucwords(implode(' ', $parts)));

        $possibleClasses = [
            "SitePortal\\Database\\Migrations\\{$className}",
            $className,
        ];

        foreach ($possibleClasses as $fqcn) {
            if (class_exists($fqcn) && is_subclass_of($fqcn, MigrationInterface::class)) {
                /** @var MigrationInterface $instance */
                return new $fqcn();
            }
        }

        throw new RuntimeException("Gecerli bir MigrationInterface sinifi bulunamadi: {$filePath}");
    }
}
