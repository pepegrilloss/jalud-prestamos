<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tesoreria_cuentas', function (Blueprint $table) {
            $table->bigIncrements('CuentaTesoreriaID');
            $table->string('Banco', 100);
            $table->string('NumeroCuenta', 100)->unique();
            $table->string('TipoCuenta', 30)->default('BANCO');
            $table->decimal('SaldoActual', 14, 2)->default(0);
            $table->timestamp('FechaUltimoMovimiento')->nullable();
            $table->string('Estado', 20)->default('ACTIVA')->index();
            $table->timestamps();
        });

        Schema::create('tesoreria_movimientos', function (Blueprint $table) {
            $table->bigIncrements('MovimientoTesoreriaID');
            $table->string('Tipo', 30)->index();
            $table->string('OrigenTipo', 30);
            $table->unsignedBigInteger('CuentaOrigenID')->nullable()->index();
            $table->string('CuentaOrigenNombre', 255);
            $table->string('DestinoTipo', 30);
            $table->unsignedBigInteger('CuentaDestinoID')->nullable()->index();
            $table->string('CuentaDestinoNombre', 255);
            $table->decimal('Monto', 14, 2);
            $table->date('FechaContable')->index();
            $table->timestamp('FechaMovimiento');
            $table->string('Concepto', 255);
            $table->text('Observaciones')->nullable();
            $table->unsignedBigInteger('UsuarioID')->index();
            $table->unsignedBigInteger('MovimientoOriginalID')->nullable()->unique();
            $table->decimal('SaldoAnteriorOrigen', 14, 2)->nullable();
            $table->decimal('SaldoNuevoOrigen', 14, 2)->nullable();
            $table->decimal('SaldoAnteriorDestino', 14, 2)->nullable();
            $table->decimal('SaldoNuevoDestino', 14, 2)->nullable();
            $table->timestamps();
            $table->index(['FechaContable', 'Tipo'], 'idx_tesoreria_mov_fecha_tipo');
            $table->index(['UsuarioID', 'FechaMovimiento'], 'idx_tesoreria_mov_usuario_fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tesoreria_movimientos');
        Schema::dropIfExists('tesoreria_cuentas');
    }
};
