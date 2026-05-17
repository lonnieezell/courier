<?php

declare(strict_types=1);

namespace Myth\Courier\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Creates the courier_events table for tracking open, click, bounce, and unsubscribe events.
 */
class CreateCourierEventsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'send_id'    => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => false],
            'type'       => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => false],
            'metadata'   => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('send_id', 'courier_sends', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('courier_events');
    }

    public function down(): void
    {
        $this->forge->dropTable('courier_events', true);
    }
}
