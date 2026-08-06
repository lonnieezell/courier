<?php

declare(strict_types=1);

namespace Myth\Courier\Postal;

use Myth\Courier\Enums\ContactStatus;
use Myth\Courier\Models\ContactModel;
use Myth\Postal\Address;
use Myth\Postal\SuppressionListInterface;

/**
 * Suppresses recipients whose contact record is no longer mailable —
 * unsubscribed, bounced, or marked as a complaint. Bound into postal via
 * Config\Mailer::$suppressionList so the mailer filters them before dispatch.
 */
class CourierSuppressionList implements SuppressionListInterface
{
    public function __construct(private readonly ContactModel $contacts = new ContactModel())
    {
    }

    public function isSuppressed(Address $recipient): bool
    {
        $contact = $this->contacts->where('email', $recipient->email)->first();

        if ($contact === null) {
            return false;
        }

        return in_array($contact->status, [
            ContactStatus::Unsubscribed,
            ContactStatus::Bounced,
            ContactStatus::Complained,
        ], true);
    }
}
