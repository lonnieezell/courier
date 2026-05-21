<?php

declare(strict_types=1);

namespace Myth\Courier\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Myth\Courier\Models\TagModel;

/**
 * Manage courier tags from the command line.
 *
 * Usage:
 *   php spark courier:tags list [--limit=20]
 *   php spark courier:tags create [--slug=] [--label=]
 *   php spark courier:tags delete --slug= [--force]
 */
class TagsCommand extends BaseCommand
{
    protected $group       = 'Courier';
    protected $name        = 'courier:tags';
    protected $description = 'Manage courier tags (list, create, delete).';

    /**
     * @param array<int|string, mixed> $params
     */
    public function run(array $params): void
    {
        $action = array_shift($params);

        if ($action === null) {
            CLI::error('No action specified. Available: list, create, delete');

            return;
        }

        match ($action) {
            'list'   => $this->actionList(),
            'create' => $this->actionCreate(),
            'delete' => $this->actionDelete(),
            default  => CLI::error("Unknown action '{$action}'. Available: list, create, delete"),
        };
    }

    private function actionList(): void
    {
        $limit = (int) (CLI::getOption('limit') ?? 20);
        $model = new TagModel();
        $tags  = $model->orderBy('slug')->findAll($limit);

        if ($tags === []) {
            CLI::write('No tags found.');

            return;
        }

        $rows = array_map(static fn ($tag) => [$tag->id, $tag->slug, $tag->label], $tags);
        CLI::table($rows, ['ID', 'Slug', 'Label']);
    }

    private function actionCreate(): void
    {
        $slug  = CLI::getOption('slug') ?? CLI::prompt('Slug');
        $label = CLI::getOption('label') ?? CLI::prompt('Label');

        $model = new TagModel();
        $model->insert(['slug' => $slug, 'label' => $label]);

        CLI::write("Tag '{$slug}' created.", 'green');
    }

    private function actionDelete(): void
    {
        $slug = CLI::getOption('slug') ?? CLI::prompt('Slug');

        $model = new TagModel();
        $tag   = $model->where('slug', $slug)->first();

        if ($tag === null) {
            CLI::error("Tag '{$slug}' not found.");

            return;
        }

        if (! CLI::getOption('force') && CLI::prompt('Delete tag "' . $slug . '"?', ['y', 'n']) !== 'y') {
            CLI::write('Aborted.', 'yellow');

            return;
        }

        $model->delete($tag->id);
        CLI::write("Tag '{$slug}' deleted.", 'green');
    }
}
