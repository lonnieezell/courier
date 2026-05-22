<?php

declare(strict_types=1);

namespace Myth\Courier\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Myth\Courier\Models\CampaignModel;
use Myth\Courier\Models\ContactModel;
use Myth\Courier\Models\DripEnrollmentModel;

/**
 * Manage courier drip enrollments from the command line.
 *
 * Usage:
 *   php spark courier:drips list [--email=] [--campaign=] [--status=active] [--limit=20]
 *   php spark courier:drips enroll [--email=] [--campaign=]
 *   php spark courier:drips cancel --email= --campaign= [--force]
 */
class DripsCommand extends BaseCommand
{
    protected $group       = 'Courier';
    protected $name        = 'courier:drips';
    protected $description = 'Manage courier drip enrollments (list, enroll, cancel).';

    /**
     * @param array<int|string, mixed> $params
     */
    public function run(array $params): void
    {
        $action = array_shift($params);

        if ($action === null) {
            CLI::error('No action specified. Available: list, enroll, cancel');

            return;
        }

        match ($action) {
            'list'   => $this->actionList(),
            'enroll' => $this->actionEnroll(),
            'cancel' => $this->actionCancel(),
            default  => CLI::error("Unknown action '{$action}'. Available: list, enroll, cancel"),
        };
    }

    private function actionList(): void
    {
        $limit    = (int) (CLI::getOption('limit') ?? 20);
        $email    = CLI::getOption('email');
        $campaign = CLI::getOption('campaign');
        $status   = CLI::getOption('status');

        $model = new DripEnrollmentModel();
        $model->join('courier_contacts', 'courier_contacts.id = courier_drip_enrollments.contact_id')
            ->join('courier_campaigns', 'courier_campaigns.id = courier_drip_enrollments.campaign_id')
            ->select('courier_drip_enrollments.*, courier_contacts.email, courier_campaigns.name AS campaign_name');

        if ($email !== null) {
            $model->where('courier_contacts.email', $email);
        }

        if ($campaign !== null) {
            $model->where('courier_campaigns.name', $campaign);
        }

        if ($status !== null) {
            $model->where('courier_drip_enrollments.status', $status);
        }

        $enrollments = $model->orderBy('courier_drip_enrollments.created_at', 'DESC')->findAll($limit);

        if ($enrollments === []) {
            CLI::write('No enrollments found.');

            return;
        }

        $rows = array_map(
            static fn ($e) => [$e->id, $e->email ?? '', $e->campaign_name ?? '', $e->status->value, $e->next_send_at ?? '—'],
            $enrollments,
        );
        CLI::table($rows, ['ID', 'Email', 'Campaign', 'Status', 'Next Send At']);
    }

    private function actionEnroll(): void
    {
        $email        = CLI::getOption('email') ?? CLI::prompt('Contact email');
        $campaignName = CLI::getOption('campaign') ?? CLI::prompt('Campaign name');

        $contact = (new ContactModel())->where('email', $email)->first();

        if ($contact === null) {
            CLI::error("Contact '{$email}' not found.");

            return;
        }

        $service    = service('dripService');
        $enrollment = $service->enrollByName($contact->id, $campaignName);

        if ($enrollment === null) {
            CLI::write("Contact '{$email}' is already enrolled in '{$campaignName}'.", 'yellow');

            return;
        }

        CLI::write("Enrolled '{$email}' in '{$campaignName}'.", 'green');
    }

    private function actionCancel(): void
    {
        $email        = CLI::getOption('email') ?? CLI::prompt('Contact email');
        $campaignName = CLI::getOption('campaign') ?? CLI::prompt('Campaign name');

        if (! CLI::getOption('force') && CLI::prompt("Cancel enrollment for '{$email}' in '{$campaignName}'?", ['y', 'n']) !== 'y') {
            CLI::write('Aborted.', 'yellow');

            return;
        }

        $contact = (new ContactModel())->where('email', $email)->first();

        if ($contact === null) {
            CLI::error("Contact '{$email}' not found.");

            return;
        }

        $campaign = (new CampaignModel())->where('name', $campaignName)->first();

        if ($campaign === null) {
            CLI::error("Campaign '{$campaignName}' not found.");

            return;
        }

        $service = service('dripService');
        $service->cancel($contact->id, $campaign->id);

        CLI::write("Enrollment cancelled for '{$email}' in '{$campaignName}'.", 'green');
    }
}
