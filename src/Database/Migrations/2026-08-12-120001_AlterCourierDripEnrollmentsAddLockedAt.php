<?php

declare(strict_types=1);

namespace Myth\Courier\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds locked_at to courier_drip_enrollments so processDue() can claim rows
 * atomically (status='processing') and reclaim stale claims left by a crashed run.
 */
class AlterCourierDripEnrollmentsAddLockedAt extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('courier_drip_enrollments', [
            'locked_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'default' => null,
                'after'   => 'retry_count',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('courier_drip_enrollments', 'locked_at');
    }
}
