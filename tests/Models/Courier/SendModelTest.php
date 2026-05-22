<?php

declare(strict_types=1);

namespace Tests\Models\Courier;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Myth\Courier\Enums\CampaignStatus;
use Myth\Courier\Enums\CampaignType;
use Myth\Courier\Enums\SendStatus;
use Myth\Courier\Models\CampaignModel;
use Myth\Courier\Models\ContactModel;
use Myth\Courier\Models\SendModel;

/**
 * @internal
 */
final class SendModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = 'Myth\Courier';
    private int $contactId;
    private int $campaignId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contactId  = (new ContactModel())->insert(['email' => 'send@example.com']);
        $this->campaignId = (new CampaignModel())->skipValidation(true)->insert([
            'name'       => 'Test Campaign',
            'subject'    => 'Hello',
            'type'       => CampaignType::Blast,
            'status'     => CampaignStatus::Draft,
            'from_name'  => 'Sender',
            'from_email' => 'sender@example.com',
        ]);
    }

    public function testCreatePendingReturnsObjectWithTokens(): void
    {
        $model = new SendModel();
        $send  = $model->createPending($this->contactId, $this->campaignId, null);

        $this->assertSame(SendStatus::Pending, $send->status);
        $this->assertSame(32, strlen($send->open_token));
    }

    public function testCreatePendingWithNullStepId(): void
    {
        $model = new SendModel();
        $send  = $model->createPending($this->contactId, $this->campaignId, null);

        $this->assertNull($send->drip_step_id);
    }

    public function testFindByOpenTokenReturnsMatchingSend(): void
    {
        $model = new SendModel();
        $send  = $model->createPending($this->contactId, $this->campaignId, null);

        $found = $model->findByOpenToken($send->open_token);

        $this->assertNotNull($found);
        $this->assertSame($send->id, $found->id);
    }

    public function testFindByOpenTokenReturnsNullForMissingToken(): void
    {
        $model = new SendModel();

        $this->assertNull($model->findByOpenToken('doesnotexist'));
    }
}
