<?php

declare(strict_types=1);

namespace SitePortal\Core;

final class Config
{
    private static ?self $instance = null;

    /** @var array<string, mixed> */
    private array $items = [];

    private string $configPath;

    public function __construct(?string $configPath = null)
    {
        $this->configPath = $configPath ?? dirname(__DIR__, 2) . '/config';
        $this->loadConfigFiles();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function setInstance(?self $instance): void
    {
        self::$instance = $instance;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $current = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }
            /** @var mixed $current */
            $current = $current[$segment];
        }

        return $current;
    }

    public function has(string $key): bool
    {
        $segments = explode('.', $key);
        $current = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return false;
            }
            /** @var mixed $current */
            $current = $current[$segment];
        }

        return true;
    }

    public function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $current = &$this->items;

        foreach ($segments as $segment) {
            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                $current[$segment] = [];
            }
            $current = &$current[$segment];
        }

        $current = $value;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->items;
    }

    private function loadConfigFiles(): void
    {
        if (!is_dir($this->configPath)) {
            return;
        }

        $files = scandir($this->configPath);
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            if (str_ends_with($file, '.php')) {
                $group = basename($file, '.php');
                $filePath = $this->configPath . '/' . $file;
                $configData = require $filePath;
                if (is_array($configData)) {
                    /** @var array<string, mixed> $configData */
                    $this->items[$group] = $configData;
                }
            }
        }
    }
}
