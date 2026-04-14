<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_resolucion_excedente', function (Blueprint $table) {
            if (!Schema::hasColumn('solicitudes_resolucion_excedente', 'ClienteOrigenID')) {
                $table->unsignedBigInteger('ClienteOrigenID')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_resolucion_excedente', function (Blueprint $table) {
            if (Schema::hasColumn('solicitudes_resolucion_excedente', 'ClienteOrigenID')) {
                $table->dropColumn('ClienteOrigenID');
            }
        });
    }
};
