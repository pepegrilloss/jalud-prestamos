<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Remover la restricción UNIQUE en Fecha para permitir múltiples registros por fecha
        // (pero solo uno abierto gracias a  abierto_flag)
        Schema::table('apertura_cierre_dia', function (Blueprint $table) {
            // Primero, cambiar la columna Fecha para que NO sea UNIQUE
            $table->dropUnique('apertura_cierre_dia_fecha_unique');
        });
        
        // Ahora agregar un índice NORMAL (no único) para consultas por fecha
        Schema::table('apertura_cierre_dia', function (Blueprint $table) {
            $table->index('Fecha');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('apertura_cierre_dia', function (Blueprint $table) {
            $table->dropIndex('apertura_cierre_dia_fecha_index');
            $table->unique('Fecha');
        });
    }
};
