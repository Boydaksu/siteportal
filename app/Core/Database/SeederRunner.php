<?php

declare(strict_types=1);

namespace SitePortal\Core\Database;

use RuntimeException;

final class SeederRunner
{
    private Connection $db;
    private string $seedersPath;

    public function __construct(Connection $db, ?string $seedersPath = null)
    {
        $this->db = $db;
        $this->seedersPath = $seedersPath ?? dirname(__DIR__, 3) . '/database/seeders';
    }

    /**
     * @return list<string>
     */
    public function run(): array
    {
        $files = $this->getSeederFiles();
        $executed = [];

        foreach ($files as $filePath) {
            $seeder = $this->loadSeederInstance($filePath);
            $seeder->run($this->db);
            $executed[] = $seeder->getName();
        }

        return $executed;
    }

    /**
     * @return list<string>
     */
    public function getSeederFiles(): array
    {
        if (!is_dir($this->seedersPath)) {
            return [];
        }

        $files = scandir($this->seedersPath);
        if ($files === false) {
            return [];
        }

        $seeders = [];
        sort($files);

        foreach ($files as $file) {
            if (str_ends_with($file, '.php')) {
                $seeders[] = $this->seedersPath . '/' . $file;
            }
        }

        return $seeders;
    }

    private function loadSeederInstance(string $filePath): SeederInterface
    {
        $declaredClasses = get_declared_classes();
        require_once $filePath;
        $newClasses = array_diff(get_declared_classes(), $declaredClasses);

        foreach ($newClasses as $class) {
            if (is_subclass_of($class, SeederInterface::class)) {
                /** @var SeederInterface $instance */
                $instance = new $class();
                return $instance;
            }
        }

        // If class was already declared
        $baseName = basename($filePath, '.php');
        $parts = explode('_', $baseName);
        if (count($parts) > 1 && is_numeric($parts[0])) {
            array_shift($parts);
        }
        $className = str_replace(' ', '', ucwords(implode(' ', $parts)));

        $possibleClasses = [
            "SitePortal\\Database\\Seeders\\{$className}",
            $className,
            $baseName,
        ];

        foreach ($possibleClasses as $fqcn) {
            if (class_exists($fqcn) && is_subclass_of($fqcn, SeederInterface::class)) {
                /** @var SeederInterface $instance */
                return new $fqcn();
            }
        }

        throw new RuntimeException("Gecerli bir SeederInterface sinifi bulunamadi: {$filePath}");
    }
}
