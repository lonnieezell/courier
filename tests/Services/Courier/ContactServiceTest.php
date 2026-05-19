<?php

declare(strict_types=1);

namespace Tests\Services\Courier;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Myth\Courier\Enums\CampaignStatus;
use Myth\Courier\Enums\CampaignType;
use Myth\Courier\Enums\ContactStatus;
use Myth\Courier\Enums\EnrollmentStatus;
use Myth\Courier\Exceptions\CourierValidationException;
use Myth\Courier\Models\CampaignModel;
use Myth\Courier\Models\ContactModel;
use Myth\Courier\Models\ContactTagModel;
use Myth\Courier\Models\DripEnrollmentModel;
use Myth\Courier\Models\TagModel;
use Myth\Courier\Services\ContactService;

/**
 * @internal
 */
final class ContactServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = 'Myth\Courier';
    private ContactService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ContactService(
            new ContactModel(),
            new TagModel(),
            new DripEnrollmentModel(),
            new ContactTagModel(),
        );
    }

    public function testSubscribeCreatesNewContact(): void
    {
        $contact = $this->service->subscribe(['email' => 'new@example.com']);

        $this->assertSame('new@example.com', $contact->email);
        $this->assertSame(ContactStatus::Subscribed, $contact->status);
        $this->assertNotEmpty($contact->unsubscribe_token);
        $this->assertSame(64, strlen($contact->unsubscribe_token));
        $this->assertNotNull($contact->subscribed_at);
    }

    public function testSubscribeResubscribesUnsubscribedContact(): void
    {
        $model = new ContactModel();
        $id    = $model->insert([
            'email'  => 'old@example.com',
            'status' => ContactStatus::Unsubscribed,
        ]);

        $contact = $this->service->subscribe(['email' => 'old@example.com']);

        $this->assertSame((int) $id, (int) $contact->id);
        $this->assertSame(ContactStatus::Subscribed, $contact->status);
        $this->assertCount(1, $model->findAll());
    }

    public function testSubscribeThrowsOnMissingEmail(): void
    {
        $this->expectException(CourierValidationException::class);
        $this->service->subscribe([]);
    }

    public function testSubscribeAppliesTagsCorrectly(): void
    {
        $contact = $this->service->subscribe(
            ['email' => 'tagged@example.com'],
            ['newsletter', 'vip'],
        );

        $pivotCount = (new ContactTagModel())
            ->where('contact_id', $contact->id)
            ->countAllResults();

        $this->assertSame(2, $pivotCount);
    }

    public function testUnsubscribeByTokenUpdatesStatusAndCancelsEnrollments(): void
    {
        $contactModel    = new ContactModel();
        $enrollmentModel = new DripEnrollmentModel();
        $campaignId      = (new CampaignModel())->skipValidation(true)->insert([
            'name'       => 'Test',
            'subject'    => 'Test',
            'type'       => CampaignType::DripSequence,
            'status'     => CampaignStatus::Draft,
            'from_name'  => 'Sender',
            'from_email' => 'sender@example.com',
        ]);

        $contactId = $contactModel->insert(['email' => 'unsub@example.com']);
        $contact   = $contactModel->find($contactId);

        $enrollmentModel->insert([
            'contact_id'  => $contactId,
            'campaign_id' => $campaignId,
            'status'      => EnrollmentStatus::Active,
        ]);

        $result = $this->service->unsubscribeByToken($contact->unsubscribe_token);

        $updated    = $contactModel->find($contactId);
        $enrollment = $enrollmentModel->where('contact_id', $contactId)->first();

        $this->assertTrue($result);
        $this->assertSame(ContactStatus::Unsubscribed, $updated->status);
        $this->assertNotNull($updated->unsubscribed_at);
        $this->assertSame(EnrollmentStatus::Cancelled, $enrollment->status);
    }

    public function testUnsubscribeByTokenReturnsFalseForInvalidToken(): void
    {
        $result = $this->service->unsubscribeByToken('no-such-token');

        $this->assertFalse($result);
    }
}
