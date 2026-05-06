<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fondo_sedes', function (Blueprint $table) {
            $table->id('FondoSedeID');
            $table->unsignedBigInteger('SedeID')->unique();
            $table->decimal('Saldo', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('transferencia_sedes', function (Blueprint $table) {
            $table->id('TransferenciaID');
            $table->unsignedBigInteger('SedeOrigenID');
            $table->unsignedBigInteger('SedeDestinoID');
            $table->unsignedBigInteger('UsuarioOrigenID');
            $table->unsignedBigInteger('UsuarioRespondeID')->nullable();
            $table->decimal('Monto', 14, 2);
            $table->string('Estado', 20)->default('PENDIENTE');
            $table->string('Observacion', 500)->nullable();
            $table->timestamp('FechaTransferencia')->nullable();
            $table->timestamp('FechaRespuesta')->nullable();
            $table->timestamps();
        });

        Schema::create('movimientos_fondo', function (Blueprint $table) {
            $table->id('MovimientoID');
            $table->unsignedBigInteger('SedeID');
            $table->string('Tipo', 50);
            $table->decimal('Monto', 14, 2);
            $table->decimal('SaldoAnterior', 14, 2)->default(0);
            $table->decimal('SaldoNuevo', 14, 2)->default(0);
            $table->unsignedBigInteger('TransferenciaID')->nullable();
            $table->unsignedBigInteger('UsuarioID');
            $table->string('Observacion', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_fondo');
        Schema::dropIfExists('transferencia_sedes');
        Schema::dropIfExists('fondo_sedes');
    }
};
