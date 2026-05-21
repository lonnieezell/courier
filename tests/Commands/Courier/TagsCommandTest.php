<?php

declare(strict_types=1);

namespace Tests\Commands\Courier;

use CodeIgniter\CLI\CLI;
use CodeIgniter\CLI\Commands;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Myth\Courier\Commands\TagsCommand;
use Myth\Courier\Models\TagModel;

/**
 * @internal
 */
final class TagsCommandTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = 'Myth\Courier';
    private TagsCommand $command;
    private TagModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model   = new TagModel();
        $this->command = new TagsCommand(service('logger'), $this->createStub(Commands::class));
    }

    private function setArgv(array $args): void
    {
        $_SERVER['argv'] = array_merge(['spark', 'courier:tags'], $args);
        CLI::init();
    }

    public function testListRunsWithoutThrowing(): void
    {
        $this->expectNotToPerformAssertions();
        $this->setArgv(['list']);
        $this->command->run(['list']);
    }

    public function testCreateInsertsTag(): void
    {
        $this->setArgv(['create', '--slug', 'beta', '--label', 'Beta Users']);
        $this->command->run(['create']);

        $tag = $this->model->where('slug', 'beta')->first();
        $this->assertNotNull($tag);
        $this->assertSame('Beta Users', $tag->label);
    }

    public function testDeleteRemovesTag(): void
    {
        $this->model->insert(['slug' => 'old-tag', 'label' => 'Old Tag']);

        $this->setArgv(['delete', '--slug', 'old-tag', '--force']);
        $this->command->run(['delete']);

        $this->assertNull($this->model->where('slug', 'old-tag')->first());
    }

    public function testDeleteUnknownSlugDoesNotThrow(): void
    {
        $this->expectNotToPerformAssertions();
        $this->setArgv(['delete', '--slug', 'nonexistent', '--force']);
        $this->command->run(['delete']);
    }
}
