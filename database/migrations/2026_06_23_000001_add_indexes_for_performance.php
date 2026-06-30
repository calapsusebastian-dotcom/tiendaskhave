<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->index('estado');
            $table->index('tienda_id');
            $table->index(['tienda_id', 'estado']);
            $table->index('created_at');
        });

        Schema::table('comandas', function (Blueprint $table) {
            $table->index('estado');
            $table->index('tienda_id');
            $table->index(['tienda_id', 'estado']);
            $table->index('created_at');
        });

        Schema::table('comanda_items', function (Blueprint $table) {
            $table->index('comanda_id');
        });

        Schema::table('pedido_items', function (Blueprint $table) {
            $table->index('pedido_id');
        });

        Schema::table('productos_menu', function (Blueprint $table) {
            $table->index(['tienda_id', 'activo']);
        });

        Schema::table('mesas', function (Blueprint $table) {
            $table->index(['tienda_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropIndex(['estado']);
            $table->dropIndex(['tienda_id']);
            $table->dropIndex(['tienda_id', 'estado']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('comandas', function (Blueprint $table) {
            $table->dropIndex(['estado']);
            $table->dropIndex(['tienda_id']);
            $table->dropIndex(['tienda_id', 'estado']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('comanda_items', function (Blueprint $table) {
            $table->dropIndex(['comanda_id']);
        });

        Schema::table('pedido_items', function (Blueprint $table) {
            $table->dropIndex(['pedido_id']);
        });

        Schema::table('productos_menu', function (Blueprint $table) {
            $table->dropIndex(['tienda_id', 'activo']);
        });

        Schema::table('mesas', function (Blueprint $table) {
            $table->dropIndex(['tienda_id', 'estado']);
        });
    }
};
