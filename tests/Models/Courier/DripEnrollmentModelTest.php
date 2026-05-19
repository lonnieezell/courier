<?php

declare(strict_types=1);

namespace Tests\Models\Courier;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Myth\Courier\Enums\CampaignStatus;
use Myth\Courier\Enums\CampaignType;
use Myth\Courier\Enums\EnrollmentStatus;
use Myth\Courier\Models\CampaignModel;
use Myth\Courier\Models\ContactModel;
use Myth\Courier\Models\DripEnrollmentModel;
use Myth\Courier\Models\DripStepModel;

/**
 * @internal
 */
final class DripEnrollmentModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = 'Myth\Courier';
    private int $contactId;
    private int $campaignId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contactId  = (new ContactModel())->insert(['email' => 'enroll@example.com']);
        $this->campaignId = (new CampaignModel())->skipValidation(true)->insert([
            'name'       => 'Test Drip',
            'subject'    => 'Welcome',
            'type'       => CampaignType::DripSequence,
            'status'     => CampaignStatus::Draft,
            'from_name'  => 'Sender',
            'from_email' => 'sender@example.com',
        ]);
    }

    public function testAdvanceMovesToNextStep(): void
    {
        $stepModel = new DripStepModel();
        $stepModel->insert([
            'campaign_id' => $this->campaignId,
            'position'    => 1,
            'view'        => 'emails/welcome',
            'subject'     => 'Step 1',
            'delay_hours' => 0,
        ]);
        $stepModel->insert([
            'campaign_id' => $this->campaignId,
            'position'    => 2,
            'view'        => 'emails/followup',
            'subject'     => 'Step 2',
            'delay_hours' => 24,
        ]);

        $enrollmentModel = new DripEnrollmentModel();
        $enrollmentId    = $enrollmentModel->insert([
            'contact_id'   => $this->contactId,
            'campaign_id'  => $this->campaignId,
            'current_step' => 1,
        ]);

        $enrollment = $enrollmentModel->find($enrollmentId);
        $enrollmentModel->advance($enrollment);

        $updated = $enrollmentModel->find($enrollmentId);

        $this->assertSame(2, (int) $updated->current_step);
        $this->assertNotNull($updated->next_send_at);
    }

    public function testAdvanceOnLastStepCompletesEnrollment(): void
    {
        (new DripStepModel())->insert([
            'campaign_id' => $this->campaignId,
            'position'    => 1,
            'view'        => 'emails/only',
            'subject'     => 'Only Step',
            'delay_hours' => 0,
        ]);

        $enrollmentModel = new DripEnrollmentModel();
        $enrollmentId    = $enrollmentModel->insert([
            'contact_id'   => $this->contactId,
            'campaign_id'  => $this->campaignId,
            'current_step' => 1,
        ]);

        $enrollment = $enrollmentModel->find($enrollmentId);
        $enrollmentModel->advance($enrollment);

        $updated = $enrollmentModel->find($enrollmentId);

        $this->assertSame(EnrollmentStatus::Completed, $updated->status);
    }
}
