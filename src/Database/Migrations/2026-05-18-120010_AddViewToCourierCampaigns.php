<?php

declare(strict_types=1);

namespace Myth\Courier\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds the view column to courier_campaigns for existing installs.
 */
class AddViewToCourierCampaigns extends Migration
{
    public function up(): void
    {
        // Guard for installs that already have the column (e.g. fresh test runs
        // that execute migration 120005 which already includes the view field).
        if ($this->db->fieldExists('view', 'courier_campaigns')) {
            return;
        }

        $this->forge->addColumn('courier_campaigns', [
            'view' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
                'null'       => true,
                'after'      => 'type',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('courier_campaigns', 'view');
    }
}
