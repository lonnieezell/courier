<?php

declare(strict_types=1);

namespace Tests\Mailables;

use CodeIgniter\Test\CIUnitTestCase;
use Myth\Courier\DTO\CampaignDTO;
use Myth\Courier\DTO\ContactDTO;
use Myth\Courier\Mailables\CourierMailable;
use Myth\Postal\Email;
use Myth\Postal\Mailable;

/**
 * @internal
 */
final class CourierMailableTest extends CIUnitTestCase
{
    private function makeMailable(): CourierMailable
    {
        return new class () extends CourierMailable {
            protected function build(): void
            {
                $this->to($this->contact->email)
                    ->subject('Hi ' . $this->contact->email)
                    ->html('<p>Hello</p>');
            }
        };
    }

    public function testIsAPostalMailable(): void
    {
        $this->assertInstanceOf(Mailable::class, $this->makeMailable());
    }

    public function testExposesContactAndCampaignProperties(): void
    {
        $mailable = $this->makeMailable();

        $contact        = new ContactDTO();
        $contact->email = 'reader@example.com';
        $campaign       = new CampaignDTO();

        $mailable->contact  = $contact;
        $mailable->campaign = $campaign;

        $this->assertSame($contact, $mailable->contact);
        $this->assertSame($campaign, $mailable->campaign);
    }

    public function testCampaignDefaultsToNull(): void
    {
        $this->assertNull($this->makeMailable()->campaign);
    }

    public function testRenderComposesEmailUsingContact(): void
    {
        $mailable          = $this->makeMailable();
        $contact           = new ContactDTO();
        $contact->email    = 'reader@example.com';
        $mailable->contact = $contact;

        $email = $mailable->render();

        $this->assertInstanceOf(Email::class, $email);
        $this->assertSame('reader@example.com', $email->to[0]->email);
        $this->assertSame('Hi reader@example.com', $email->subject);
    }
}
