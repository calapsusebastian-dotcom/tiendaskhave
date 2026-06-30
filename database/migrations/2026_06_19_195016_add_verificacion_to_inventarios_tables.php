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
        Schema::table('inventario_items', function (Blueprint $table) {
            $table->decimal('cantidad_sistema', 10, 2)->nullable()->after('cantidad');
        });

        Schema::table('inventarios', function (Blueprint $table) {
            $table->foreignId('verificado_by')->nullable()->constrained('users')->nullOnDelete()->after('diligenciado_by');
        });
    }

    public function down(): void
    {
        Schema::table('inventario_items', function (Blueprint $table) {
            $table->dropColumn('cantidad_sistema');
        });

        Schema::table('inventarios', function (Blueprint $table) {
            $table->dropForeign(['verificado_by']);
            $table->dropColumn('verificado_by');
        });
    }
};
