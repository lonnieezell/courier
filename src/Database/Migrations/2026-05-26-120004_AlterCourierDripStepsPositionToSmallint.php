<?php

declare(strict_types=1);

namespace YourVendor\YourPackage\Database\Migrations;

use CodeIgniter\Database\Migration;

class AlterCourierDripStepsPositionToSmallint extends Migration
{
    public function up(): void
    {
        $this->forge->modifyColumn('courier_drip_steps', [
            'position' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'null' => false],
        ]);
    }

    public function down(): void
    {
        $this->forge->modifyColumn('courier_drip_steps', [
            'position' => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'null' => false],
        ]);
    }
}
