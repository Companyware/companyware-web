<?php

namespace App\View;

use RuntimeException;

final class TemplateRenderer
{
    public function render(string $template, array $context = []): string
    {
        $path = dirname(__DIR__, 2).'/templates/'.$template.'.php';
        if (!is_file($path)) {
            throw new RuntimeException(sprintf('Template "%s" was not found.', $template));
        }
        extract($context, EXTR_SKIP);
        ob_start();
        require $path;
        return (string) ob_get_clean();
    }
}
