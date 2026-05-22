<?php

declare(strict_types=1);

namespace Myth\Courier\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Makes send_id nullable in courier_events so webhook-sourced events
 * (bounces, complaints) can be recorded without a matching send row.
 *
 * Fresh installs already get the nullable column from 2026-05-18-120009.
 * This migration exists only to upgrade pre-existing MySQL/PostgreSQL installs.
 * SQLite3 (test environment) does not need the ALTER; it is skipped.
 */
class AlterCourierEventsNullableSendId extends Migration
{
    public function up(): void
    {
        if ($this->db->DBDriver === 'SQLite3') {
            return;
        }

        $this->db->query('ALTER TABLE courier_events DROP FOREIGN KEY courier_events_send_id_foreign');
        $this->forge->modifyColumn('courier_events', [
            'send_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
            ],
        ]);
        $this->db->query('ALTER TABLE courier_events ADD CONSTRAINT courier_events_send_id_foreign FOREIGN KEY (send_id) REFERENCES courier_sends(id) ON DELETE CASCADE ON UPDATE CASCADE');
    }

    public function down(): void
    {
        if ($this->db->DBDriver === 'SQLite3') {
            return;
        }

        $this->db->query('ALTER TABLE courier_events DROP FOREIGN KEY courier_events_send_id_foreign');
        $this->forge->modifyColumn('courier_events', [
            'send_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => false,
            ],
        ]);
        $this->db->query('ALTER TABLE courier_events ADD CONSTRAINT courier_events_send_id_foreign FOREIGN KEY (send_id) REFERENCES courier_sends(id) ON DELETE CASCADE ON UPDATE CASCADE');
    }
}
