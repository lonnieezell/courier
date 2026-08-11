<?php

declare(strict_types=1);

namespace Tests\Integration;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Myth\Courier\DTO\CampaignDTO;
use Myth\Courier\Enums\CampaignStatus;
use Myth\Courier\Enums\CampaignType;
use Myth\Courier\Enums\ContactStatus;
use Myth\Courier\Enums\EnrollmentStatus;
use Myth\Courier\Enums\UnsubscribeResult;
use Myth\Courier\Models\CampaignModel;
use Myth\Courier\Models\ContactModel;
use Myth\Courier\Models\ContactTagModel;
use Myth\Courier\Models\DripEnrollmentModel;
use Myth\Courier\Models\DripStepModel;
use Myth\Courier\Models\SendModel;
use Myth\Courier\Models\TagModel;
use Myth\Courier\Services\ContactService;
use Myth\Courier\Services\DripService;
use Myth\Courier\Services\MailerService;
use Myth\Courier\Services\TemplateService;

/**
 * Full contact lifecycle: subscribe → drip → complete → unsubscribe → re-subscribe.
 *
 * @internal
 */
final class EndToEndTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    private const BODY_VIEW = 'Myth\Courier\Views\tests/test_body';

    protected $refresh   = true;
    protected $namespace = 'Myth\Courier';
    private ContactService $contactService;
    private DripService $dripService;
    private CampaignModel $campaignModel;
    private ContactModel $contactModel;
    private DripEnrollmentModel $enrollmentModel;
    private DripStepModel $stepModel;
    private SendModel $sendModel;

    protected function setUp(): void
    {
        parent::setUp();

        $config           = config('Courier');
        $config->testMode = true;

        $this->campaignModel   = new CampaignModel();
        $this->contactModel    = new ContactModel();
        $this->enrollmentModel = new DripEnrollmentModel();
        $this->stepModel       = new DripStepModel();
        $this->sendModel       = new SendModel();

        $mailerService = new MailerService(new TemplateService(sys_get_temp_dir()), $this->sendModel, $this->campaignModel, config('Courier'));

        $this->dripService = new DripService(
            $this->enrollmentModel,
            $this->stepModel,
            $this->campaignModel,
            $mailerService,
            $this->contactModel,
            $config,
        );

        $this->contactService = new ContactService(
            $this->contactModel,
            new TagModel(),
            $this->enrollmentModel,
            new ContactTagModel(),
        );
        $this->contactService->setDripService($this->dripService);
    }

    public function testFullContactLifecycle(): void
    {
        $campaign = $this->makeDripCampaign(2);

        // 1. Subscribe with tags
        $contact = $this->contactService->subscribe(['email' => 'e2e@example.com'], ['trial']);

        $this->assertSame(ContactStatus::Subscribed, $contact->status);

        // 2. Enroll in drip
        $enrollment = $this->dripService->enroll($contact->id, $campaign->id);

        $this->assertNotNull($enrollment);
        $this->assertSame(EnrollmentStatus::Active, $enrollment->status);

        // 3. Time-travel → run step 1
        $this->enrollmentModel
            ->where('id', $enrollment->id)
            ->set('next_send_at', date('Y-m-d H:i:s', strtotime('-1 hour')))
            ->update();

        $result = $this->dripService->processDue();

        $this->assertSame(1, $result['processed']);

        $step1Send = $this->sendModel
            ->where('contact_id', $contact->id)
            ->where('campaign_id', $campaign->id)
            ->where('drip_step_id', $this->stepModel->where('campaign_id', $campaign->id)->where('position', 1)->first()->id)
            ->first();

        $this->assertNotNull($step1Send);

        $enrollment = $this->enrollmentModel->find($enrollment->id);
        $this->assertSame(EnrollmentStatus::Active, $enrollment->status);
        $this->assertSame(2, (int) $enrollment->current_step);

        // 4. Time-travel → run step 2
        $this->enrollmentModel
            ->where('id', $enrollment->id)
            ->set('next_send_at', date('Y-m-d H:i:s', strtotime('-1 hour')))
            ->update();

        $result = $this->dripService->processDue();

        $this->assertSame(1, $result['processed']);

        $enrollment = $this->enrollmentModel->find($enrollment->id);
        $this->assertSame(EnrollmentStatus::Completed, $enrollment->status);

        // 5. Unsubscribe
        $contact = $this->contactModel->find($contact->id);
        $ok      = $this->contactService->unsubscribeByToken($contact->unsubscribe_token);

        $this->assertSame(UnsubscribeResult::Success, $ok);

        $contact = $this->contactModel->find($contact->id);
        $this->assertSame(ContactStatus::Unsubscribed, $contact->status);

        // 6. Re-subscribe
        $resubscribed = $this->contactService->subscribe(['email' => 'e2e@example.com']);

        $this->assertSame(ContactStatus::Subscribed, $resubscribed->status);
        $this->assertSame($contact->id, $resubscribed->id);

        // 7. No new enrollment created (completed enrollment stays, no new active one)
        $activeEnrollments = $this->enrollmentModel
            ->where('contact_id', $contact->id)
            ->where('campaign_id', $campaign->id)
            ->where('status', EnrollmentStatus::Active->value)
            ->countAllResults();

        $this->assertSame(0, $activeEnrollments);
    }

    private function makeDripCampaign(int $steps): CampaignDTO
    {
        $id = $this->campaignModel->insert([
            'name'       => 'E2E Drip',
            'subject'    => 'E2E',
            'type'       => CampaignType::DripSequence,
            'status'     => CampaignStatus::Draft,
            'view'       => self::BODY_VIEW,
            'from_name'  => 'Sender',
            'from_email' => 'sender@example.com',
        ]);

        for ($i = 1; $i <= $steps; $i++) {
            $this->stepModel->insert([
                'campaign_id' => $id,
                'position'    => $i,
                'view'        => self::BODY_VIEW,
                'subject'     => "Step {$i}",
                'delay_hours' => 24 * $i,
            ]);
        }

        return $this->campaignModel->find($id);
    }
}
