<?php

declare(strict_types=1);

namespace Myth\Courier\Services;

use InvalidArgumentException;
use Myth\Postal\Markdown\LayoutRenderer;
use Throwable;

/**
 * Renders email HTML using CI4's view() system or markdown files.
 */
class TemplateService
{
    /**
     * @param string $markdownPath Absolute path to resolve markdown body files from.
     */
    public function __construct(private readonly string $markdownPath)
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
            $body = $this->restoreTokens(
                service('markdown')->toHtml($this->loadMarkdown($viewPath, $data)),
            );
        } else {
            $body = $this->renderView($viewPath, $data);
        }

        if ($layoutPath === null) {
            return $body;
        }

        return $this->renderLayout($body, $layoutPath, $data);
    }

    /**
     * Renders a view for plain-text use.
     * For markdown paths strips the markdown syntax from the source.
     * For PHP views strips HTML tags and normalises whitespace.
     *
     * @param array<string, mixed> $data
     *
     * @throws InvalidArgumentException if the view path cannot be rendered
     */
    public function renderText(string $viewPath, array $data = []): string
    {
        if (str_ends_with($viewPath, '.md')) {
            $text = service('markdown')->toText($this->loadMarkdown($viewPath, $data));

            // toText() strips markdown syntax but leaves raw HTML, so the Mail
            // Component tags have to be dropped here or they reach the
            // plain-text part. Only those tags are removed: strip_tags() would
            // also swallow prose such as "if x<y then".
            return trim((string) preg_replace('#</?mail-[a-z0-9-]*+[^>]*+>#i', '', $text));
        }

        $html = $this->renderView($viewPath, $data);
        $text = strip_tags($html);

        // Collapse multiple blank lines and trim surrounding whitespace
        $text = (string) preg_replace('/\n{3,}/', "\n\n", $text);
        $text = (string) preg_replace('/[ \t]+/', ' ', $text);

        return trim($text);
    }

    /**
     * Restores {courier_*} placeholders that CommonMark URL-encoded while
     * converting, so MailerService's later str_replace still finds them.
     *
     * A link written as [Unsubscribe]({courier_unsubscribe_url}) comes out of
     * the converter as href="%7Bcourier_unsubscribe_url%7D".
     */
    private function restoreTokens(string $html): string
    {
        return (string) preg_replace('/%7B([a-zA-Z0-9_]+)%7D/i', '{$1}', $html);
    }

    /**
     * Wraps the rendered body in a layout via postal's LayoutRenderer, which
     * also inlines the layout's stylesheet for email-client compatibility.
     *
     * LayoutRenderer only hands $content to the layout view, so $data is primed
     * on the shared renderer first to keep layouts' access to $subject, $contact
     * and friends.
     *
     * @param array<string, mixed> $data
     *
     * @throws InvalidArgumentException
     */
    private function renderLayout(string $body, string $layoutPath, array $data): string
    {
        try {
            service('renderer')->setData($data, 'raw');

            return (new LayoutRenderer())->render($body, $layoutPath);
        } catch (Throwable $e) {
            throw new InvalidArgumentException(
                "Courier: could not render view \"{$layoutPath}\": " . $e->getMessage(),
                0,
                $e,
            );
        }
    }

    /**
     * Loads a markdown body file from the configured base path and substitutes
     * its {token} placeholders, returning the raw markdown source.
     *
     * @param array<string, mixed> $data
     *
     * @throws InvalidArgumentException when the file does not exist
     */
    private function loadMarkdown(string $path, array $data): string
    {
        $fullPath = rtrim($this->markdownPath, '/\\') . DIRECTORY_SEPARATOR . ltrim($path, '/\\');

        if (! is_file($fullPath)) {
            throw new InvalidArgumentException(
                "Courier: markdown file not found: \"{$path}\"",
            );
        }

        $markdown = (string) file_get_contents($fullPath);

        foreach ($this->buildTokens($data) as $key => $value) {
            $markdown = str_replace('{' . $key . '}', $value, $markdown);
        }

        return $markdown;
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
