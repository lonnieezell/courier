<?php

declare(strict_types=1);

namespace Tests\Services\Courier;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Myth\Courier\Enums\ContactStatus;
use Myth\Courier\Models\ContactModel;
use Myth\Courier\Models\ContactTagModel;
use Myth\Courier\Models\SegmentModel;
use Myth\Courier\Models\TagModel;
use Myth\Courier\Services\SegmentService;

/**
 * @internal
 */
final class SegmentServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = 'Myth\Courier';
    private SegmentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new SegmentService(
            new ContactModel(),
            new SegmentModel(),
        );
    }

    public function testResolveExcludesUnsubscribedContacts(): void
    {
        $contactModel = new ContactModel();
        $segmentModel = new SegmentModel();

        $contactModel->insert(['email' => 'sub@example.com', 'status' => ContactStatus::Subscribed]);
        $contactModel->insert(['email' => 'unsub@example.com', 'status' => ContactStatus::Unsubscribed]);

        // @phpstan-ignore argument.type
        $segmentId = $segmentModel->skipValidation(true)->insert([
            'name'       => 'All contacts',
            'rules'      => [],
            'match_mode' => 'all',
        ]);

        $results = $this->service->resolve($segmentId);

        $emails = array_column($results, 'email');
        $this->assertContains('sub@example.com', $emails);
        $this->assertNotContains('unsub@example.com', $emails);
    }

    public function testResolveByTagSlugsExcludesUnsubscribedContacts(): void
    {
        $tagModel     = new TagModel();
        $contactModel = new ContactModel();
        $pivotModel   = new ContactTagModel();

        $tagId   = $tagModel->skipValidation(true)->insert(['slug' => 'news', 'label' => 'News']);
        $subId   = $contactModel->insert(['email' => 'sub@example.com', 'status' => ContactStatus::Subscribed]);
        $unsubId = $contactModel->insert(['email' => 'unsub@example.com', 'status' => ContactStatus::Unsubscribed]);

        $pivotModel->insert(['contact_id' => $subId, 'tag_id' => $tagId]);
        $pivotModel->insert(['contact_id' => $unsubId, 'tag_id' => $tagId]);

        $results = $this->service->resolveByTagSlugs(['news']);

        $emails = array_column($results, 'email');
        $this->assertContains('sub@example.com', $emails);
        $this->assertNotContains('unsub@example.com', $emails);
    }

    public function testResolveByTagSlugsReturnsOnlyContactsWithAllTags(): void
    {
        $tagModel     = new TagModel();
        $contactModel = new ContactModel();
        $pivotModel   = new ContactTagModel();

        $tagAId = $tagModel->skipValidation(true)->insert(['slug' => 'tag-a', 'label' => 'Tag A']);
        $tagBId = $tagModel->skipValidation(true)->insert(['slug' => 'tag-b', 'label' => 'Tag B']);

        $c1Id = $contactModel->insert(['email' => 'both@example.com', 'status' => ContactStatus::Subscribed]);
        $c2Id = $contactModel->insert(['email' => 'only-a@example.com', 'status' => ContactStatus::Subscribed]);
        $c3Id = $contactModel->insert(['email' => 'only-b@example.com', 'status' => ContactStatus::Subscribed]);

        $pivotModel->insert(['contact_id' => $c1Id, 'tag_id' => $tagAId]);
        $pivotModel->insert(['contact_id' => $c1Id, 'tag_id' => $tagBId]);
        $pivotModel->insert(['contact_id' => $c2Id, 'tag_id' => $tagAId]);
        $pivotModel->insert(['contact_id' => $c3Id, 'tag_id' => $tagBId]);

        $results = $this->service->resolveByTagSlugs(['tag-a', 'tag-b']);

        $this->assertCount(1, $results);
        $this->assertSame('both@example.com', $results[0]->email);
    }

    public function testBuildQueryWithTagRuleFiltersCorrectly(): void
    {
        $tagModel     = new TagModel();
        $contactModel = new ContactModel();
        $pivotModel   = new ContactTagModel();
        $segmentModel = new SegmentModel();

        $tagId = $tagModel->skipValidation(true)->insert(['slug' => 'newsletter', 'label' => 'Newsletter']);
        $c1Id  = $contactModel->insert(['email' => 'sub@example.com', 'status' => ContactStatus::Subscribed]);
        $contactModel->insert(['email' => 'nosub@example.com']);

        $pivotModel->insert(['contact_id' => $c1Id, 'tag_id' => $tagId]);

        // @phpstan-ignore argument.type (JsonCast requires PHP array; CI4 Model::insert() types are too strict)
        $segmentId = $segmentModel->skipValidation(true)->insert([
            'name'       => 'Newsletter subscribers',
            'rules'      => [['field' => 'tag', 'op' => 'in', 'value' => 'newsletter']],
            'match_mode' => 'all',
        ]);

        $results = $this->service->resolve($segmentId);

        $this->assertCount(1, $results);
        $this->assertSame('sub@example.com', $results[0]->email);
    }

    public function testBuildQueryWithSubscribedAtGteRuleFiltersCorrectly(): void
    {
        $contactModel = new ContactModel();
        $segmentModel = new SegmentModel();

        $contactModel->skipValidation(true)->insert([
            'email'         => 'early@example.com',
            'status'        => ContactStatus::Subscribed,
            'subscribed_at' => '2026-01-01 00:00:00',
        ]);
        $contactModel->skipValidation(true)->insert([
            'email'         => 'late@example.com',
            'status'        => ContactStatus::Subscribed,
            'subscribed_at' => '2026-06-01 00:00:00',
        ]);

        // @phpstan-ignore argument.type (JsonCast requires PHP array; CI4 Model::insert() types are too strict)
        $segmentId = $segmentModel->skipValidation(true)->insert([
            'name'       => 'Recent',
            'rules'      => [['field' => 'subscribed_at', 'op' => 'gte', 'value' => '2026-04-01']],
            'match_mode' => 'all',
        ]);

        $results = $this->service->resolve($segmentId);

        $this->assertCount(1, $results);
        $this->assertSame('late@example.com', $results[0]->email);
    }

    public function testBuildQueryMatchModeAnyReturnsUnion(): void
    {
        $tagModel     = new TagModel();
        $contactModel = new ContactModel();
        $pivotModel   = new ContactTagModel();
        $segmentModel = new SegmentModel();

        $vipId        = $tagModel->skipValidation(true)->insert(['slug' => 'vip', 'label' => 'VIP']);
        $newsletterId = $tagModel->skipValidation(true)->insert(['slug' => 'newsletter', 'label' => 'Newsletter']);

        $c1Id = $contactModel->insert(['email' => 'vip@example.com', 'status' => ContactStatus::Subscribed]);
        $c2Id = $contactModel->insert(['email' => 'newsletter@example.com', 'status' => ContactStatus::Subscribed]);
        $contactModel->insert(['email' => 'neither@example.com']);

        $pivotModel->insert(['contact_id' => $c1Id, 'tag_id' => $vipId]);
        $pivotModel->insert(['contact_id' => $c2Id, 'tag_id' => $newsletterId]);

        // @phpstan-ignore argument.type (JsonCast requires PHP array; CI4 Model::insert() types are too strict)
        $segmentId = $segmentModel->skipValidation(true)->insert([
            'name'  => 'VIP or Newsletter',
            'rules' => [
                ['field' => 'tag', 'op' => 'in', 'value' => 'vip'],
                ['field' => 'tag', 'op' => 'in', 'value' => 'newsletter'],
            ],
            'match_mode' => 'any',
        ]);

        $results = $this->service->resolve($segmentId);

        $this->assertCount(2, $results);
    }

    public function testResolveBySegmentAndTagSlugsReturnsIntersectionSubscribedOnly(): void
    {
        $tagModel     = new TagModel();
        $contactModel = new ContactModel();
        $pivotModel   = new ContactTagModel();
        $segmentModel = new SegmentModel();

        $vipId = $tagModel->skipValidation(true)->insert(['slug' => 'vip', 'label' => 'VIP']);

        // @phpstan-ignore argument.type
        $segmentId = $segmentModel->skipValidation(true)->insert([
            'name'       => 'Web source',
            'rules'      => [['field' => 'source', 'op' => 'eq', 'value' => 'web']],
            'match_mode' => 'all',
        ]);

        // In segment AND has tag → should be returned
        $matchId = $contactModel->skipValidation(true)->insert(['email' => 'web-vip@example.com', 'status' => ContactStatus::Subscribed, 'source' => 'web']);
        // In segment, no tag
        $contactModel->skipValidation(true)->insert(['email' => 'web-plain@example.com', 'status' => ContactStatus::Subscribed, 'source' => 'web']);
        // Has tag, not in segment
        $apiVipId = $contactModel->skipValidation(true)->insert(['email' => 'api-vip@example.com', 'status' => ContactStatus::Subscribed, 'source' => 'api']);
        // Unsubscribed, would otherwise match both
        $unsubId = $contactModel->skipValidation(true)->insert(['email' => 'unsub@example.com', 'status' => ContactStatus::Unsubscribed, 'source' => 'web']);

        $pivotModel->insert(['contact_id' => $matchId, 'tag_id' => $vipId]);
        $pivotModel->insert(['contact_id' => $apiVipId, 'tag_id' => $vipId]);
        $pivotModel->insert(['contact_id' => $unsubId, 'tag_id' => $vipId]);

        $results = $this->service->resolveBySegmentAndTagSlugs($segmentId, ['vip']);

        $this->assertCount(1, $results);
        $this->assertSame('web-vip@example.com', $results[0]->email);
    }

    public function testPreviewCountReturnsCorrectInteger(): void
    {
        $tagModel     = new TagModel();
        $contactModel = new ContactModel();
        $pivotModel   = new ContactTagModel();
        $segmentModel = new SegmentModel();

        $tagId = $tagModel->skipValidation(true)->insert(['slug' => 'newsletter', 'label' => 'Newsletter']);
        $c1Id  = $contactModel->insert(['email' => 'sub@example.com', 'status' => ContactStatus::Subscribed]);
        $contactModel->insert(['email' => 'nosub@example.com']);

        $pivotModel->insert(['contact_id' => $c1Id, 'tag_id' => $tagId]);

        // @phpstan-ignore argument.type (JsonCast requires PHP array; CI4 Model::insert() types are too strict)
        $segmentId = $segmentModel->skipValidation(true)->insert([
            'name'       => 'Newsletter subscribers',
            'rules'      => [['field' => 'tag', 'op' => 'in', 'value' => 'newsletter']],
            'match_mode' => 'all',
        ]);

        $count = $this->service->previewCount($segmentId);

        $this->assertSame(1, $count);
        $this->assertSame(count($this->service->resolve($segmentId)), $count);
    }
}
