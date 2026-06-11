<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Gasto', function (Blueprint $table) {
            $table->dateTime('FechaCierre')->nullable()->after('FechaModificacion');
        });

        Schema::table('Compra', function (Blueprint $table) {
            $table->dateTime('FechaCierre')->nullable()->after('FechaModificacion');
        });

        Schema::table('transferencia_sedes', function (Blueprint $table) {
            $table->dateTime('FechaCierre')->nullable()->after('FechaRespuesta');
        });
    }

    public function down(): void
    {
        Schema::table('Gasto', function (Blueprint $table) {
            $table->dropColumn('FechaCierre');
        });

        Schema::table('Compra', function (Blueprint $table) {
            $table->dropColumn('FechaCierre');
        });

        Schema::table('transferencia_sedes', function (Blueprint $table) {
            $table->dropColumn('FechaCierre');
        });
    }
};
