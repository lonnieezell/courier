<?php

declare(strict_types=1);

namespace Myth\Courier\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use DateTime;
use Exception;
use Myth\Courier\Enums\CampaignStatus;
use Myth\Courier\Models\CampaignModel;

/**
 * Manage courier campaigns from the command line.
 *
 * Usage:
 *   php spark courier:campaigns list [--status=] [--limit=20]
 *   php spark courier:campaigns show --id=
 *   php spark courier:campaigns create [--name=] [--subject=] [--from-name=] [--from-email=] [--view=]
 *   php spark courier:campaigns schedule --id= --at="2026-06-01 09:00:00"
 *   php spark courier:campaigns pause --id= [--force]
 *   php spark courier:campaigns resume --id=
 *   php spark courier:campaigns cancel --id= [--force]
 *   php spark courier:campaigns stats --id=
 */
class CampaignsCommand extends BaseCommand
{
    protected $group       = 'Courier';
    protected $name        = 'courier:campaigns';
    protected $description = 'Manage courier campaigns (list, show, create, schedule, pause, resume, cancel, stats).';

    /**
     * @param array<int|string, mixed> $params
     */
    public function run(array $params): void
    {
        $action = array_shift($params);

        if ($action === null) {
            CLI::error('No action specified. Available: list, show, create, schedule, pause, resume, cancel, stats');

            return;
        }

        match ($action) {
            'list'     => $this->actionList(),
            'show'     => $this->actionShow(),
            'create'   => $this->actionCreate(),
            'schedule' => $this->actionSchedule(),
            'pause'    => $this->actionPause(),
            'resume'   => $this->actionResume(),
            'cancel'   => $this->actionCancel(),
            'stats'    => $this->actionStats(),
            default    => CLI::error("Unknown action '{$action}'. Available: list, show, create, schedule, pause, resume, cancel, stats"),
        };
    }

    private function actionList(): void
    {
        $limit  = (int) (CLI::getOption('limit') ?? 20);
        $status = CLI::getOption('status');
        $model  = new CampaignModel();

        if ($status !== null) {
            $model->where('status', $status);
        }

        $campaigns = $model->orderBy('created_at', 'DESC')->findAll($limit);

        if ($campaigns === []) {
            CLI::write('No campaigns found.');

            return;
        }

        $rows = array_map(
            static fn ($c) => [$c->id, $c->name, $c->type->value, $c->status->value, $c->scheduled_at ?? '—'],
            $campaigns,
        );
        CLI::table($rows, ['ID', 'Name', 'Type', 'Status', 'Scheduled At']);
    }

    private function actionShow(): void
    {
        $id    = (int) (CLI::getOption('id') ?? CLI::prompt('Campaign ID'));
        $model = new CampaignModel();
        $c     = $model->find($id);

        if ($c === null) {
            CLI::error("Campaign #{$id} not found.");

            return;
        }

        CLI::table([[$c->id, $c->name, $c->type->value, $c->status->value, $c->from_email, $c->scheduled_at ?? '—']], [
            'ID', 'Name', 'Type', 'Status', 'From Email', 'Scheduled At',
        ]);
    }

    private function actionCreate(): void
    {
        $name      = CLI::getOption('name') ?? CLI::prompt('Campaign name');
        $subject   = CLI::getOption('subject') ?? CLI::prompt('Subject line');
        $fromName  = CLI::getOption('from-name') ?? CLI::prompt('From name');
        $fromEmail = CLI::getOption('from-email') ?? CLI::prompt('From email');
        $view      = CLI::getOption('view') ?? '';

        $data = [
            'name'       => $name,
            'subject'    => $subject,
            'from_name'  => $fromName,
            'from_email' => $fromEmail,
        ];

        if ($view !== '') {
            $data['view'] = $view;
        }

        $service  = service('campaignService');
        $campaign = $service->create($data);

        CLI::write("Campaign #{$campaign->id} '{$campaign->name}' created (draft).", 'green');
    }

    private function actionSchedule(): void
    {
        $id = (int) (CLI::getOption('id') ?? CLI::prompt('Campaign ID'));
        $at = CLI::getOption('at') ?? CLI::prompt('Send at (Y-m-d H:i:s)');

        try {
            $dateTime = new DateTime($at);
        } catch (Exception) {
            CLI::error("Invalid date format: {$at}");

            return;
        }

        $service = service('campaignService');
        $service->schedule($id, $dateTime);

        CLI::write("Campaign #{$id} scheduled for {$at}.", 'green');
    }

    private function actionPause(): void
    {
        $this->confirmAndSetStatus('Pause', CampaignStatus::Paused);
    }

    private function actionResume(): void
    {
        $id      = (int) (CLI::getOption('id') ?? CLI::prompt('Campaign ID'));
        $service = service('campaignService');
        $service->resume($id);

        CLI::write("Campaign #{$id} resumed.", 'green');
    }

    private function actionCancel(): void
    {
        $this->confirmAndSetStatus('Cancel', CampaignStatus::Cancelled);
    }

    private function confirmAndSetStatus(string $verb, CampaignStatus $status): void
    {
        $id = (int) (CLI::getOption('id') ?? CLI::prompt('Campaign ID'));

        if (! CLI::getOption('force') && CLI::prompt("{$verb} campaign #{$id}?", ['y', 'n']) !== 'y') {
            CLI::write('Aborted.', 'yellow');

            return;
        }

        $model    = new CampaignModel();
        $campaign = $model->find($id);

        if ($campaign === null) {
            CLI::error("Campaign #{$id} not found.");

            return;
        }

        $model->update($id, ['status' => $status]);
        CLI::write("Campaign #{$id} {$status->value}.", 'green');
    }

    private function actionStats(): void
    {
        $id      = (int) (CLI::getOption('id') ?? CLI::prompt('Campaign ID'));
        $service = service('campaignService');
        $stats   = $service->getCampaignStats($id);

        $openRate  = $stats['sent'] > 0 ? round($stats['opened'] / $stats['sent'] * 100, 1) : 0;
        $clickRate = $stats['sent'] > 0 ? round($stats['clicked'] / $stats['sent'] * 100, 1) : 0;

        CLI::table([[
            $stats['total'],
            $stats['sent'],
            $stats['failed'],
            $stats['opened'],
            $stats['clicked'],
            $openRate . '%',
            $clickRate . '%',
        ]], ['Total', 'Sent', 'Failed', 'Opened', 'Clicked', 'Open Rate', 'Click Rate']);
    }
}
