<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE inventarios MODIFY COLUMN estado ENUM('borrador', 'completado', 'verificado') NOT NULL DEFAULT 'borrador'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE inventarios MODIFY COLUMN estado ENUM('borrador', 'completado') NOT NULL DEFAULT 'borrador'");
    }
};
