<?php

declare(strict_types=1);

namespace CrewWeaver\Core;

use RuntimeException;

class View
{
    /* ============================================================
     * PROTECTED VARIABLES
     * ============================================================ */
    protected string $_viewPath;

    /* ============================================================
     * PUBLIC VARIABLES
     * ============================================================ */

    /* ============================================================
     * MAGIC METHODS
     * ============================================================ */
    public function __construct(string $viewPath)
    {
        $this->_viewPath = rtrim($viewPath, DIRECTORY_SEPARATOR);
    }

    /* ============================================================
     * PROTECTED METHODS
     * ============================================================ */
    protected function _renderFile(string $file, array $data): string
    {
        if (!is_file($file)) {
            throw new RuntimeException('View not found: ' . $file);
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function render(string $template, array $data = [], string $layout = 'Layouts/application'): Response
    {
        $content = $this->_renderFile($this->_viewPath . DIRECTORY_SEPARATOR . $template . '.php', $data);
        $html = $this->_renderFile($this->_viewPath . DIRECTORY_SEPARATOR . $layout . '.php', $data + ['content' => $content]);
        return new Response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public function fragment(string $template, array $data = []): string
    {
        return $this->_renderFile($this->_viewPath . DIRECTORY_SEPARATOR . $template . '.php', $data);
    }
}
