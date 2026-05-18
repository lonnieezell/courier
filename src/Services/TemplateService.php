<?php

declare(strict_types=1);

namespace Myth\Courier\Services;

use InvalidArgumentException;

/**
 * Renders email HTML using CI4's view() system.
 */
class TemplateService
{
    /**
     * Renders the email body view, then optionally wraps it in a layout.
     *
     * When $layoutPath is null the content view is returned directly.
     * The layout receives $content (the rendered body) plus all $data keys.
     *
     * @param array<string, mixed> $data
     *
     * @throws InvalidArgumentException if either view path cannot be rendered
     */
    public function render(string $viewPath, ?string $layoutPath, array $data = []): string
    {
        $body = $this->renderView($viewPath, $data);

        if ($layoutPath === null) {
            return $body;
        }

        $data['content'] = $body;

        return $this->renderView($layoutPath, $data);
    }

    /**
     * Renders a view for plain-text use: strips HTML tags and normalises whitespace.
     *
     * @param array<string, mixed> $data
     *
     * @throws InvalidArgumentException if the view path cannot be rendered
     */
    public function renderText(string $viewPath, array $data = []): string
    {
        $html = $this->renderView($viewPath, $data);
        $text = strip_tags($html);

        // Collapse multiple blank lines and trim surrounding whitespace
        $text = (string) preg_replace('/\n{3,}/', "\n\n", $text);
        $text = (string) preg_replace('/[ \t]+/', ' ', $text);

        return trim($text);
    }

    /**
     * Thin wrapper around CI's view() that converts rendering failures
     * into a consistent InvalidArgumentException.
     *
     * @param array<string, mixed> $data
     *
     * @throws InvalidArgumentException
     */
    private function renderView(string $path, array $data): string
    {
        try {
            return view($path, $data);
        } catch (\Throwable $e) {
            throw new InvalidArgumentException(
                "Courier: could not render view \"{$path}\": " . $e->getMessage(),
                0,
                $e,
            );
        }
    }
}
