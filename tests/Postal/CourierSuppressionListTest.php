<?php

declare(strict_types=1);

namespace Tests\Postal;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Myth\Courier\Enums\ContactStatus;
use Myth\Courier\Models\ContactModel;
use Myth\Courier\Postal\CourierSuppressionList;
use Myth\Postal\Address;
use Myth\Postal\SuppressionListInterface;

/**
 * @internal
 */
final class CourierSuppressionListTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = 'Myth\Courier';

    private function seed(string $email, ContactStatus $status): void
    {
        (new ContactModel())->insert([
            'email'  => $email,
            'status' => $status,
        ]);
    }

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(SuppressionListInterface::class, new CourierSuppressionList());
    }

    public function testSubscribedContactIsNotSuppressed(): void
    {
        $this->seed('subscribed@example.com', ContactStatus::Subscribed);

        $this->assertFalse(
            (new CourierSuppressionList())->isSuppressed(new Address('subscribed@example.com')),
        );
    }

    public function testUnsubscribedContactIsSuppressed(): void
    {
        $this->seed('gone@example.com', ContactStatus::Unsubscribed);

        $this->assertTrue(
            (new CourierSuppressionList())->isSuppressed(new Address('gone@example.com')),
        );
    }

    public function testBouncedContactIsSuppressed(): void
    {
        $this->seed('bounce@example.com', ContactStatus::Bounced);

        $this->assertTrue(
            (new CourierSuppressionList())->isSuppressed(new Address('bounce@example.com')),
        );
    }

    public function testComplainedContactIsSuppressed(): void
    {
        $this->seed('spam@example.com', ContactStatus::Complained);

        $this->assertTrue(
            (new CourierSuppressionList())->isSuppressed(new Address('spam@example.com')),
        );
    }

    public function testUnknownAddressIsNotSuppressed(): void
    {
        $this->assertFalse(
            (new CourierSuppressionList())->isSuppressed(new Address('nobody@example.com')),
        );
    }
}
