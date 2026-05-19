<?php

declare(strict_types=1);

/**
 * This file is part of YourVendor/YourPackage.
 *
 * (c) Your Name <you@example.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Myth\Courier\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Creates the courier_segments table for rule-based contact grouping.
 */
class CreateCourierSegmentsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'rules'      => ['type' => 'TEXT', 'null' => false],
            'match_mode' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'all'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('courier_segments');
    }

    public function down(): void
    {
        $this->forge->dropTable('courier_segments', true);
    }
}
