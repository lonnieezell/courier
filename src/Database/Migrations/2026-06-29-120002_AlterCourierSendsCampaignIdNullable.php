<?php

declare(strict_types=1);

namespace Myth\Courier\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Makes courier_sends.campaign_id nullable so transactional (one-off) sends,
 * which belong to no campaign, can be recorded.
 *
 * Fresh installs already get the nullable column from 2026-05-18-120008.
 * This migration exists only to upgrade pre-existing MySQL/PostgreSQL installs.
 * SQLite3 (test environment) does not need the ALTER; it is skipped.
 */
class AlterCourierSendsCampaignIdNullable extends Migration
{
    public function up(): void
    {
        if ($this->db->DBDriver === 'SQLite3') {
            return;
        }

        $this->db->query('ALTER TABLE courier_sends DROP FOREIGN KEY courier_sends_campaign_id_foreign');
        $this->forge->modifyColumn('courier_sends', [
            'campaign_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
            ],
        ]);
        $this->db->query('ALTER TABLE courier_sends ADD CONSTRAINT courier_sends_campaign_id_foreign FOREIGN KEY (campaign_id) REFERENCES courier_campaigns(id) ON DELETE CASCADE ON UPDATE CASCADE');
    }

    public function down(): void
    {
        if ($this->db->DBDriver === 'SQLite3') {
            return;
        }

        $this->db->query('ALTER TABLE courier_sends DROP FOREIGN KEY courier_sends_campaign_id_foreign');
        $this->forge->modifyColumn('courier_sends', [
            'campaign_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => false,
            ],
        ]);
        $this->db->query('ALTER TABLE courier_sends ADD CONSTRAINT courier_sends_campaign_id_foreign FOREIGN KEY (campaign_id) REFERENCES courier_campaigns(id) ON DELETE CASCADE ON UPDATE CASCADE');
    }
}
