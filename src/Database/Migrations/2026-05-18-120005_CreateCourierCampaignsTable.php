<?php

declare(strict_types=1);

/**
 * This file is part of YourVendor/YourPackage.
 *
 * (c) Your Name <you@example.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Myth\Courier\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Creates the courier_campaigns table for blast and drip email campaigns.
 */
class CreateCourierCampaignsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'name'         => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => false],
            'subject'      => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => false],
            'type'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'blast'],
            'layout'       => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
            'status'       => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'draft'],
            'segment_id'   => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'tag_filter'   => ['type' => 'TEXT', 'null' => true],
            'from_name'    => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'from_email'   => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => false],
            'scheduled_at' => ['type' => 'DATETIME', 'null' => true],
            'sent_at'      => ['type' => 'DATETIME', 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('segment_id', 'courier_segments', 'id', 'SET NULL', 'SET NULL');
        $this->forge->createTable('courier_campaigns');
    }

    public function down(): void
    {
        $this->forge->dropTable('courier_campaigns', true);
    }
}
