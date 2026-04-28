<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('excedentes', function (Blueprint $table) {
            $table->dateTime('FechaCierre')->nullable()->after('Fecha');
        });

        Schema::table('solicitudes_resolucion_excedente', function (Blueprint $table) {
            $table->dateTime('FechaCierre')->nullable()->after('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('excedentes', function (Blueprint $table) {
            $table->dropColumn('FechaCierre');
        });

        Schema::table('solicitudes_resolucion_excedente', function (Blueprint $table) {
            $table->dropColumn('FechaCierre');
        });
    }
};
