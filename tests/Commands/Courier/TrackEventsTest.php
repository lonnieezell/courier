<?php

declare(strict_types=1);

namespace Tests\Commands\Courier;

use CodeIgniter\CLI\Commands;
use CodeIgniter\Test\CIUnitTestCase;
use Myth\Courier\Commands\TrackEvents;

/**
 * @internal
 */
final class TrackEventsTest extends CIUnitTestCase
{
    public function testRunCompletesWithoutThrowing(): void
    {
        $this->expectNotToPerformAssertions();

        $command = new TrackEvents(service('logger'), $this->createMock(Commands::class));
        $command->run([]);
    }
}
