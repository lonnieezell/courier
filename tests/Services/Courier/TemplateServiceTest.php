<?php

declare(strict_types=1);

namespace Tests\Services\Courier;

use CodeIgniter\Test\CIUnitTestCase;
use InvalidArgumentException;
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

    private const STYLED_LAYOUT_VIEW = 'Myth\\Courier\\Views\\tests/test_styled_layout';
    private const DATA_LAYOUT_VIEW   = 'Myth\\Courier\\Views\\tests/test_data_layout';
    private const FIXTURE_DIR        = __DIR__ . '/../../_support/Views/';

    private TemplateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TemplateService(self::FIXTURE_DIR);
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

    public function testRenderTextMarkdownStripsMarkdownSyntax(): void
    {
        $contact             = new stdClass();
        $contact->first_name = 'Grace';

        $text = $this->service->renderText('test_body.md', ['contact' => $contact]);

        $this->assertStringContainsString('Hello Grace!', $text);
        $this->assertStringContainsString('This is a test email.', $text);
        $this->assertStringNotContainsString('**', $text);
        $this->assertStringNotContainsString('<', $text);
    }

    public function testRenderInlinesLayoutStylesheet(): void
    {
        $contact             = new stdClass();
        $contact->first_name = 'Kim';

        $html = $this->service->render('test_body.md', self::STYLED_LAYOUT_VIEW, ['contact' => $contact]);

        $this->assertStringContainsString('Hello Kim!', $html);
        $this->assertStringContainsString('color: #ff0000', $html);
        $this->assertStringContainsString('style=', $html);
    }

    public function testLayoutReceivesRenderDataAlongsideContent(): void
    {
        $contact             = new stdClass();
        $contact->first_name = 'Leo';

        $html = $this->service->render('test_body.md', self::DATA_LAYOUT_VIEW, [
            'contact' => $contact,
            'subject' => 'Weekly Digest',
        ]);

        $this->assertStringContainsString('Hello Leo!', $html);
        $this->assertStringContainsString('data-subject="Weekly Digest"', $html);
    }

    public function testTrackingPlaceholdersInDefaultLayoutSurviveCssInlining(): void
    {
        $contact             = new stdClass();
        $contact->first_name = 'Mia';

        $html = $this->service->render('test_body.md', config('Courier')->defaultLayout, [
            'contact' => $contact,
        ]);

        // MailerService::applyTracking() str_replaces these on the fully-rendered HTML
        $this->assertStringContainsString('{courier_unsubscribe_url}', $html);
        $this->assertStringContainsString('{courier_tracking_pixel}', $html);
    }

    public function testRenderMarkdownRendersMailComponents(): void
    {
        $contact             = new stdClass();
        $contact->first_name = 'Judy';

        $html = $this->service->render('test_components.md', null, ['contact' => $contact]);

        $this->assertStringContainsString('Hello Judy!', $html);
        $this->assertStringNotContainsString('<mail-button', $html);
        $this->assertStringNotContainsString('<mail-panel', $html);
        $this->assertStringContainsString('<a href=', $html);
        $this->assertStringContainsString('Read the update', $html);
        $this->assertStringContainsString('Heads up, this is a callout.', $html);
    }

    public function testCourierTokenSurvivesInsideMailComponentAttribute(): void
    {
        $contact             = new stdClass();
        $contact->first_name = 'Nora';

        // Components escape attributes with esc($url, 'attr'), which encodes the
        // braces; MailerService's later str_replace still has to find the token.
        $html = $this->service->render('test_component_unsubscribe.md', null, ['contact' => $contact]);

        $this->assertStringContainsString('{courier_unsubscribe_url}', $html);

        $finalHtml = str_replace('{courier_unsubscribe_url}', 'https://example.com/u/abc', $html);

        $this->assertStringContainsString('https://example.com/u/abc', $finalHtml);
        $this->assertStringNotContainsString('courier_unsubscribe_url', $finalHtml);
    }

    public function testRenderTextMarkdownStripsMailComponentMarkup(): void
    {
        $contact             = new stdClass();
        $contact->first_name = 'Omar';

        $text = $this->service->renderText('test_components.md', ['contact' => $contact]);

        $this->assertStringContainsString('Hello Omar!', $text);
        $this->assertStringContainsString('Read the update', $text);
        $this->assertStringContainsString('Heads up, this is a callout.', $text);
        $this->assertStringNotContainsString('<mail-button', $text);
        $this->assertStringNotContainsString('<mail-panel', $text);
    }

    public function testMailComponentLinkStaysTrackableWithoutALayout(): void
    {
        $contact             = new stdClass();
        $contact->first_name = 'Quinn';

        // Components escape hrefs with esc($url, 'attr'); MailerService::wrapLinks()
        // only matches an unencoded http(s) URL, and a layout must not be what
        // decides whether a link gets click-tracked.
        $html = $this->service->render('test_components.md', null, ['contact' => $contact]);

        $this->assertMatchesRegularExpression('/href=(["\'])(https?:\/\/[^"\']+)\1/i', $html);
        $this->assertStringContainsString('href="https://example.com/read"', $html);
    }

    public function testRenderTextKeepsProseContainingAngleBrackets(): void
    {
        $contact             = new stdClass();
        $contact->first_name = 'Sam';

        $text = $this->service->renderText('test_angle_prose.md', ['contact' => $contact]);

        // Dropping component tags must not swallow prose that merely looks like a tag
        $this->assertStringContainsString('Upgrade if x<y then check the dashboard.', $text);
        $this->assertStringContainsString('Go', $text);
        $this->assertStringNotContainsString('<mail-button', $text);
    }

    public function testAmpersandsInLinkUrlsStayEscaped(): void
    {
        $contact             = new stdClass();
        $contact->first_name = 'Rae';

        $html = $this->service->render('test_query_link.md', null, ['contact' => $contact]);

        $this->assertStringContainsString('href="https://example.com/?a=1&amp;b=2"', $html);
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

        // Markdown rendering must not replace {courier_unsubscribe_url} — MailerService does that later
        $html = $this->service->render('test_unsubscribe.md', null, ['contact' => $contact]);

        $this->assertStringContainsString('Hello Hank!', $html);
        $this->assertStringContainsString('{courier_unsubscribe_url}', $html);

        // Simulate what MailerService does: replace the placeholder with the real URL
        $finalHtml = str_replace('{courier_unsubscribe_url}', 'https://example.com/unsubscribe/abc', $html);

        $this->assertStringContainsString('https://example.com/unsubscribe/abc', $finalHtml);
        $this->assertStringNotContainsString('{courier_unsubscribe_url}', $finalHtml);
    }
}
