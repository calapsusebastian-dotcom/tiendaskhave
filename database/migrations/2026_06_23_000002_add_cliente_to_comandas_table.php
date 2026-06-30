<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comandas', function (Blueprint $table) {
            $table->string('cliente_nombre')->nullable()->after('estado');
            $table->string('cliente_cc')->nullable()->after('cliente_nombre');
            $table->string('cliente_telefono')->nullable()->after('cliente_cc');
            $table->string('cliente_correo')->nullable()->after('cliente_telefono');
        });
    }

    public function down(): void
    {
        Schema::table('comandas', function (Blueprint $table) {
            $table->dropColumn(['cliente_nombre', 'cliente_cc', 'cliente_telefono', 'cliente_correo']);
        });
    }
};
