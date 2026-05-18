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
 * Creates the courier_sends table recording each email sent to a contact.
 */
class CreateCourierSendsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'contact_id'   => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => false],
            'campaign_id'  => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => false],
            'drip_step_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'status'       => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'],
            'message_id'   => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'open_token'   => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'click_token'  => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'sent_at'      => ['type' => 'DATETIME', 'null' => true],
            'opened_at'    => ['type' => 'DATETIME', 'null' => true],
            'clicked_at'   => ['type' => 'DATETIME', 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('open_token');
        $this->forge->addUniqueKey('click_token');
        $this->forge->addForeignKey('contact_id', 'courier_contacts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('campaign_id', 'courier_campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('drip_step_id', 'courier_drip_steps', 'id', 'SET NULL', 'SET NULL');
        $this->forge->createTable('courier_sends');
    }

    public function down(): void
    {
        $this->forge->dropTable('courier_sends', true);
    }
}
