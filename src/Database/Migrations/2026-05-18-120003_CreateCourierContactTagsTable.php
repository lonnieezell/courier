<?php

declare(strict_types=1);

namespace Myth\Courier\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Creates the courier_contact_tags pivot table linking contacts to tags.
 */
class CreateCourierContactTagsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'contact_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => false],
            'tag_id'     => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => false],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['contact_id', 'tag_id']);
        $this->forge->addForeignKey('contact_id', 'courier_contacts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('tag_id', 'courier_tags', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('courier_contact_tags');
    }

    public function down(): void
    {
        $this->forge->dropTable('courier_contact_tags', true);
    }
}
