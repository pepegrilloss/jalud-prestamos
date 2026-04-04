<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transferencia_sedes', function (Blueprint $table) {
            $table->id('TransferenciaID');
            $table->unsignedInteger('SedeOrigenID');
            $table->unsignedInteger('SedeDestinoID');
            $table->unsignedBigInteger('UsuarioOrigenID');
            $table->unsignedBigInteger('UsuarioRespondeID')->nullable();
            $table->decimal('Monto', 15, 2);
            $table->enum('Estado', ['PENDIENTE', 'ACEPTADO', 'RECHAZADO'])->default('PENDIENTE');
            $table->text('Observacion')->nullable();
            $table->timestamp('FechaTransferencia')->useCurrent();
            $table->timestamp('FechaRespuesta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transferencia_sedes');
    }
};
