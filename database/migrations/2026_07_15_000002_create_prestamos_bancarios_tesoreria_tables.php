<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tesoreria_prestamos_bancarios', function (Blueprint $table) {
            $table->bigIncrements('PrestamoBancarioID');
            $table->unsignedBigInteger('CuentaTesoreriaID')->index();
            $table->string('Cliente', 255);
            $table->string('CuentaPrestamo', 100);
            $table->string('Operacion', 100)->nullable();
            $table->decimal('MontoPrestamo', 14, 2);
            $table->date('FechaDesembolso');
            $table->date('FechaVencimiento')->index();
            $table->unsignedSmallInteger('NumeroCuotas');
            $table->unsignedTinyInteger('DiaPago');
            $table->decimal('PagoMensual', 14, 2);
            $table->decimal('TEA', 9, 6);
            $table->decimal('TED', 9, 6);
            $table->string('Estado', 20)->default('VIGENTE')->index();
            $table->text('Observaciones')->nullable();
            $table->timestamps();
            $table->index(['CuentaTesoreriaID', 'Estado'], 'idx_prestamo_banco_estado');
        });

        Schema::create('tesoreria_prestamo_cuotas', function (Blueprint $table) {
            $table->bigIncrements('CuotaPrestamoBancarioID');
            $table->unsignedBigInteger('PrestamoBancarioID')->index();
            $table->unsignedSmallInteger('Numero');
            $table->date('FechaVencimiento')->index();
            $table->decimal('Capital', 14, 2);
            $table->decimal('Interes', 14, 2);
            $table->decimal('Comision', 14, 2)->default(0);
            $table->decimal('Seguros', 14, 2)->default(0);
            $table->decimal('MontoCuota', 14, 2);
            $table->decimal('SaldoDeuda', 14, 2);
            $table->string('Estado', 20)->default('PENDIENTE')->index();
            $table->date('FechaPago')->nullable();
            $table->timestamps();
            $table->unique(['PrestamoBancarioID', 'Numero'], 'uq_prestamo_cuota_numero');
            $table->index(['PrestamoBancarioID', 'Estado'], 'idx_prestamo_cuota_estado');
        });

        Schema::create('tesoreria_prestamo_pagos', function (Blueprint $table) {
            $table->bigIncrements('PagoPrestamoBancarioID');
            $table->unsignedBigInteger('PrestamoBancarioID')->index();
            $table->unsignedBigInteger('CuotaPrestamoBancarioID')->index();
            $table->unsignedBigInteger('MovimientoTesoreriaID')->unique();
            $table->decimal('Monto', 14, 2);
            $table->date('FechaContable')->index();
            $table->timestamp('FechaRegistro');
            $table->string('Concepto', 255);
            $table->text('Observaciones')->nullable();
            $table->unsignedBigInteger('UsuarioID')->index();
            $table->unsignedBigInteger('PagoOriginalID')->nullable()->unique();
            $table->timestamps();
        });

        Schema::table('tesoreria_movimientos', function (Blueprint $table) {
            $table->unsignedBigInteger('PrestamoBancarioID')->nullable()->index()->after('MovimientoOriginalID');
            $table->unsignedBigInteger('CuotaPrestamoBancarioID')->nullable()->index()->after('PrestamoBancarioID');
        });
    }

    public function down(): void
    {
        Schema::table('tesoreria_movimientos', function (Blueprint $table) {
            $table->dropColumn(['PrestamoBancarioID', 'CuotaPrestamoBancarioID']);
        });

        Schema::dropIfExists('tesoreria_prestamo_pagos');
        Schema::dropIfExists('tesoreria_prestamo_cuotas');
        Schema::dropIfExists('tesoreria_prestamos_bancarios');
    }
};
