<?php

declare(strict_types=1);

namespace Myth\Courier\Database\Migrations;

use CodeIgniter\Database\Migration;

class AlterCourierDripStepsPositionToSmallint extends Migration
{
    public function up(): void
    {
        // SQLite3 does not distinguish integer sizes; modifyColumn on SQLite triggers a
        // table-rename strategy (CI4's Table::run()) that corrupts dependent FK references
        // due to SQLite 3.26+ auto-updating child-table FK names during RENAME TABLE.
        // The widening is a no-op in SQLite, so we skip it safely.
        if ($this->db->DBDriver === 'SQLite3') {
            return;
        }

        $this->forge->modifyColumn('courier_drip_steps', [
            'position' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'null' => false],
        ]);
    }

    public function down(): void
    {
        if ($this->db->DBDriver === 'SQLite3') {
            return;
        }

        $this->forge->modifyColumn('courier_drip_steps', [
            'position' => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'null' => false],
        ]);
    }
}
