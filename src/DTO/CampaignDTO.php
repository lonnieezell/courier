<?php

declare(strict_types=1);

namespace Myth\Courier\DTO;

use Myth\Courier\Enums\CampaignStatus;
use Myth\Courier\Enums\CampaignType;

/**
 * Typed representation of a courier_campaigns row.
 */
class CampaignDTO extends BaseDTO
{
    public int $id;
    public string $name;
    public string $subject;
    public CampaignType $type;
    public ?string $view        = null;
    public ?string $mailable    = null;
    public ?string $source_file = null;
    public ?string $layout      = null;
    public CampaignStatus $status;
    public ?int $segment_id  = null;
    public mixed $tag_filter = null;
    public string $from_name;
    public string $from_email;
    public ?string $scheduled_at = null;
    public ?string $sent_at      = null;
    public string $created_at;
    public string $updated_at;

    /**
     * Returns true when this campaign is driven by a YAML source file
     * rather than rows in the drip-steps table.
     */
    public function isFileBased(): bool
    {
        return $this->source_file !== null;
    }
}
