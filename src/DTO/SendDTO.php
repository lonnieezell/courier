<?php

declare(strict_types=1);

namespace Myth\Courier\DTO;

use Myth\Courier\Enums\SendStatus;

/**
 * Typed representation of a courier_sends row.
 */
class SendDTO extends BaseDTO
{
    public int $id;
    public int $contact_id;
    public int $campaign_id;
    public ?int $drip_step_id = null;
    public SendStatus $status;
    public ?string $message_id = null;
    public string $open_token;
    public string $click_token;
    public ?string $sent_at    = null;
    public ?string $opened_at  = null;
    public ?string $clicked_at = null;
    public string $created_at;
    public string $updated_at;
}
