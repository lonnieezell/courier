<?php

declare(strict_types=1);

namespace Tests\Controllers\Courier;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Myth\Courier\Enums\CampaignStatus;
use Myth\Courier\Enums\CampaignType;
use Myth\Courier\Models\CampaignModel;
use Myth\Courier\Models\ContactModel;
use Myth\Courier\Models\EventModel;
use Myth\Courier\Models\SendModel;

/**
 * @internal
 */
final class CourierControllerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh   = true;
    protected $namespace = 'Myth\Courier';
    private int $contactId;
    private int $campaignId;

    /**
     * @var array<int, array{0: string, 1: string, 2: string}>
     */
    private array $courierRoutes = [
        ['GET', 'courier/open/(:segment)', '\Myth\Courier\Controllers\CourierController::open/$1'],
        ['GET', 'courier/click/(:segment)', '\Myth\Courier\Controllers\CourierController::click/$1'],
        ['GET', 'courier/unsubscribe/(:segment)', '\Myth\Courier\Controllers\CourierController::unsubscribe/$1'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->contactId  = (new ContactModel())->insert(['email' => 'tracking@example.com']);
        $this->campaignId = (new CampaignModel())->skipValidation(true)->insert([
            'name'       => 'Track Test',
            'subject'    => 'Hello',
            'type'       => CampaignType::Blast,
            'status'     => CampaignStatus::Draft,
            'from_name'  => 'Sender',
            'from_email' => 'sender@example.com',
        ]);
    }

    public function testOpenWithValidTokenRecordsEventAndReturnsGif(): void
    {
        $send   = (new SendModel())->createPending($this->contactId, $this->campaignId, null);
        $result = $this->withRoutes($this->courierRoutes)->get('courier/open/' . $send->open_token);

        $result->assertStatus(200);
        $result->assertHeader('Content-Type', 'image/gif');

        $this->assertSame(
            1,
            (new EventModel())->where('send_id', $send->id)->where('type', 'open')->countAllResults(),
        );

        $updated = (new SendModel())->find($send->id);
        $this->assertNotNull($updated->opened_at);
    }

    public function testOpenWithInvalidTokenStillReturnsGif(): void
    {
        $result = $this->withRoutes($this->courierRoutes)->get('courier/open/totallybadtoken');

        $result->assertStatus(200);
        $result->assertHeader('Content-Type', 'image/gif');
    }

    public function testOpenIsIdempotent(): void
    {
        $send = (new SendModel())->createPending($this->contactId, $this->campaignId, null);
        $this->withRoutes($this->courierRoutes)->get('courier/open/' . $send->open_token);
        $firstOpenedAt = (new SendModel())->find($send->id)->opened_at;

        $this->withRoutes($this->courierRoutes)->get('courier/open/' . $send->open_token);
        $secondOpenedAt = (new SendModel())->find($send->id)->opened_at;

        $this->assertSame($firstOpenedAt, $secondOpenedAt);
    }

    public function testClickWithValidTokenRecordsEventAndRedirects(): void
    {
        $send   = (new SendModel())->createPending($this->contactId, $this->campaignId, null);
        $result = $this->withRoutes($this->courierRoutes)->get(
            'courier/click/' . $send->click_token . '?url=' . urlencode('https://example.com'),
        );

        $result->assertStatus(302);
        $result->assertRedirectTo('https://example.com');

        $this->assertSame(
            1,
            (new EventModel())->where('send_id', $send->id)->where('type', 'click')->countAllResults(),
        );
    }

    public function testClickRejectsNonHttpUrls(): void
    {
        $send   = (new SendModel())->createPending($this->contactId, $this->campaignId, null);
        $result = $this->withRoutes($this->courierRoutes)->get(
            'courier/click/' . $send->click_token . '?url=' . urlencode('javascript:alert(1)'),
        );

        $result->assertStatus(302);
        $result->assertRedirectTo('/');
    }

    public function testClickWithInvalidTokenRedirectsToRoot(): void
    {
        $result = $this->withRoutes($this->courierRoutes)->get('courier/click/badtoken');

        $result->assertStatus(302);
        $result->assertRedirectTo('/');
    }

    public function testUnsubscribeWithValidTokenShowsSuccessView(): void
    {
        $contact = (new ContactModel())->find($this->contactId);
        $result  = $this->withRoutes($this->courierRoutes)->get('courier/unsubscribe/' . $contact->unsubscribe_token);

        $result->assertStatus(200);
        $result->assertSee('unsubscribed');
    }

    public function testUnsubscribeWithInvalidTokenShowsInvalidViewAnd404(): void
    {
        $result = $this->withRoutes($this->courierRoutes)->get('courier/unsubscribe/badtoken');

        $result->assertStatus(404);
    }
}
