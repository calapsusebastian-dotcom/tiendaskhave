<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mesas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tienda_id')->constrained('tiendas')->cascadeOnDelete();
            $table->unsignedSmallInteger('numero');
            $table->string('nombre', 60)->nullable();
            $table->unsignedTinyInteger('capacidad')->default(4);
            $table->enum('estado', ['libre', 'ocupada', 'en_cuenta'])->default('libre');
            $table->timestamps();
            $table->unique(['tienda_id', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mesas');
    }
};
