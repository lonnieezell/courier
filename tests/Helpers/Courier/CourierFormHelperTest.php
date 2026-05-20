<?php

declare(strict_types=1);

namespace Tests\Helpers\Courier;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class CourierFormHelperTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('courier');
    }

    public function testCourierFormReturnsFormWithActionAndEmailInput(): void
    {
        $html = courier_form('homepage-signup');

        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('action="https://example.com/courier/capture"', $html);
        $this->assertStringContainsString('method="POST"', $html);
        $this->assertStringContainsString('name="email"', $html);
        $this->assertStringContainsString('type="email"', $html);
        $this->assertStringContainsString('<button type="submit">', $html);
        $this->assertStringContainsString('name="courier_source"', $html);
        $this->assertStringContainsString('value="homepage-signup"', $html);
        $this->assertStringContainsString('</form>', $html);
    }

    public function testCourierFormEncodesTagsAsJson(): void
    {
        $html = courier_form('blog-sidebar', ['tags' => ['trial', 'newsletter']]);

        $this->assertStringContainsString('name="courier_tags"', $html);
        $this->assertStringContainsString('["trial","newsletter"]', $html);
    }

    public function testCourierFormIncludesOptionalFields(): void
    {
        $html = courier_form('signup', ['fields' => ['first_name', 'last_name']]);

        $this->assertStringContainsString('name="first_name"', $html);
        $this->assertStringContainsString('name="last_name"', $html);
    }

    public function testCourierFormAjaxAddsDataAttributeAndOmitsRedirect(): void
    {
        $html = courier_form('signup', ['ajax' => true, 'redirect' => '/thanks']);

        $this->assertStringContainsString('data-courier-ajax="1"', $html);
        $this->assertStringNotContainsString('courier_redirect', $html);
    }

    public function testCourierFormClassAttributeIsSet(): void
    {
        $html = courier_form('signup', ['class' => 'my-form']);

        $this->assertStringContainsString('class="my-form"', $html);
    }

    public function testCourierFormOmitsDripIdWhenNull(): void
    {
        $html = courier_form('signup');

        $this->assertStringNotContainsString('courier_drip_id', $html);
    }

    public function testCourierFormOpenReturnsOpeningTagWithHiddenFieldsOnly(): void
    {
        $html = courier_form_open('footer-signup', ['tags' => ['vip']]);

        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('name="courier_source"', $html);
        $this->assertStringContainsString('name="courier_tags"', $html);
        $this->assertStringNotContainsString('name="email"', $html);
        $this->assertStringNotContainsString('</form>', $html);
    }

    public function testCourierFormCloseReturnsClosingTag(): void
    {
        $this->assertSame('</form>', courier_form_close());
    }

    public function testCourierUnsubscribeUrlContainsToken(): void
    {
        $contact                    = new \Myth\Courier\DTO\ContactDTO();
        $contact->unsubscribe_token = 'abc123token';

        $url = courier_unsubscribe_url($contact);

        $this->assertStringContainsString('/courier/unsubscribe/abc123token', $url);
    }
}
