<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE solicitudes_resolucion_excedente MODIFY TipoResolucion ENUM(
            'TRASLADO_DE_PAGO',
            'ASIGNACION_POR_RECLAMO',
            'DEVOLUCION_EFECTIVO',
            'APLICACION_NUEVO_CREDITO',
            'DEVOLUCION_PAGO_MAYOR',
            'APLICACION_PAGO_MAYOR'
        ) NOT NULL");
    }

    public function down(): void
    {
        DB::table('solicitudes_resolucion_excedente')
            ->where('TipoResolucion', 'APLICACION_PAGO_MAYOR')
            ->update(['TipoResolucion' => 'TRASLADO_DE_PAGO']);

        DB::statement("ALTER TABLE solicitudes_resolucion_excedente MODIFY TipoResolucion ENUM(
            'TRASLADO_DE_PAGO',
            'ASIGNACION_POR_RECLAMO',
            'DEVOLUCION_EFECTIVO',
            'APLICACION_NUEVO_CREDITO',
            'DEVOLUCION_PAGO_MAYOR'
        ) NOT NULL");
    }
};
