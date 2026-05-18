<?php

declare(strict_types=1);

namespace Tests\Services\Courier;

use CodeIgniter\Test\CIUnitTestCase;
use InvalidArgumentException;
use Myth\Courier\Services\TemplateService;

/**
 * @internal
 */
final class TemplateServiceTest extends CIUnitTestCase
{
    private TemplateService $service;

    /** Namespaced view paths for test fixtures under src/Views/tests/ */
    private const BODY_VIEW   = 'Myth\\Courier\\Views\\tests/test_body';
    private const LAYOUT_VIEW = 'Myth\\Courier\\Views\\tests/test_layout';

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TemplateService();
    }

    public function testRenderWithLayoutReturnsLayoutWrapper(): void
    {
        $contact       = (object) ['first_name' => 'Alice'];
        $html          = $this->service->render(self::BODY_VIEW, self::LAYOUT_VIEW, ['contact' => $contact]);

        $this->assertStringContainsString('class="layout"', $html);
    }

    public function testRenderInjectsContentIntoLayout(): void
    {
        $contact = (object) ['first_name' => 'Bob'];
        $html    = $this->service->render(self::BODY_VIEW, self::LAYOUT_VIEW, ['contact' => $contact]);

        $this->assertStringContainsString('Hello Bob', $html);
        $this->assertStringContainsString('class="layout"', $html);
    }

    public function testRenderWithNullLayoutReturnsContentOnly(): void
    {
        $contact = (object) ['first_name' => 'Carol'];
        $html    = $this->service->render(self::BODY_VIEW, null, ['contact' => $contact]);

        $this->assertStringContainsString('Hello Carol', $html);
        $this->assertStringNotContainsString('class="layout"', $html);
    }

    public function testRenderThrowsOnMissingViewPath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Courier: could not render view/');

        $this->service->render('does/not/exist', null);
    }

    public function testRenderTextStripsHtmlTags(): void
    {
        $contact = (object) ['first_name' => 'Dave'];
        $text    = $this->service->renderText(self::BODY_VIEW, ['contact' => $contact]);

        $this->assertStringContainsString('Hello Dave', $text);
        $this->assertStringNotContainsString('<p>', $text);
    }
}
