<?php

declare(strict_types=1);

namespace Myth\Courier\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds nullable link_id to courier_events so click events reference their courier_links row.
 * MySQL/MariaDB: FK constraint enforced at DB level.
 * SQLite (tests): FK not enforced at DB level; relationship is app-enforced.
 */
class AlterCourierEventsAddLinkId extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('courier_events', [
            'link_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
                'after'      => 'send_id',
            ],
        ]);

        if ($this->db->DBDriver !== 'SQLite3') {
            $this->db->query('ALTER TABLE courier_events ADD CONSTRAINT fk_courier_events_link_id FOREIGN KEY (link_id) REFERENCES courier_links(id) ON DELETE SET NULL ON UPDATE CASCADE');
        }
    }

    public function down(): void
    {
        if ($this->db->DBDriver !== 'SQLite3') {
            $this->db->query('ALTER TABLE courier_events DROP FOREIGN KEY fk_courier_events_link_id');
        }

        $this->forge->dropColumn('courier_events', 'link_id');
    }
}
