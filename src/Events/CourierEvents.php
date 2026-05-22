<?php

declare(strict_types=1);

namespace Myth\Courier\Events;

/**
 * Event name constants for CI4's Events system.
 *
 * Register listeners in app/Config/Events.php:
 *   Events::on(CourierEvents::CONTACT_SUBSCRIBED, function(ContactDTO $contact) { ... });
 */
class CourierEvents
{
    public const CONTACT_SUBSCRIBED   = 'courier:contact.subscribed';
    public const CONTACT_UNSUBSCRIBED = 'courier:contact.unsubscribed';
    public const CONTACT_BOUNCED      = 'courier:contact.bounced';
    public const CONTACT_COMPLAINED   = 'courier:contact.complained';
    public const EMAIL_SENT           = 'courier:email.sent';
    public const EMAIL_FAILED         = 'courier:email.failed';
}
