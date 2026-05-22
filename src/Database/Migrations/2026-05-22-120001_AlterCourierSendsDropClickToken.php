<?php

declare(strict_types=1);

namespace Myth\Courier\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Drops click_token (and its unique index) from courier_sends.
 * Per-link tokens now live in courier_links instead.
 *
 * Uses raw SQL: CI4's forge DROP COLUMN on SQLite uses a temp-table rename strategy
 * that fails when courier_events has a FK referencing courier_sends.
 * Each supported driver requires different index-drop syntax.
 */
class AlterCourierSendsDropClickToken extends Migration
{
    public function up(): void
    {
        $prefix    = $this->db->DBPrefix;
        $table     = $prefix . 'courier_sends';
        $indexName = $prefix . 'courier_sends_click_token';

        match ($this->db->DBDriver) {
            'SQLite3' => $this->db->query("DROP INDEX IF EXISTS {$indexName}"),
            'Postgre' => $this->db->query("DROP INDEX IF EXISTS {$indexName}"),
            'SQLSRV'  => $this->db->query("DROP INDEX {$indexName} ON {$table}"),
            default   => $this->db->query("ALTER TABLE {$table} DROP INDEX {$indexName}"),
        };

        $this->db->query("ALTER TABLE {$table} DROP COLUMN click_token");
    }

    public function down(): void
    {
        $this->forge->addColumn('courier_sends', [
            'click_token' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true, 'after' => 'open_token'],
        ]);

        $prefix    = $this->db->DBPrefix;
        $table     = $prefix . 'courier_sends';
        $indexName = $prefix . 'courier_sends_click_token';

        $this->db->query("CREATE UNIQUE INDEX {$indexName} ON {$table} (click_token)");
    }
}
