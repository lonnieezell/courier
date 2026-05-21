<?php

declare(strict_types=1);

namespace Tests\Services\Courier;

use CodeIgniter\Test\CIUnitTestCase;
use InvalidArgumentException;
use Myth\Courier\Services\MarkdownService;

/**
 * @internal
 */
final class MarkdownServiceTest extends CIUnitTestCase
{
    private MarkdownService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $fixtureDir    = realpath(__DIR__ . '/../../_support/Views') . '/';
        $this->service = new MarkdownService($fixtureDir);
    }

    public function testRenderFileReplacesTokensAndReturnsHtml(): void
    {
        $html = $this->service->renderFile('test_body.md', ['first_name' => 'Alice']);

        $this->assertStringContainsString('Hello Alice!', $html);
        $this->assertStringContainsString('<strong>test</strong>', $html);
    }

    public function testRenderFileAsTextReturnsRawMarkdownWithTokensReplaced(): void
    {
        $text = $this->service->renderFileAsText('test_body.md', ['first_name' => 'Bob']);

        $this->assertStringContainsString('Hello Bob!', $text);
        $this->assertStringContainsString('**test**', $text);
        $this->assertStringNotContainsString('<', $text);
    }

    public function testRenderFileMissingFileThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/markdown file not found/');

        $this->service->renderFile('does_not_exist.md');
    }
}
