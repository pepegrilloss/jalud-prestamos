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
        Schema::table('Compra', function (Blueprint $table) {
            $table->string('TipoCompra', 20)->default('CONTADO')->after('TipoIGV');
            $table->string('EstadoPago', 20)->default('PAGADO')->after('TipoCompra');
            $table->timestamp('FechaPago')->nullable()->after('EstadoPago');
            $table->unsignedBigInteger('UsuarioPagoID')->nullable()->after('FechaPago');
        });
    }

    public function down(): void
    {
        Schema::table('Compra', function (Blueprint $table) {
            $table->dropColumn(['TipoCompra', 'EstadoPago', 'FechaPago', 'UsuarioPagoID']);
        });
    }
};
