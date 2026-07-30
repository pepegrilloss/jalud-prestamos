<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Compra', function (Blueprint $table): void {
            $table->string('OrigenTesoreriaTipo', 30)->nullable()->after('UsuarioPagoID');
            $table->unsignedBigInteger('CuentaTesoreriaID')->nullable()->index()->after('OrigenTesoreriaTipo');
        });

        Schema::table('Gasto', function (Blueprint $table): void {
            $table->string('OrigenTesoreriaTipo', 30)->nullable()->after('MetodoGasto');
            $table->unsignedBigInteger('CuentaTesoreriaID')->nullable()->index()->after('OrigenTesoreriaTipo');
        });

        Schema::table('tesoreria_movimientos', function (Blueprint $table): void {
            $table->unsignedBigInteger('CompraID')->nullable()->index()->after('CuotaPrestamoBancarioID');
            $table->unsignedBigInteger('GastoID')->nullable()->index()->after('CompraID');
        });
    }

    public function down(): void
    {
        Schema::table('tesoreria_movimientos', function (Blueprint $table): void {
            $table->dropColumn(['CompraID', 'GastoID']);
        });

        Schema::table('Gasto', function (Blueprint $table): void {
            $table->dropColumn(['OrigenTesoreriaTipo', 'CuentaTesoreriaID']);
        });

        Schema::table('Compra', function (Blueprint $table): void {
            $table->dropColumn(['OrigenTesoreriaTipo', 'CuentaTesoreriaID']);
        });
    }
};
