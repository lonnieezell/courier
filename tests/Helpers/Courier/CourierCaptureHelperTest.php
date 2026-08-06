<?php

declare(strict_types=1);

namespace Tests\Helpers\Courier;

use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Myth\Courier\Enums\CampaignStatus;
use Myth\Courier\Enums\CampaignType;
use Myth\Courier\Enums\ContactStatus;
use Myth\Courier\Models\CampaignModel;
use Myth\Courier\Models\ContactModel;
use Myth\Courier\Models\ContactTagModel;
use Myth\Courier\Models\DripEnrollmentModel;
use Myth\Courier\Models\DripStepModel;
use Myth\Courier\Models\TagModel;

/**
 * @internal
 */
final class CourierCaptureHelperTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh   = true;
    protected $namespace = 'Myth\Courier';

    /**
     * @var array<int, array{0: string, 1: string, 2: string}>
     */
    private array $captureRoute = [
        ['POST', 'courier/capture', '\Myth\Courier\Controllers\CourierController::capture'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config('Courier')->testMode = true;
    }

    public function testPostValidEmailCreatesContactAndRedirects(): void
    {
        $result = $this->withRoutes($this->captureRoute)->post('courier/capture', [
            'email'            => 'subscriber@example.com',
            'courier_source'   => 'homepage-signup',
            'courier_redirect' => '/thanks',
        ]);

        $result->assertStatus(302);
        $result->assertRedirectTo('/thanks');

        $this->assertSame(
            1,
            (new ContactModel())->where('email', 'subscriber@example.com')->countAllResults(),
        );
    }

    public function testPostWithTagsAppliesTagsToContact(): void
    {
        $this->withRoutes($this->captureRoute)->post('courier/capture', [
            'email'        => 'tagged@example.com',
            'courier_tags' => '["newsletter","trial"]',
        ]);

        $contact = (new ContactModel())->where('email', 'tagged@example.com')->first();
        $this->assertNotNull($contact);

        $tag = (new TagModel())->where('slug', 'newsletter')->first();
        $this->assertNotNull($tag);

        $linked = (new ContactTagModel())
            ->where('contact_id', $contact->id)
            ->where('tag_id', $tag->id)
            ->countAllResults();

        $this->assertSame(1, $linked);
    }

    public function testPostWithDripIdEnrollsContactInDrip(): void
    {
        $campaignId = $this->createDripCampaignWithStep();

        $this->withRoutes($this->captureRoute)->post('courier/capture', [
            'email'           => 'drip@example.com',
            'courier_drip_id' => $campaignId,
        ]);

        $contact = (new ContactModel())->where('email', 'drip@example.com')->first();
        $this->assertNotNull($contact);

        $enrolled = (new DripEnrollmentModel())
            ->where('contact_id', $contact->id)
            ->where('campaign_id', $campaignId)
            ->countAllResults();

        $this->assertSame(1, $enrolled);
    }

    public function testPostMissingEmailReturns422JsonWhenAjax(): void
    {
        $result = $this->withRoutes($this->captureRoute)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post('courier/capture', ['email' => '']);

        $result->assertStatus(422);

        $body = json_decode($result->getJSON(), true);
        $this->assertFalse($body['success']);
        $this->assertArrayHasKey('email', $body['errors']);
    }

    public function testPostMissingEmailRedirectsBackWithErrorsWhenNotAjax(): void
    {
        $result = $this->withRoutes($this->captureRoute)->post('courier/capture', ['email' => '']);

        $result->assertStatus(302);
        $this->assertNotEmpty(session('courier_errors'));
    }

    public function testPostUnsubscribedEmailResubscribesSilently(): void
    {
        $contactModel = new ContactModel();
        $id           = $contactModel->insert([
            'email'           => 'unsub@example.com',
            'status'          => ContactStatus::Unsubscribed,
            'unsubscribed_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->withRoutes($this->captureRoute)->post('courier/capture', [
            'email'            => 'unsub@example.com',
            'courier_redirect' => '/thanks',
        ]);

        $result->assertStatus(302);
        $result->assertRedirectTo('/thanks');

        $contact = $contactModel->find($id);
        $this->assertSame(ContactStatus::Subscribed, $contact->status);
    }

    public function testDefaultsDripOverridesPostDripId(): void
    {
        $campaignId = $this->createDripCampaignWithStep();

        $route = [
            ['POST', 'test/capture-drip', static function () use ($campaignId): ResponseInterface {
                helper('courier');

                return courier_capture(service('request'), ['drip' => $campaignId]);
            }],
        ];

        $this->withRoutes($route)->post('test/capture-drip', [
            'email'           => 'override@example.com',
            'courier_drip_id' => '999',
        ]);

        $contact = (new ContactModel())->where('email', 'override@example.com')->first();
        $this->assertNotNull($contact);

        $enrolled = (new DripEnrollmentModel())
            ->where('contact_id', $contact->id)
            ->where('campaign_id', $campaignId)
            ->countAllResults();

        $this->assertSame(1, $enrolled);
    }

    public function testDefaultsTagsMergeWithPostTags(): void
    {
        $route = [
            ['POST', 'test/capture-tags', static function (): ResponseInterface {
                helper('courier');

                return courier_capture(service('request'), ['tags' => ['vip']]);
            }],
        ];

        $this->withRoutes($route)->post('test/capture-tags', [
            'email'        => 'merge@example.com',
            'courier_tags' => '["newsletter"]',
        ]);

        $contact = (new ContactModel())->where('email', 'merge@example.com')->first();
        $this->assertNotNull($contact);

        foreach (['newsletter', 'vip'] as $slug) {
            $tag = (new TagModel())->where('slug', $slug)->first();
            $this->assertNotNull($tag, "Tag '{$slug}' not found");

            $linked = (new ContactTagModel())
                ->where('contact_id', $contact->id)
                ->where('tag_id', $tag->id)
                ->countAllResults();

            $this->assertSame(1, $linked, "Tag '{$slug}' not linked to contact");
        }
    }

    public function testInvalidTagSlugsAreDroppedSilently(): void
    {
        $this->withRoutes($this->captureRoute)->post('courier/capture', [
            'email'        => 'tagtest@example.com',
            'courier_tags' => json_encode([
                'valid-tag',
                '<script>alert(1)</script>',  // XSS payload
                str_repeat('a', 65),           // too long
                '',                            // empty string
                'also-valid',
            ]),
        ]);

        $contact = (new ContactModel())->where('email', 'tagtest@example.com')->first();
        $this->assertNotNull($contact);

        // valid slugs applied
        foreach (['valid-tag', 'also-valid'] as $slug) {
            $tag = (new TagModel())->where('slug', $slug)->first();
            $this->assertNotNull($tag, "Expected tag '{$slug}' to be created");
        }

        // invalid slugs must not appear in the DB
        $this->assertSame(0, (new TagModel())->like('slug', '<script>', 'none')->countAllResults());
        $this->assertSame(0, (new TagModel())->where('slug', str_repeat('a', 65))->countAllResults());
        $this->assertSame(0, (new TagModel())->where('slug', '')->countAllResults());
    }

    public function testAbsoluteRedirectUrlIsSanitizedToRoot(): void
    {
        $result = $this->withRoutes($this->captureRoute)->post('courier/capture', [
            'email'            => 'safe@example.com',
            'courier_redirect' => 'https://evil.example.com/phishing',
        ]);

        $result->assertStatus(302);
        $result->assertRedirectTo('/');
    }

    public function testPostBouncedEmailReturnsErrorGracefully(): void
    {
        (new ContactModel())->insert([
            'email'  => 'bounced@example.com',
            'status' => ContactStatus::Bounced,
        ]);

        $result = $this->withRoutes($this->captureRoute)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post('courier/capture', ['email' => 'bounced@example.com']);

        $result->assertStatus(422);

        $body = json_decode($result->getJSON(), true);
        $this->assertFalse($body['success']);
        $this->assertArrayHasKey('email', $body['errors']);
    }

    public function testPostWithHoneypotFilledReturnsSilentSuccessAjax(): void
    {
        $result = $this->withRoutes($this->captureRoute)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post('courier/capture', [
                'email'      => 'bot@example.com',
                'courier_hp' => 'i am a bot',
            ]);

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);
        $this->assertTrue($body['success']);

        $this->assertSame(0, (new ContactModel())->where('email', 'bot@example.com')->countAllResults());
    }

    public function testPostWithHoneypotFilledRedirectsSuccessfullyWhenNotAjax(): void
    {
        $result = $this->withRoutes($this->captureRoute)->post('courier/capture', [
            'email'            => 'bot2@example.com',
            'courier_hp'       => 'i am a bot',
            'courier_redirect' => '/thanks',
        ]);

        $result->assertStatus(302);
        $result->assertRedirectTo('/thanks');

        $this->assertSame(0, (new ContactModel())->where('email', 'bot2@example.com')->countAllResults());
    }

    public function testPostAlreadySubscribedEmailReturnsSuccessAjax(): void
    {
        (new ContactModel())->insert([
            'email'  => 'existing@example.com',
            'status' => ContactStatus::Subscribed,
        ]);

        $result = $this->withRoutes($this->captureRoute)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post('courier/capture', ['email' => 'existing@example.com']);

        $result->assertStatus(200);

        $body = json_decode($result->getJSON(), true);
        $this->assertTrue($body['success']);
    }

    public function testPostAlreadySubscribedEmailRedirectsSuccessfullyWhenNotAjax(): void
    {
        (new ContactModel())->insert([
            'email'  => 'existing2@example.com',
            'status' => ContactStatus::Subscribed,
        ]);

        $result = $this->withRoutes($this->captureRoute)->post('courier/capture', [
            'email'            => 'existing2@example.com',
            'courier_redirect' => '/thanks',
        ]);

        $result->assertStatus(302);
        $result->assertRedirectTo('/thanks');
    }

    private function createDripCampaignWithStep(): int
    {
        $campaignModel = new CampaignModel();
        $campaignId    = $campaignModel->insert([
            'name'       => 'Welcome Drip',
            'subject'    => 'Welcome',
            'type'       => CampaignType::DripSequence,
            'status'     => CampaignStatus::Draft,
            'from_name'  => 'Sender',
            'from_email' => 'sender@example.com',
        ]);

        (new DripStepModel())->insert([
            'campaign_id' => $campaignId,
            'position'    => 1,
            'subject'     => 'Step 1',
            'view'        => 'Myth\Courier\Views\tests/test_body',
            'delay_hours' => 24,
        ]);

        return $campaignId;
    }
}
