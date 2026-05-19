<?php

declare(strict_types=1);

namespace Myth\Courier\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Stub command for processing bounce webhooks or SMTP feedback loops.
 *
 * To add a real bounce handler, extend this class and override run():
 *
 *   protected function processBounce(string $email): void
 *   {
 *       $contact = model(\Myth\Courier\Models\ContactModel::class)
 *           ->where('email', $email)->first();
 *       if ($contact === null) { return; }
 *       model(\Myth\Courier\Models\ContactModel::class)
 *           ->update($contact->id, ['status' => 'bounced']);
 *       service('contactService')->cancelAllDrips($contact->id);
 *       model(\Myth\Courier\Models\EventModel::class)
 *           ->insert(['send_id' => 0, 'type' => 'bounce']);
 *   }
 *
 * Usage:
 *   php spark courier:track-events
 */
class TrackEvents extends BaseCommand
{
    protected $group       = 'Courier';
    protected $name        = 'courier:track-events';
    protected $description = 'Process bounce webhooks or SMTP feedback loops.';

    /**
     * @param array<int|string, mixed> $params
     */
    public function run(array $params): void
    {
        log_message('info', 'TrackEventsTask: no webhook source configured');
        CLI::write('TrackEventsTask: no webhook source configured', 'yellow');
    }
}
