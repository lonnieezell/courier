<?php

declare(strict_types=1);

namespace Myth\Courier\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds source_file column to courier_campaigns for file-based drip campaign definitions.
 */
class AddSourceFileToCourierCampaigns extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('courier_campaigns', [
            'source_file' => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true, 'after' => 'view'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('courier_campaigns', 'source_file');
    }
}
