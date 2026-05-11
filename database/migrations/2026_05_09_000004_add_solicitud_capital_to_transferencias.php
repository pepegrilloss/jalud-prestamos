<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transferencia_sedes', function (Blueprint $table) {
            $table->boolean('EsSolicitudCapital')->default(false)->after('CuentaDestino');
            $table->decimal('MontoAprobado', 14, 2)->nullable()->after('Monto');
        });
    }

    public function down(): void
    {
        Schema::table('transferencia_sedes', function (Blueprint $table) {
            $table->dropColumn('EsSolicitudCapital');
            $table->dropColumn('MontoAprobado');
        });
    }
};
