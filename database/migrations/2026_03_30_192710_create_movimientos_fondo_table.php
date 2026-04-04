<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_fondo', function (Blueprint $table) {
            $table->id('MovimientoID');
            $table->unsignedInteger('SedeID');
            $table->string('Tipo', 50); // INGRESO_CAPITAL, ENVIO_TRANSFERENCIA, RECEPCION_TRANSFERENCIA, RECHAZO_TRANSFERENCIA
            $table->decimal('Monto', 15, 2);
            $table->decimal('SaldoAnterior', 15, 2);
            $table->decimal('SaldoNuevo', 15, 2);
            $table->unsignedBigInteger('TransferenciaID')->nullable();
            $table->unsignedBigInteger('UsuarioID');
            $table->text('Observacion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_fondo');
    }
};
