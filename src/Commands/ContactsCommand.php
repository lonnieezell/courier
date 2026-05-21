<?php

declare(strict_types=1);

namespace Myth\Courier\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Myth\Courier\Models\ContactModel;

/**
 * Manage courier contacts from the command line.
 *
 * Usage:
 *   php spark courier:contacts list [--status=] [--tag=] [--limit=20]
 *   php spark courier:contacts show --email=
 *   php spark courier:contacts subscribe [--email=] [--first-name=] [--last-name=] [--tags=tag1,tag2]
 *   php spark courier:contacts unsubscribe [--email=] [--force]
 *   php spark courier:contacts tag --email= --tags=tag1,tag2
 *   php spark courier:contacts untag --email= --tags=tag1,tag2
 */
class ContactsCommand extends BaseCommand
{
    protected $group       = 'Courier';
    protected $name        = 'courier:contacts';
    protected $description = 'Manage courier contacts (list, show, subscribe, unsubscribe, tag, untag).';

    /**
     * @param array<int|string, mixed> $params
     */
    public function run(array $params): void
    {
        $action = array_shift($params);

        if ($action === null) {
            CLI::error('No action specified. Available: list, show, subscribe, unsubscribe, tag, untag');

            return;
        }

        match ($action) {
            'list'        => $this->actionList(),
            'show'        => $this->actionShow(),
            'subscribe'   => $this->actionSubscribe(),
            'unsubscribe' => $this->actionUnsubscribe(),
            'tag'         => $this->actionTag(),
            'untag'       => $this->actionUntag(),
            default       => CLI::error("Unknown action '{$action}'. Available: list, show, subscribe, unsubscribe, tag, untag"),
        };
    }

    private function actionList(): void
    {
        $limit  = (int) (CLI::getOption('limit') ?? 20);
        $status = CLI::getOption('status');
        $tag    = CLI::getOption('tag');

        $model = new ContactModel();

        if ($status !== null) {
            $model->where('status', $status);
        }

        if ($tag !== null) {
            $model->join('courier_contact_tags', 'courier_contact_tags.contact_id = courier_contacts.id')
                ->join('courier_tags', 'courier_tags.id = courier_contact_tags.tag_id')
                ->where('courier_tags.slug', $tag);
        }

        $contacts = $model->orderBy('email')->findAll($limit);

        if ($contacts === []) {
            CLI::write('No contacts found.');

            return;
        }

        $rows = array_map(
            static fn ($c) => [$c->id, $c->email, $c->first_name ?? '', $c->last_name ?? '', $c->status->value],
            $contacts,
        );
        CLI::table($rows, ['ID', 'Email', 'First Name', 'Last Name', 'Status']);
    }

    private function actionShow(): void
    {
        $email   = CLI::getOption('email') ?? CLI::prompt('Email');
        $service = service('contactService');
        $contact = $service->getContact($email);

        if ($contact === null) {
            CLI::error("Contact '{$email}' not found.");

            return;
        }

        CLI::table([
            [$contact->id, $contact->email, $contact->first_name ?? '', $contact->last_name ?? '', $contact->status->value],
        ], ['ID', 'Email', 'First Name', 'Last Name', 'Status']);
    }

    private function actionSubscribe(): void
    {
        $email     = CLI::getOption('email') ?? CLI::prompt('Email');
        $firstName = CLI::getOption('first-name') ?? '';
        $lastName  = CLI::getOption('last-name') ?? '';
        $tagsRaw   = CLI::getOption('tags') ?? '';

        $tags = $tagsRaw !== '' ? explode(',', $tagsRaw) : [];

        $data = array_filter([
            'email'      => $email,
            'first_name' => $firstName,
            'last_name'  => $lastName,
        ]);

        $service = service('contactService');
        $contact = $service->subscribe($data, $tags);

        CLI::write("Subscribed: {$contact->email}", 'green');
    }

    private function actionUnsubscribe(): void
    {
        $email   = CLI::getOption('email') ?? CLI::prompt('Email');
        $service = service('contactService');

        if (! CLI::getOption('force') && CLI::prompt("Unsubscribe '{$email}'?", ['y', 'n']) !== 'y') {
            CLI::write('Aborted.', 'yellow');

            return;
        }

        $contact = $service->getContact($email);

        if ($contact === null) {
            CLI::error("Contact '{$email}' not found.");

            return;
        }

        $service->unsubscribeByToken($contact->unsubscribe_token);

        CLI::write("Unsubscribed: {$email}", 'green');
    }

    private function actionTag(): void
    {
        $service = service('contactService');
        $this->applyOrRemoveTags($service->applyTags(...), 'Tags applied to %s.');
    }

    private function actionUntag(): void
    {
        $service = service('contactService');
        $this->applyOrRemoveTags($service->removeTags(...), 'Tags removed from %s.');
    }

    /**
     * @param callable(int, list<string>): mixed $serviceCall
     */
    private function applyOrRemoveTags(callable $serviceCall, string $successMessage): void
    {
        $email   = CLI::getOption('email') ?? CLI::prompt('Email');
        $tagsRaw = CLI::getOption('tags') ?? CLI::prompt('Tags (comma-separated slugs)');
        $tags    = explode(',', $tagsRaw);

        $model   = new ContactModel();
        $contact = $model->where('email', $email)->first();

        if ($contact === null) {
            CLI::error("Contact '{$email}' not found.");

            return;
        }

        $serviceCall($contact->id, $tags);
        CLI::write(sprintf($successMessage, $email), 'green');
    }
}
