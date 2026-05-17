<?php

declare(strict_types=1);

namespace Myth\Courier\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Creates the courier_contacts table for storing email subscribers.
 */
class CreateCourierContactsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'email'             => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false],
            'first_name'        => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'last_name'         => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'status'            => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'subscribed'],
            'source'            => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'unsubscribe_token' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => false],
            'subscribed_at'     => ['type' => 'DATETIME', 'null' => true],
            'unsubscribed_at'   => ['type' => 'DATETIME', 'null' => true],
            'custom_fields'     => ['type' => 'TEXT', 'null' => true],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('email');
        $this->forge->addUniqueKey('unsubscribe_token');
        $this->forge->createTable('courier_contacts');
    }

    public function down(): void
    {
        $this->forge->dropTable('courier_contacts', true);
    }
}
