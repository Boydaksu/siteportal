<?php

declare(strict_types=1);

namespace SitePortal\Core;

use RuntimeException;

final class View
{
    private string $viewsPath;

    public function __construct(?string $viewsPath = null)
    {
        $this->viewsPath = $viewsPath ?? dirname(__DIR__, 2) . '/resources/views';
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $view, array $data = []): string
    {
        $file = $this->viewsPath . '/' . str_replace('.', '/', $view) . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("Görünüm şablonu bulunamadı: {$view} ({$file})");
        }

        return $this->renderFile($file, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function renderWithLayout(string $view, array $data = [], string $layout = 'layout'): string
    {
        $content = $this->render($view, $data);
        $layoutData = array_merge($data, ['content' => $content]);

        return $this->render($layout, $layoutData);
    }

    public static function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderFile(string $file, array $data): string
    {
        $closure = static function (string $templateFile, array $templateData): string {
            extract($templateData, EXTR_SKIP);
            ob_start();
            try {
                require $templateFile;
                return (string) ob_get_clean();
            } catch (\Throwable $e) {
                if (ob_get_level() > 0) {
                    ob_end_clean();
                }
                throw $e;
            }
        };

        return $closure($file, $data);
    }
}
