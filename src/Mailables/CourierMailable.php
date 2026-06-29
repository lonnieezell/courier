<?php

declare(strict_types=1);

namespace Myth\Courier\Mailables;

use Myth\Courier\DTO\CampaignDTO;
use Myth\Courier\DTO\ContactDTO;
use Myth\Postal\Mailable;

/**
 * Base class for Courier's class-based emails. Extends postal's Mailable with
 * the recipient contact and (optional) originating campaign, which MailerService
 * sets before rendering so subclasses can compose against them and Courier can
 * apply tracking and unsubscribe injection to the rendered HTML.
 */
abstract class CourierMailable extends Mailable
{
    public ContactDTO $contact;

    public ?CampaignDTO $campaign = null;
}
