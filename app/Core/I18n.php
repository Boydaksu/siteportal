<?php

declare(strict_types=1);

namespace SitePortal\Core;

final class I18n
{
    private static ?self $instance = null;

    private string $defaultLocale;
    private string $currentLocale;
    private string $langDir;

    /** @var array<string, array<string, string>> */
    private array $translations = [];

    /** @var list<string> */
    private array $supportedLocales = ['tr-TR', 'en-US'];

    /**
     * @param list<string>|null $supportedLocales
     */
    public function __construct(
        ?string $langDir = null,
        string $defaultLocale = 'tr-TR',
        ?array $supportedLocales = null
    ) {
        $this->langDir = $langDir ?? dirname(__DIR__, 2) . '/resources/lang';
        $this->defaultLocale = $defaultLocale;
        $this->currentLocale = $defaultLocale;
        if ($supportedLocales !== null) {
            $this->supportedLocales = $supportedLocales;
        }

        $this->loadTranslations();
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

    public function getLocale(): string
    {
        return $this->currentLocale;
    }

    public function setLocale(string $locale): void
    {
        if (in_array($locale, $this->supportedLocales, true)) {
            $this->currentLocale = $locale;
        }
    }

    public function getDefaultLocale(): string
    {
        return $this->defaultLocale;
    }

    /** @return list<string> */
    public function getSupportedLocales(): array
    {
        return $this->supportedLocales;
    }

    /**
     * @param array<string, scalar|\Stringable> $params
     */
    public function t(string $key, array $params = [], ?string $locale = null): string
    {
        $targetLocale = $locale ?? $this->currentLocale;

        $text = $this->translations[$targetLocale][$key] ?? null;

        // Fallback to default locale
        if ($text === null && $targetLocale !== $this->defaultLocale) {
            $text = $this->translations[$this->defaultLocale][$key] ?? null;
        }

        // If still not found, return the key itself
        if ($text === null) {
            return $key;
        }

        if (empty($params)) {
            return $text;
        }

        $replacements = [];
        foreach ($params as $paramKey => $paramValue) {
            $replacements['{' . $paramKey . '}'] = (string) $paramValue;
        }

        return strtr($text, $replacements);
    }

    public function has(string $key, ?string $locale = null): bool
    {
        $targetLocale = $locale ?? $this->currentLocale;
        if (isset($this->translations[$targetLocale][$key])) {
            return true;
        }

        return isset($this->translations[$this->defaultLocale][$key]);
    }

    /** @return array<string, string> */
    public function getMessages(string $locale): array
    {
        return $this->translations[$locale] ?? [];
    }

    private function loadTranslations(): void
    {
        foreach ($this->supportedLocales as $locale) {
            $file = $this->langDir . '/' . $locale . '/messages.php';
            if (is_file($file)) {
                $data = require $file;
                if (is_array($data)) {
                    /** @var array<string, string> $data */
                    $this->translations[$locale] = $data;
                }
            }
        }
    }
}
