<?php

declare(strict_types=1);

namespace Myth\Courier\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds a composite index on (status, next_send_at) to speed up the processDue() queue query.
 */
class AddIndexToCourierDripEnrollments extends Migration
{
    public function up(): void
    {
        $this->forge->addKey(['status', 'next_send_at'], false, false, 'idx_due');
        $this->forge->processIndexes('courier_drip_enrollments');
    }

    public function down(): void
    {
        $this->forge->dropKey('courier_drip_enrollments', 'idx_due', false);
    }
}
