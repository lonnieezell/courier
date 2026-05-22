<?php

declare(strict_types=1);

namespace Tests\Models\Courier;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Myth\Courier\Enums\CampaignStatus;
use Myth\Courier\Enums\CampaignType;
use Myth\Courier\Models\CampaignModel;
use Myth\Courier\Models\ContactModel;
use Myth\Courier\Models\LinkModel;
use Myth\Courier\Models\SendModel;

/**
 * @internal
 */
final class LinkModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = 'Myth\Courier';
    private int $sendId;

    protected function setUp(): void
    {
        parent::setUp();

        $contactId  = (new ContactModel())->insert(['email' => 'link@example.com']);
        $campaignId = (new CampaignModel())->skipValidation(true)->insert([
            'name'       => 'Link Test',
            'subject'    => 'Hello',
            'type'       => CampaignType::Blast,
            'status'     => CampaignStatus::Draft,
            'from_name'  => 'Sender',
            'from_email' => 'sender@example.com',
        ]);
        $this->sendId = (new SendModel())->createPending($contactId, $campaignId, null)->id;
    }

    public function testFindByTokenReturnsNullForMissingToken(): void
    {
        $this->assertNull((new LinkModel())->findByToken('doesnotexist'));
    }

    public function testInsertLinksAndFindByToken(): void
    {
        $model = new LinkModel();
        $model->insertLinks($this->sendId, ['tok001' => 'https://example.com/page']);

        $link = $model->findByToken('tok001');

        $this->assertNotNull($link);
        $this->assertSame($this->sendId, $link->send_id);
        $this->assertSame('https://example.com/page', $link->url);
    }

    public function testInsertLinksHandlesMultipleLinks(): void
    {
        $model = new LinkModel();
        $model->insertLinks($this->sendId, [
            'tok001' => 'https://example.com/a',
            'tok002' => 'https://example.com/b',
        ]);

        $this->assertNotNull($model->findByToken('tok001'));
        $this->assertNotNull($model->findByToken('tok002'));
    }
}
