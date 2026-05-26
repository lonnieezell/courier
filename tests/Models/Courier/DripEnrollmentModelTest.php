<?php

declare(strict_types=1);

namespace Tests\Models\Courier;

use CodeIgniter\Events\Events;
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

    public function testRecordFailureIncrementsRetryCountAndPushesNextSendAt(): void
    {
        $enrollmentModel = new DripEnrollmentModel();
        $enrollmentId    = $enrollmentModel->insert([
            'contact_id'   => $this->contactId,
            'campaign_id'  => $this->campaignId,
            'current_step' => 1,
            'next_send_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
        ]);

        $enrollment = $enrollmentModel->find($enrollmentId);
        $before     = time();
        $enrollmentModel->recordFailure($enrollment, 'mailer returned false', 5, 3);
        $after = time();

        $updated = $enrollmentModel->find($enrollmentId);

        $this->assertSame(1, $updated->retry_count);
        $this->assertSame(EnrollmentStatus::Active, $updated->status);
        $nextSendAt = strtotime((string) $updated->next_send_at);
        $this->assertGreaterThanOrEqual($before + 5 * 60, $nextSendAt);
        $this->assertLessThanOrEqual($after + 5 * 60, $nextSendAt);
    }

    public function testRecordFailureAtMaxRetriesSetsFailedStatus(): void
    {
        $enrollmentModel = new DripEnrollmentModel();
        $enrollmentId    = $enrollmentModel->insert([
            'contact_id'   => $this->contactId,
            'campaign_id'  => $this->campaignId,
            'current_step' => 1,
            'retry_count'  => 2,
        ]);

        $enrollment = $enrollmentModel->find($enrollmentId);
        $enrollmentModel->recordFailure($enrollment, 'ESP error', 5, 3);

        $updated = $enrollmentModel->find($enrollmentId);

        $this->assertSame(EnrollmentStatus::Failed, $updated->status);
    }

    public function testRecordFailureAtMaxRetriesFiresEventWithFailedStatus(): void
    {
        $enrollmentModel = new DripEnrollmentModel();
        $enrollmentId    = $enrollmentModel->insert([
            'contact_id'   => $this->contactId,
            'campaign_id'  => $this->campaignId,
            'current_step' => 1,
            'retry_count'  => 2,
        ]);

        $capturedStatus = null;
        Events::on('courier_enrollment_failed', static function ($enrollment) use (&$capturedStatus): void {
            $capturedStatus = $enrollment->status;
        });

        $enrollment = $enrollmentModel->find($enrollmentId);
        $enrollmentModel->recordFailure($enrollment, 'ESP error', 5, 3);

        Events::removeAllListeners('courier_enrollment_failed');

        $this->assertSame(EnrollmentStatus::Failed, $capturedStatus);
    }

    public function testAdvanceOnLastStepCompletionResetsRetryCount(): void
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
            'retry_count'  => 2,
        ]);

        $enrollment = $enrollmentModel->find($enrollmentId);
        $enrollmentModel->advance($enrollment);

        $updated = $enrollmentModel->find($enrollmentId);

        $this->assertSame(EnrollmentStatus::Completed, $updated->status);
        $this->assertSame(0, $updated->retry_count);
    }

    public function testAdvanceResetsRetryCount(): void
    {
        (new DripStepModel())->insert([
            'campaign_id' => $this->campaignId,
            'position'    => 1,
            'view'        => 'emails/step1',
            'subject'     => 'Step 1',
            'delay_hours' => 0,
        ]);
        (new DripStepModel())->insert([
            'campaign_id' => $this->campaignId,
            'position'    => 2,
            'view'        => 'emails/step2',
            'subject'     => 'Step 2',
            'delay_hours' => 24,
        ]);

        $enrollmentModel = new DripEnrollmentModel();
        $enrollmentId    = $enrollmentModel->insert([
            'contact_id'   => $this->contactId,
            'campaign_id'  => $this->campaignId,
            'current_step' => 1,
            'retry_count'  => 2,
        ]);

        $enrollment = $enrollmentModel->find($enrollmentId);
        $enrollmentModel->advance($enrollment);

        $updated = $enrollmentModel->find($enrollmentId);

        $this->assertSame(0, $updated->retry_count);
    }
}
