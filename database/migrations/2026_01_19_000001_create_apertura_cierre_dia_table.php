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
        Schema::create('apertura_cierre_dia', function (Blueprint $table) {
            $table->id('AperturaCierreDiaID');
            $table->date('Fecha')->unique();
            $table->timestamp('FechaApertura')->nullable();
            $table->timestamp('FechaCierre')->nullable();
            $table->enum('EstadoDia', ['ABIERTO', 'CERRADO'])->default('CERRADO');
            $table->unsignedBigInteger('UsuarioAperturaID')->nullable();
            $table->unsignedBigInteger('UsuarioCierreID')->nullable();
            $table->text('Observaciones')->nullable();
            $table->timestamps();

            $table->foreign('UsuarioAperturaID')->references('id')->on('users')->onDelete('set null');
            $table->foreign('UsuarioCierreID')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('apertura_cierre_dia');
    }
};
