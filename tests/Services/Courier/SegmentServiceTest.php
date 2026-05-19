<?php

declare(strict_types=1);

/**
 * This file is part of YourVendor/YourPackage.
 *
 * (c) Your Name <you@example.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Tests\Services\Courier;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
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

    public function testResolveByTagSlugsReturnsOnlyContactsWithAllTags(): void
    {
        $tagModel     = new TagModel();
        $contactModel = new ContactModel();
        $pivotModel   = new ContactTagModel();

        $tagAId = $tagModel->skipValidation(true)->insert(['slug' => 'tag-a', 'label' => 'Tag A']);
        $tagBId = $tagModel->skipValidation(true)->insert(['slug' => 'tag-b', 'label' => 'Tag B']);

        $c1Id = $contactModel->insert(['email' => 'both@example.com']);
        $c2Id = $contactModel->insert(['email' => 'only-a@example.com']);
        $c3Id = $contactModel->insert(['email' => 'only-b@example.com']);

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
        $c1Id  = $contactModel->insert(['email' => 'sub@example.com']);
        $c2Id  = $contactModel->insert(['email' => 'nosub@example.com']);

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
            'subscribed_at' => '2026-01-01 00:00:00',
        ]);
        $contactModel->skipValidation(true)->insert([
            'email'         => 'late@example.com',
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

        $c1Id = $contactModel->insert(['email' => 'vip@example.com']);
        $c2Id = $contactModel->insert(['email' => 'newsletter@example.com']);
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

    public function testPreviewCountReturnsCorrectInteger(): void
    {
        $tagModel     = new TagModel();
        $contactModel = new ContactModel();
        $pivotModel   = new ContactTagModel();
        $segmentModel = new SegmentModel();

        $tagId = $tagModel->skipValidation(true)->insert(['slug' => 'newsletter', 'label' => 'Newsletter']);
        $c1Id  = $contactModel->insert(['email' => 'sub@example.com']);
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
