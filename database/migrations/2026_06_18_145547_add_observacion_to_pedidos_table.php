<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Ampliar el enum con los nuevos estados antes de actualizar datos
        DB::statement("ALTER TABLE pedidos MODIFY COLUMN estado ENUM('por_aprobar','aprobado','pendiente','enviado','recibido','terminado','rechazado') NOT NULL DEFAULT 'por_aprobar'");

        DB::table('pedidos')->where('estado', 'pendiente')->update(['estado' => 'aprobado']);

        // Quitar 'pendiente' del enum ahora que no hay registros con ese valor
        DB::statement("ALTER TABLE pedidos MODIFY COLUMN estado ENUM('por_aprobar','aprobado','enviado','recibido','terminado','rechazado') NOT NULL DEFAULT 'por_aprobar'");

        Schema::table('pedidos', function (Blueprint $table) {
            $table->boolean('recibido_ok')->nullable()->after('imov');
            $table->text('observacion')->nullable()->after('recibido_ok');
        });
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE pedidos MODIFY COLUMN estado ENUM('por_aprobar','aprobado','pendiente','enviado','recibido','terminado','rechazado') NOT NULL DEFAULT 'por_aprobar'");
        DB::table('pedidos')->where('estado', 'aprobado')->update(['estado' => 'pendiente']);
        DB::statement("ALTER TABLE pedidos MODIFY COLUMN estado ENUM('por_aprobar','pendiente','enviado','recibido','rechazado') NOT NULL DEFAULT 'por_aprobar'");

        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn(['recibido_ok', 'observacion']);
        });
    }
};
