<?php

declare(strict_types=1);

namespace Myth\Courier\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds a composite index on (campaign_id, contact_id) to speed up the SendCampaign batch-preparation query.
 */
class AddIndexToCourierSends extends Migration
{
    public function up(): void
    {
        $this->forge->addKey(['campaign_id', 'contact_id'], false, false, 'idx_campaign_contact');
        $this->forge->processIndexes('courier_sends');
    }

    public function down(): void
    {
        $this->forge->dropKey('courier_sends', 'idx_campaign_contact', false);
    }
}
