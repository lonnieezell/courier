<?php

declare(strict_types=1);

namespace Tests\Models\Courier;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
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
            'type'       => 'blast',
            'status'     => 'draft',
            'from_name'  => 'Sender',
            'from_email' => 'sender@example.com',
        ]);
    }

    public function testCreatePendingReturnsObjectWithTokens(): void
    {
        $model = new SendModel();
        $send  = $model->createPending($this->contactId, $this->campaignId, null);

        $this->assertSame('pending', $send->status);
        $this->assertSame(32, strlen($send->open_token));
        $this->assertSame(32, strlen($send->click_token));
    }

    public function testCreatePendingWithNullStepId(): void
    {
        $model = new SendModel();
        $send  = $model->createPending($this->contactId, $this->campaignId, null);

        $this->assertNull($send->drip_step_id);
    }
}
