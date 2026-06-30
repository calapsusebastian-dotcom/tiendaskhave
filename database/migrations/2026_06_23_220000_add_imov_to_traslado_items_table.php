<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('traslado_items', function (Blueprint $table) {
            $table->string('imov', 100)->nullable()->after('cantidad');
        });
    }

    public function down(): void
    {
        Schema::table('traslado_items', function (Blueprint $table) {
            $table->dropColumn('imov');
        });
    }
};
