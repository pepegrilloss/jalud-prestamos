<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Agregar saldo de Caja Chica a fondo_sedes
        Schema::table('fondo_sedes', function (Blueprint $table) {
            $table->decimal('SaldoCajaChica', 14, 2)->default(0)->after('Saldo');
        });

        // Agregar tipo de cuenta destino/origen a transferencias
        Schema::table('transferencia_sedes', function (Blueprint $table) {
            $table->string('CuentaOrigen', 30)->default('CAJA_ABIERTA')->after('SedeDestinoID');
            $table->string('CuentaDestino', 30)->default('CAJA_ABIERTA')->after('CuentaOrigen');
        });
    }

    public function down(): void
    {
        Schema::table('fondo_sedes', function (Blueprint $table) {
            $table->dropColumn('SaldoCajaChica');
        });

        Schema::table('transferencia_sedes', function (Blueprint $table) {
            $table->dropColumn(['CuentaOrigen', 'CuentaDestino']);
        });
    }
};
