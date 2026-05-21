<?php

declare(strict_types=1);

namespace Myth\Courier\Services;

use InvalidArgumentException;
use Throwable;

/**
 * Renders email HTML using CI4's view() system or markdown files.
 */
class TemplateService
{
    public function __construct(private readonly MarkdownService $markdownService)
    {
    }

    /**
     * Renders the email body view, then optionally wraps it in a layout.
     *
     * When $viewPath ends with .md the body is rendered from a markdown file.
     * When $layoutPath is null the content view is returned directly.
     * The layout receives $content (the rendered body) plus all $data keys.
     *
     * @param array<string, mixed> $data
     *
     * @throws InvalidArgumentException if either view path cannot be rendered
     */
    public function render(string $viewPath, ?string $layoutPath, array $data = []): string
    {
        if (str_ends_with($viewPath, '.md')) {
            $body = $this->markdownService->renderFile($viewPath, $this->buildTokens($data));
        } else {
            $body = $this->renderView($viewPath, $data);
        }

        if ($layoutPath === null) {
            return $body;
        }

        $data['content'] = $body;

        return $this->renderView($layoutPath, $data);
    }

    /**
     * Renders a view for plain-text use.
     * For markdown paths returns the raw markdown (already readable as plain text).
     * For PHP views strips HTML tags and normalises whitespace.
     *
     * @param array<string, mixed> $data
     *
     * @throws InvalidArgumentException if the view path cannot be rendered
     */
    public function renderText(string $viewPath, array $data = []): string
    {
        if (str_ends_with($viewPath, '.md')) {
            return $this->markdownService->renderFileAsText($viewPath, $this->buildTokens($data));
        }

        $html = $this->renderView($viewPath, $data);
        $text = strip_tags($html);

        // Collapse multiple blank lines and trim surrounding whitespace
        $text = (string) preg_replace('/\n{3,}/', "\n\n", $text);
        $text = (string) preg_replace('/[ \t]+/', ' ', $text);

        return trim($text);
    }

    /**
     * Flattens a $data array into a string token map for markdown rendering.
     * All scalar properties of $data['contact'] are included, plus any other
     * scalar values in $data.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, string>
     */
    private function buildTokens(array $data): array
    {
        $tokens = [];

        if (isset($data['contact'])) {
            foreach (get_object_vars($data['contact']) as $field => $value) {
                if (! is_object($value) && ! is_array($value)) {
                    $tokens[$field] = (string) $value;
                }
            }
        }

        foreach ($data as $key => $value) {
            if ($key !== 'contact' && ! is_object($value) && ! is_array($value)) {
                $tokens[$key] = (string) $value;
            }
        }

        return $tokens;
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
        } catch (Throwable $e) {
            throw new InvalidArgumentException(
                "Courier: could not render view \"{$path}\": " . $e->getMessage(),
                0,
                $e,
            );
        }
    }
}
