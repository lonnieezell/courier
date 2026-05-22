<?php

declare(strict_types=1);

namespace Myth\Courier\DTO;

/**
 * Typed representation of a courier_links row.
 */
class LinkDTO extends BaseDTO
{
    public int $id;
    public int $send_id;
    public string $link_token;
    public string $url;
    public string $created_at;
    public string $updated_at;
}
