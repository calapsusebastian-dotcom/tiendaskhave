<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('traslados', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 20)->unique();
            $table->foreignId('tienda_origen_id')->constrained('tiendas');
            $table->foreignId('tienda_destino_id')->constrained('tiendas');
            $table->foreignId('solicitante_id')->constrained('users');
            $table->enum('estado', ['pendiente', 'enviado', 'recibido', 'rechazado', 'cancelado'])->default('pendiente');
            $table->text('notas')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traslados');
    }
};
