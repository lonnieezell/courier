<?php

declare(strict_types=1);

namespace Myth\Courier\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds the nullable mailable column to courier_campaigns and courier_drip_steps
 * so a campaign or drip step can render through a class-based Mailable instead
 * of a view.
 */
class AddMailableToCampaignsAndDripSteps extends Migration
{
    public function up(): void
    {
        if (! $this->db->fieldExists('mailable', 'courier_campaigns')) {
            $this->forge->addColumn('courier_campaigns', [
                'mailable' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                    'after'      => 'view',
                ],
            ]);
        }

        if (! $this->db->fieldExists('mailable', 'courier_drip_steps')) {
            $this->forge->addColumn('courier_drip_steps', [
                'mailable' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                    'after'      => 'view',
                ],
            ]);
        }
    }

    public function down(): void
    {
        if ($this->db->fieldExists('mailable', 'courier_campaigns')) {
            $this->forge->dropColumn('courier_campaigns', 'mailable');
        }

        if ($this->db->fieldExists('mailable', 'courier_drip_steps')) {
            $this->forge->dropColumn('courier_drip_steps', 'mailable');
        }
    }
}
