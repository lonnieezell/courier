<?php

declare(strict_types=1);

namespace Myth\Courier\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Myth\Courier\Models\SegmentModel;

/**
 * Manage courier segments from the command line.
 *
 * Usage:
 *   php spark courier:segments list [--limit=20]
 *   php spark courier:segments show --id=
 *   php spark courier:segments create [--name=] [--match-mode=all|any] [--rules=json]
 *   php spark courier:segments delete --id= [--force]
 */
class SegmentsCommand extends BaseCommand
{
    protected $group       = 'Courier';
    protected $name        = 'courier:segments';
    protected $description = 'Manage courier segments (list, show, create, delete).';

    /**
     * @param array<int|string, mixed> $params
     */
    public function run(array $params): void
    {
        $action = array_shift($params);

        if ($action === null) {
            CLI::error('No action specified. Available: list, show, create, delete');

            return;
        }

        match ($action) {
            'list'   => $this->actionList(),
            'show'   => $this->actionShow(),
            'create' => $this->actionCreate(),
            'delete' => $this->actionDelete(),
            default  => CLI::error("Unknown action '{$action}'. Available: list, show, create, delete"),
        };
    }

    private function actionList(): void
    {
        $limit    = (int) (CLI::getOption('limit') ?? 20);
        $model    = new SegmentModel();
        $segments = $model->orderBy('name')->findAll($limit);

        if ($segments === []) {
            CLI::write('No segments found.');

            return;
        }

        $rows = array_map(static fn ($s) => [$s->id, $s->name, $s->match_mode], $segments);
        CLI::table($rows, ['ID', 'Name', 'Match Mode']);
    }

    private function actionShow(): void
    {
        $id    = (int) (CLI::getOption('id') ?? CLI::prompt('Segment ID'));
        $model = new SegmentModel();
        $s     = $model->find($id);

        if ($s === null) {
            CLI::error("Segment #{$id} not found.");

            return;
        }

        $count = service('segmentService')->previewCount($id);

        CLI::table([[$s->id, $s->name, $s->match_mode, $count]], ['ID', 'Name', 'Match Mode', 'Contact Count']);
        CLI::write('Rules: ' . json_encode($s->rules, JSON_PRETTY_PRINT));
    }

    private function actionCreate(): void
    {
        $name      = CLI::getOption('name') ?? CLI::prompt('Segment name');
        $matchMode = CLI::getOption('match-mode') ?? CLI::prompt('Match mode', ['all', 'any']);
        $rulesJson = CLI::getOption('rules');

        if ($rulesJson === null) {
            $rules = [];

            do {
                $field   = CLI::prompt('Rule field (e.g. custom_fields->plan)');
                $op      = CLI::prompt('Operator', ['eq', 'neq', 'gt', 'gte', 'lt', 'lte']);
                $value   = CLI::prompt('Value');
                $rules[] = ['field' => $field, 'op' => $op, 'value' => $value];
                $more    = CLI::prompt('Add another rule?', ['n', 'y']);
            } while ($more === 'y');
        } else {
            $rules = json_decode($rulesJson, true);
        }

        $model = new SegmentModel();
        $id    = (int) $model->insert([
            'name'       => $name,
            'match_mode' => $matchMode,
            'rules'      => $rules,
        ]);

        CLI::write("Segment #{$id} '{$name}' created.", 'green');
    }

    private function actionDelete(): void
    {
        $id    = (int) (CLI::getOption('id') ?? CLI::prompt('Segment ID'));
        $model = new SegmentModel();
        $s     = $model->find($id);

        if ($s === null) {
            CLI::error("Segment #{$id} not found.");

            return;
        }

        if (! CLI::getOption('force') && CLI::prompt("Delete segment '{$s->name}'?", ['y', 'n']) !== 'y') {
            CLI::write('Aborted.', 'yellow');

            return;
        }

        $model->delete($id);
        CLI::write("Segment #{$id} deleted.", 'green');
    }
}
