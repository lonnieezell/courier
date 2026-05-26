<?php

declare(strict_types=1);

namespace Myth\Courier\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds retry_count to courier_drip_enrollments for failed-send backoff tracking.
 */
class AlterCourierDripEnrollmentsAddRetryCount extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('courier_drip_enrollments', [
            'retry_count' => [
                'type'       => 'TINYINT',
                'constraint' => 3,
                'unsigned'   => true,
                'default'    => 0,
                'null'       => false,
                'after'      => 'next_send_at',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('courier_drip_enrollments', 'retry_count');
    }
}
