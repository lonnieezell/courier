<?php

declare(strict_types=1);

namespace Myth\Courier\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Creates the courier_drip_steps table for individual steps within a drip campaign.
 */
class CreateCourierDripStepsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'campaign_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => false],
            'position'    => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'null' => false],
            'view'        => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => false],
            'subject'     => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => false],
            'delay_hours' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 24],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('campaign_id', 'courier_campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('courier_drip_steps');
    }

    public function down(): void
    {
        $this->forge->dropTable('courier_drip_steps', true);
    }
}
