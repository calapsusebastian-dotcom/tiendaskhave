<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comanda_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comanda_id')->constrained('comandas')->cascadeOnDelete();
            $table->foreignId('producto_menu_id')->constrained('productos_menu')->cascadeOnDelete();
            $table->unsignedSmallInteger('cantidad');
            $table->decimal('precio_unitario', 12, 2);
            $table->string('observacion', 200)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comanda_items');
    }
};
