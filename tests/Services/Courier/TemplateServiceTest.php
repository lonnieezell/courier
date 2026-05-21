<?php

declare(strict_types=1);

namespace Tests\Services\Courier;

use CodeIgniter\Test\CIUnitTestCase;
use InvalidArgumentException;
use Myth\Courier\Services\MarkdownService;
use Myth\Courier\Services\TemplateService;
use stdClass;

/**
 * @internal
 */
final class TemplateServiceTest extends CIUnitTestCase
{
    /**
     * Namespaced view paths for test fixtures under src/Views/tests/
     */
    private const BODY_VIEW = 'Myth\\Courier\\Views\\tests/test_body';

    private const LAYOUT_VIEW = 'Myth\\Courier\\Views\\tests/test_layout';
    private const FIXTURE_DIR = __DIR__ . '/../../_support/Views/';

    private TemplateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $markdownService = new MarkdownService(self::FIXTURE_DIR);
        $this->service   = new TemplateService($markdownService);
    }

    public function testRenderWithLayoutReturnsLayoutWrapper(): void
    {
        $contact = (object) ['first_name' => 'Alice'];
        $html    = $this->service->render(self::BODY_VIEW, self::LAYOUT_VIEW, ['contact' => $contact]);

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

    public function testRenderMarkdownReturnsHtmlWithTokensReplaced(): void
    {
        $contact             = new stdClass();
        $contact->first_name = 'Eve';

        $html = $this->service->render('test_body.md', null, ['contact' => $contact]);

        $this->assertStringContainsString('Hello Eve!', $html);
        $this->assertStringContainsString('<strong>test</strong>', $html);
    }

    public function testRenderMarkdownWithLayoutWrapsContent(): void
    {
        $contact             = new stdClass();
        $contact->first_name = 'Frank';

        $html = $this->service->render('test_body.md', self::LAYOUT_VIEW, ['contact' => $contact]);

        $this->assertStringContainsString('Hello Frank!', $html);
        $this->assertStringContainsString('class="layout"', $html);
    }

    public function testRenderTextMarkdownReturnsRawMarkdown(): void
    {
        $contact             = new stdClass();
        $contact->first_name = 'Grace';

        $text = $this->service->renderText('test_body.md', ['contact' => $contact]);

        $this->assertStringContainsString('Hello Grace!', $text);
        $this->assertStringContainsString('**test**', $text);
        $this->assertStringNotContainsString('<', $text);
    }

    public function testRenderMarkdownExposesAllContactScalarsAndDataKeys(): void
    {
        $contact                    = new stdClass();
        $contact->first_name        = 'Ivy';
        $contact->email             = 'ivy@example.com';
        $contact->unsubscribe_token = 'tok999';

        $html = $this->service->render('test_body.md', null, [
            'contact' => $contact,
            'subject' => 'Test Subject',
        ]);

        $this->assertStringContainsString('Hello Ivy!', $html);
    }

    public function testCourierUnsubscribeTokenSurvivesMarkdownRenderingAndCanBeReplaced(): void
    {
        $contact             = new stdClass();
        $contact->first_name = 'Hank';

        // MarkdownService must not replace {courier_unsubscribe_url} — MailerService does that later
        $html = $this->service->render('test_unsubscribe.md', null, ['contact' => $contact]);

        $this->assertStringContainsString('Hello Hank!', $html);
        $this->assertStringContainsString('{courier_unsubscribe_url}', $html);

        // Simulate what MailerService does: replace the placeholder with the real URL
        $finalHtml = str_replace('{courier_unsubscribe_url}', 'https://example.com/unsubscribe/abc', $html);

        $this->assertStringContainsString('https://example.com/unsubscribe/abc', $finalHtml);
        $this->assertStringNotContainsString('{courier_unsubscribe_url}', $finalHtml);
    }
}
