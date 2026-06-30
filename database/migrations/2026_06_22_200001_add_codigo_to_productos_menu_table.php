<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos_menu', function (Blueprint $table) {
            $table->string('codigo', 50)->nullable()->after('nombre');
        });
    }

    public function down(): void
    {
        Schema::table('productos_menu', function (Blueprint $table) {
            $table->dropColumn('codigo');
        });
    }
};
