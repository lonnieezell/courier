<?php

declare(strict_types=1);

namespace Myth\Courier\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds per-send unsubscribe_token and expiry to courier_sends.
 * Tokens are unique per send so a leaked token cannot unsubscribe indefinitely.
 *
 * Uses raw SQL for index and column drops: CI4's forge DROP COLUMN on SQLite uses
 * a temp-table strategy that fails when courier_events has a FK on courier_sends.
 */
class AlterCourierSendsAddUnsubscribeToken extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('courier_sends', [
            'unsubscribe_token' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => true,
                'default'    => null,
                'after'      => 'open_token',
            ],
            'unsubscribe_token_expires_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'default' => null,
                'after'   => 'unsubscribe_token',
            ],
        ]);

        $prefix    = $this->db->DBPrefix;
        $table     = $prefix . 'courier_sends';
        $indexName = $prefix . 'courier_sends_unsubscribe_token';

        $this->db->query("CREATE UNIQUE INDEX IF NOT EXISTS {$indexName} ON {$table} (unsubscribe_token)");
    }

    public function down(): void
    {
        $prefix    = $this->db->DBPrefix;
        $table     = $prefix . 'courier_sends';
        $indexName = $prefix . 'courier_sends_unsubscribe_token';

        match ($this->db->DBDriver) {
            'SQLite3' => $this->db->query("DROP INDEX IF EXISTS {$indexName}"),
            'Postgre' => $this->db->query("DROP INDEX IF EXISTS {$indexName}"),
            'SQLSRV'  => $this->db->query("DROP INDEX {$indexName} ON {$table}"),
            default   => $this->db->query("ALTER TABLE {$table} DROP INDEX {$indexName}"),
        };

        $this->db->query("ALTER TABLE {$table} DROP COLUMN unsubscribe_token");
        $this->db->query("ALTER TABLE {$table} DROP COLUMN unsubscribe_token_expires_at");
    }
}
