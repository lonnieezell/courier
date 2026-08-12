<?php

declare(strict_types=1);

namespace Myth\Courier\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds a DB-level backstop against duplicate Blast sends: a unique
 * constraint on (campaign_id, contact_id) that only applies to rows where
 * drip_step_id IS NULL, so drip sequences (which reuse the same
 * campaign_id/contact_id across steps) remain unaffected.
 *
 * SQLite, PostgreSQL, and SQL Server support this directly as a filtered
 * unique index. Oracle has no filtered index syntax, so a function-based
 * unique index is used instead: the indexed expressions evaluate to NULL
 * for drip rows, and Oracle omits index entries that are entirely NULL,
 * which exempts them from the uniqueness check. MySQL supports neither
 * filtered nor function-based unique indexes, so a generated column that is
 * NULL for drip rows stands in for contact_id, relying on MySQL treating
 * each NULL as distinct within a unique index.
 */
class AddBlastDedupeIndexToCourierSends extends Migration
{
    private const DEDUPE_COLUMN = 'blast_dedupe_contact_id';

    public function up(): void
    {
        $table     = $this->db->DBPrefix . 'courier_sends';
        $indexName = $this->db->DBPrefix . 'courier_sends_blast_dedupe';

        match ($this->db->DBDriver) {
            'SQLite3', 'Postgre', 'SQLSRV' => $this->db->query(
                "CREATE UNIQUE INDEX {$indexName} ON {$table} (campaign_id, contact_id) WHERE drip_step_id IS NULL"
            ),
            'OCI8' => $this->db->query(
                "CREATE UNIQUE INDEX {$indexName} ON {$table} (" .
                'CASE WHEN drip_step_id IS NULL THEN campaign_id END, ' .
                'CASE WHEN drip_step_id IS NULL THEN contact_id END)'
            ),
            default => $this->addMysqlBlastDedupe($table, $indexName),
        };
    }

    public function down(): void
    {
        $table     = $this->db->DBPrefix . 'courier_sends';
        $indexName = $this->db->DBPrefix . 'courier_sends_blast_dedupe';

        match ($this->db->DBDriver) {
            'SQLite3', 'Postgre' => $this->db->query("DROP INDEX IF EXISTS {$indexName}"),
            'SQLSRV'             => $this->db->query("DROP INDEX {$indexName} ON {$table}"),
            'OCI8'               => $this->db->query("DROP INDEX {$indexName}"),
            default              => $this->dropMysqlBlastDedupe($table, $indexName),
        };
    }

    private function addMysqlBlastDedupe(string $table, string $indexName): void
    {
        $column = self::DEDUPE_COLUMN;

        $this->db->query(
            "ALTER TABLE {$table} ADD COLUMN {$column} INT UNSIGNED " .
            "GENERATED ALWAYS AS (CASE WHEN drip_step_id IS NULL THEN contact_id END) VIRTUAL"
        );
        $this->db->query("CREATE UNIQUE INDEX {$indexName} ON {$table} (campaign_id, {$column})");
    }

    private function dropMysqlBlastDedupe(string $table, string $indexName): void
    {
        $this->db->query("ALTER TABLE {$table} DROP INDEX {$indexName}");
        $this->db->query("ALTER TABLE {$table} DROP COLUMN " . self::DEDUPE_COLUMN);
    }
}
