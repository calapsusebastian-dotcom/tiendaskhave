<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->timestamp('aprobado_at')->nullable()->after('observacion');
            $table->timestamp('enviado_at')->nullable()->after('aprobado_at');
            $table->timestamp('recibido_at')->nullable()->after('enviado_at');
            $table->timestamp('terminado_at')->nullable()->after('recibido_at');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn(['aprobado_at', 'enviado_at', 'recibido_at', 'terminado_at']);
        });
    }
};
