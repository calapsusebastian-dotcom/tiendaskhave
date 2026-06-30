<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('materias_primas', function (Blueprint $table) {
            $table->tinyInteger('iva')->default(19)->after('precio');
        });
    }

    public function down(): void
    {
        Schema::table('materias_primas', function (Blueprint $table) {
            $table->dropColumn('iva');
        });
    }
};
