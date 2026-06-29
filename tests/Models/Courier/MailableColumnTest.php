<?php

declare(strict_types=1);

namespace Tests\Models\Courier;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Myth\Courier\Enums\CampaignStatus;
use Myth\Courier\Enums\CampaignType;
use Myth\Courier\Models\CampaignModel;
use Myth\Courier\Models\DripStepModel;

/**
 * Verifies the nullable mailable column round-trips through the campaign and
 * drip-step models onto their DTOs.
 *
 * @internal
 */
final class MailableColumnTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = 'Myth\Courier';

    public function testCampaignPersistsAndHydratesMailable(): void
    {
        $model = new CampaignModel();
        $id    = (int) $model->insert([
            'name'       => 'Mailable Campaign',
            'subject'    => 'Hello',
            'type'       => CampaignType::Blast,
            'mailable'   => 'App\Mail\WelcomeMailable',
            'status'     => CampaignStatus::Draft,
            'from_name'  => 'Sender',
            'from_email' => 'sender@example.com',
        ]);

        $dto = $model->find($id);

        $this->assertSame('App\Mail\WelcomeMailable', $dto->mailable);
    }

    public function testCampaignMailableDefaultsToNull(): void
    {
        $model = new CampaignModel();
        $id    = (int) $model->insert([
            'name'       => 'Plain Campaign',
            'subject'    => 'Hello',
            'type'       => CampaignType::Blast,
            'view'       => 'some/view',
            'status'     => CampaignStatus::Draft,
            'from_name'  => 'Sender',
            'from_email' => 'sender@example.com',
        ]);

        $dto = $model->find($id);

        $this->assertNull($dto->mailable);
    }

    public function testDripStepPersistsAndHydratesMailable(): void
    {
        $campaignId = (int) (new CampaignModel())->insert([
            'name'       => 'Drip Campaign',
            'subject'    => 'Hello',
            'type'       => CampaignType::DripSequence,
            'view'       => 'some/view',
            'status'     => CampaignStatus::Draft,
            'from_name'  => 'Sender',
            'from_email' => 'sender@example.com',
        ]);

        $model = new DripStepModel();
        $id    = (int) $model->insert([
            'campaign_id' => $campaignId,
            'position'    => 1,
            'view'        => 'step/view',
            'subject'     => 'Step 1',
            'mailable'    => 'App\Mail\Step1Mailable',
            'delay_hours' => 24,
        ]);

        $dto = $model->find($id);

        $this->assertSame('App\Mail\Step1Mailable', $dto->mailable);
    }
}
