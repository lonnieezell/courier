<?php

declare(strict_types=1);

namespace Myth\Courier\Services;

use InvalidArgumentException;
use League\CommonMark\GithubFlavoredMarkdownConverter;

/**
 * Renders markdown files as HTML or plain text for use as email body content.
 */
class MarkdownService
{
    private readonly GithubFlavoredMarkdownConverter $converter;

    /**
     * @param string $basePath Absolute path (with trailing slash) to resolve markdown files from.
     */
    public function __construct(private readonly string $basePath)
    {
        $this->converter = new GithubFlavoredMarkdownConverter();
    }

    /**
     * Loads a markdown file, replaces {tokens}, and returns the rendered HTML.
     *
     * @param array<string, string> $tokens
     *
     * @throws InvalidArgumentException when the file does not exist
     */
    public function renderFile(string $path, array $tokens = []): string
    {
        $markdown = $this->loadFile($path);
        $markdown = $this->replaceTokens($markdown, $tokens);

        $html = $this->converter->convert($markdown)->getContent();

        // CommonMark URL-encodes { and } in link hrefs; restore so MailerService can replace them
        return (string) preg_replace('/%7B([a-zA-Z0-9_]+)%7D/i', '{$1}', $html);
    }

    /**
     * Loads a markdown file, replaces {tokens}, and returns the raw markdown (for plain-text email alt).
     *
     * @param array<string, string> $tokens
     *
     * @throws InvalidArgumentException when the file does not exist
     */
    public function renderFileAsText(string $path, array $tokens = []): string
    {
        $markdown = $this->loadFile($path);

        return $this->replaceTokens($markdown, $tokens);
    }

    /**
     * @throws InvalidArgumentException
     */
    private function loadFile(string $path): string
    {
        $fullPath = rtrim($this->basePath, '/\\') . DIRECTORY_SEPARATOR . ltrim($path, '/\\');

        if (! is_file($fullPath)) {
            throw new InvalidArgumentException(
                "Courier: markdown file not found: \"{$path}\"",
            );
        }

        return (string) file_get_contents($fullPath);
    }

    /**
     * @param array<string, string> $tokens
     */
    private function replaceTokens(string $content, array $tokens): string
    {
        foreach ($tokens as $key => $value) {
            $content = str_replace('{' . $key . '}', $value, $content);
        }

        return $content;
    }
}
