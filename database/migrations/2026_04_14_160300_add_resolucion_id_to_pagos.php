<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pago', function (Blueprint $table) {
            if (!Schema::hasColumn('pago', 'SolicitudResolucionID')) {
                $table->unsignedBigInteger('SolicitudResolucionID')->nullable()->after('Activo')->comment('ID de la solicitud de extorno/devolución que generó este pago');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pago', function (Blueprint $table) {
            if (Schema::hasColumn('pago', 'SolicitudResolucionID')) {
                $table->dropColumn('SolicitudResolucionID');
            }
        });
    }
};
