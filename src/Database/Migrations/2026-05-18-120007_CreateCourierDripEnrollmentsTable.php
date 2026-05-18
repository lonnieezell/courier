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
 * Creates the courier_drip_enrollments table tracking a contact's progress through a drip campaign.
 */
class CreateCourierDripEnrollmentsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'contact_id'   => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => false],
            'campaign_id'  => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => false],
            'current_step' => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'default' => 1],
            'next_send_at' => ['type' => 'DATETIME', 'null' => true],
            'status'       => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['contact_id', 'campaign_id']);
        $this->forge->addForeignKey('contact_id', 'courier_contacts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('campaign_id', 'courier_campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('courier_drip_enrollments');
    }

    public function down(): void
    {
        $this->forge->dropTable('courier_drip_enrollments', true);
    }
}
