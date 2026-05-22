<?php

declare(strict_types=1);

namespace Myth\Courier\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Creates the courier_links table mapping per-link click tokens to destination URLs.
 */
class CreateCourierLinksTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'send_id'    => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => false],
            'link_token' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => false],
            'url'        => ['type' => 'VARCHAR', 'constraint' => 2048, 'null' => false],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('link_token');
        $this->forge->addForeignKey('send_id', 'courier_sends', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('courier_links');
    }

    public function down(): void
    {
        $this->forge->dropTable('courier_links', true);
    }
}
