<?php

declare(strict_types=1);

namespace Tests\Commands\Courier;

use CodeIgniter\CLI\CLI;
use CodeIgniter\CLI\Commands;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use Myth\Courier\Commands\ContactsCommand;
use Myth\Courier\Enums\ContactStatus;
use Myth\Courier\Models\ContactModel;
use Myth\Courier\Models\ContactTagModel;
use Myth\Courier\Models\DripEnrollmentModel;
use Myth\Courier\Models\TagModel;
use Myth\Courier\Services\ContactService;

/**
 * @internal
 */
final class ContactsCommandTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = 'Myth\Courier';
    private ContactsCommand $command;
    private ContactModel $contactModel;
    private TagModel $tagModel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contactModel = new ContactModel();
        $this->tagModel     = new TagModel();

        $contactService = new ContactService(
            $this->contactModel,
            $this->tagModel,
            new DripEnrollmentModel(),
            new ContactTagModel(),
        );

        Services::injectMock('contactService', $contactService);
        $this->command = new ContactsCommand(service('logger'), $this->createStub(Commands::class));
    }

    protected function tearDown(): void
    {
        Services::reset();
        parent::tearDown();
    }

    private function setArgv(array $args): void
    {
        $_SERVER['argv'] = array_merge(['spark', 'courier:contacts'], $args);
        CLI::init();
    }

    public function testSubscribeCreatesContact(): void
    {
        $this->setArgv(['subscribe', '--email', 'alice@example.com', '--first-name', 'Alice']);
        $this->command->run(['subscribe']);

        $contact = $this->contactModel->where('email', 'alice@example.com')->first();
        $this->assertNotNull($contact);
        $this->assertSame('Alice', $contact->first_name);
        $this->assertSame(ContactStatus::Subscribed, $contact->status);
    }

    public function testSubscribeWithTagsLinksTag(): void
    {
        $this->setArgv(['subscribe', '--email', 'bob@example.com', '--tags', 'beta']);
        $this->command->run(['subscribe']);

        $contact = $this->contactModel->where('email', 'bob@example.com')->first();
        $this->assertNotNull($contact);

        $tag = $this->tagModel->where('slug', 'beta')->first();
        $this->assertNotNull($tag);
    }

    public function testUnsubscribeChangesStatus(): void
    {
        $this->contactModel->insert([
            'email'  => 'carol@example.com',
            'status' => ContactStatus::Subscribed,
        ]);

        $this->setArgv(['unsubscribe', '--email', 'carol@example.com', '--force']);
        $this->command->run(['unsubscribe']);

        $contact = $this->contactModel->where('email', 'carol@example.com')->first();
        $this->assertSame(ContactStatus::Unsubscribed, $contact->status);
    }

    public function testTagAppliesTagsToContact(): void
    {
        $this->contactModel->insert([
            'email'  => 'dan@example.com',
            'status' => ContactStatus::Subscribed,
        ]);
        $this->tagModel->insert(['slug' => 'vip', 'label' => 'VIP']);

        $this->setArgv(['tag', '--email', 'dan@example.com', '--tags', 'vip']);
        $this->command->run(['tag']);

        $this->seeInDatabase('courier_contact_tags', [
            'contact_id' => $this->contactModel->where('email', 'dan@example.com')->first()->id,
        ]);
    }

    public function testUntagRemovesTagsFromContact(): void
    {
        $contactId = (int) $this->contactModel->insert([
            'email'  => 'eve@example.com',
            'status' => ContactStatus::Subscribed,
        ]);
        $tagId = (int) $this->tagModel->insert(['slug' => 'beta', 'label' => 'Beta']);
        (new ContactTagModel())->insert(['contact_id' => $contactId, 'tag_id' => $tagId]);

        $this->setArgv(['untag', '--email', 'eve@example.com', '--tags', 'beta']);
        $this->command->run(['untag']);

        $this->dontSeeInDatabase('courier_contact_tags', ['contact_id' => $contactId]);
    }

    public function testListRunsWithoutThrowing(): void
    {
        $this->expectNotToPerformAssertions();
        $this->setArgv(['list']);
        $this->command->run(['list']);
    }

    public function testShowRunsWithoutThrowingForKnownEmail(): void
    {
        $this->contactModel->insert([
            'email'  => 'frank@example.com',
            'status' => ContactStatus::Subscribed,
        ]);

        $this->setArgv(['show', '--email', 'frank@example.com']);
        $this->expectNotToPerformAssertions();
        $this->command->run(['show']);
    }
}
