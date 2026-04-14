<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_resolucion_excedente', function (Blueprint $table) {
            $table->id('SolicitudID');
            $table->unsignedBigInteger('ExcedenteID');
            $table->enum('TipoResolucion', [
                'TRASLADO_DE_PAGO',
                'ASIGNACION_POR_RECLAMO',
                'DEVOLUCION_EFECTIVO',
                'APLICACION_NUEVO_CREDITO'
            ]);
            $table->unsignedBigInteger('ClienteDestinoID')->nullable();
            $table->unsignedBigInteger('CreditoDestinoID')->nullable();
            
            $table->text('DatosValeCaja')->nullable();
            $table->text('Observaciones')->nullable();
            $table->string('Estado', 20)->default('PENDIENTE'); // PENDIENTE, APROBADA, RECHAZADA
            
            $table->unsignedBigInteger('UserSolicitanteID')->nullable();
            $table->unsignedBigInteger('UserAprobadorID')->nullable();
            $table->integer('SedeID')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_resolucion_excedente');
    }
};
