<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Myth\Courier\Enums\CampaignStatus;
use Myth\Courier\Enums\CampaignType;
use Myth\Courier\Models\CampaignModel;
use Myth\Courier\Models\ContactModel;
use Myth\Courier\Models\DripStepModel;
use Myth\Courier\Models\SendModel;

/**
 * @internal
 */
final class CourierSendsBlastDedupeIndexTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = 'Myth\Courier';
    private int $contactId;
    private int $campaignId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contactId  = (new ContactModel())->insert(['email' => 'dedupe@example.com']);
        $this->campaignId = (new CampaignModel())->skipValidation(true)->insert([
            'name'       => 'Dedupe Campaign',
            'subject'    => 'Hello',
            'type'       => CampaignType::Blast,
            'status'     => CampaignStatus::Draft,
            'from_name'  => 'Sender',
            'from_email' => 'sender@example.com',
        ]);
    }

    public function testRejectsSecondBlastSendForSameCampaignAndContact(): void
    {
        $model = new SendModel();
        $model->createPending($this->contactId, $this->campaignId, null);

        $this->expectException(DatabaseException::class);

        $model->createPending($this->contactId, $this->campaignId, null);
    }

    public function testAllowsMultipleDripStepsForSameCampaignAndContact(): void
    {
        $stepModel = new DripStepModel();
        $step1Id   = $stepModel->insert([
            'campaign_id' => $this->campaignId,
            'position'    => 1,
            'view'        => 'drip_step_1',
            'subject'     => 'Step 1',
        ]);
        $step2Id = $stepModel->insert([
            'campaign_id' => $this->campaignId,
            'position'    => 2,
            'view'        => 'drip_step_2',
            'subject'     => 'Step 2',
        ]);

        $model  = new SendModel();
        $first  = $model->createPending($this->contactId, $this->campaignId, $step1Id);
        $second = $model->createPending($this->contactId, $this->campaignId, $step2Id);

        $this->assertNotSame($first->id, $second->id);
    }
}
