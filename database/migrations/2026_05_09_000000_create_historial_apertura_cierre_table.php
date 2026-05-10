<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historial_apertura_cierre', function (Blueprint $table) {
            $table->id('HistorialAperturaCierreID');
            $table->integer('SedeID');
            $table->date('Fecha');
            $table->string('Accion', 30);
            $table->integer('UsuarioID')->nullable();
            $table->text('Observaciones')->nullable();
            $table->integer('CantidadRegistrosAfectados')->default(0);
            $table->datetime('FechaHora');
            $table->foreign('SedeID')->references('SedeID')->on('Sede');
            $table->index(['SedeID', 'Fecha'], 'IDX_Historial_Sede_Fecha');
            $table->index('FechaHora', 'IDX_Historial_FechaHora');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_apertura_cierre');
    }
};
