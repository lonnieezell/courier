<?php

declare(strict_types=1);

namespace Tests\Support\Mailables;

use Myth\Courier\Mailables\CourierMailable;

/**
 * A concrete Mailable used to exercise MailerService's class-based send path.
 */
final class TestMailable extends CourierMailable
{
    protected function build(): void
    {
        $this->from('sender@example.com', 'Sender')
            ->to($this->contact->email)
            ->subject('Mailable Subject')
            ->html(
                '<p>Hi ' . $this->contact->email . '</p>'
                . '<a href="https://example.com/x">link</a>'
                . ' {courier_unsubscribe_url} {courier_tracking_pixel}',
            );
    }
}
