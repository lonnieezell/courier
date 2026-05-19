<?php

declare(strict_types=1);

namespace Myth\Courier\DTO;

use Myth\Courier\Enums\ContactStatus;

/**
 * Typed representation of a courier_contacts row.
 */
class ContactDTO extends BaseDTO
{
    public int $id;
    public string $email;
    public ?string $first_name = null;
    public ?string $last_name  = null;
    public ContactStatus $status;
    public ?string $source = null;
    public string $unsubscribe_token;
    public ?string $subscribed_at   = null;
    public ?string $unsubscribed_at = null;
    public mixed $custom_fields     = null;
    public string $created_at;
    public string $updated_at;
}
