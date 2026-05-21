<?php

declare(strict_types=1);

namespace Tests\Commands\Courier;

use CodeIgniter\CLI\CLI;
use CodeIgniter\CLI\Commands;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Myth\Courier\Commands\SegmentsCommand;
use Myth\Courier\Models\SegmentModel;

/**
 * @internal
 */
final class SegmentsCommandTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = 'Myth\Courier';
    private SegmentsCommand $command;
    private SegmentModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model   = new SegmentModel();
        $this->command = new SegmentsCommand(service('logger'), $this->createStub(Commands::class));
    }

    private function setArgv(array $args): void
    {
        $_SERVER['argv'] = array_merge(['spark', 'courier:segments'], $args);
        CLI::init();
    }

    private function insertSegment(string $name = 'Test Segment'): int
    {
        return (int) $this->model->insert([
            'name'       => $name,
            'match_mode' => 'all',
            'rules'      => json_decode(json_encode([['field' => 'status', 'op' => 'eq', 'value' => 'subscribed']]), true),
        ]);
    }

    public function testCreateInsertsSegment(): void
    {
        $this->setArgv([
            'create',
            '--name', 'Pro Users',
            '--match-mode', 'all',
            '--rules', json_encode([['field' => 'custom_fields->plan', 'op' => '=', 'value' => 'pro']]),
        ]);
        $this->command->run(['create']);

        $segment = $this->model->where('name', 'Pro Users')->first();
        $this->assertNotNull($segment);
        $this->assertSame('all', $segment->match_mode);
    }

    public function testDeleteRemovesSegment(): void
    {
        $id = $this->insertSegment('To Delete');

        $this->setArgv(['delete', '--id', (string) $id, '--force']);
        $this->command->run(['delete']);

        $this->assertNull($this->model->find($id));
    }

    public function testShowRunsWithoutThrowingForKnownId(): void
    {
        $id = $this->insertSegment();

        $this->setArgv(['show', '--id', (string) $id]);
        $this->expectNotToPerformAssertions();
        $this->command->run(['show']);
    }

    public function testListRunsWithoutThrowing(): void
    {
        $this->expectNotToPerformAssertions();
        $this->setArgv(['list']);
        $this->command->run(['list']);
    }
}
