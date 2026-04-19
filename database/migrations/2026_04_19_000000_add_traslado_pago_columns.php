<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Columnas en tabla pago para trazabilidad de traslados
        Schema::table('pago', function (Blueprint $table) {
            if (!Schema::hasColumn('pago', 'PagoOrigenID')) {
                $table->integer('PagoOrigenID')->nullable()->after('SolicitudResolucionID')
                    ->comment('ID del pago original del que se trasladó este pago');
            }
            if (!Schema::hasColumn('pago', 'EstadoTraslado')) {
                $table->string('EstadoTraslado', 20)->nullable()->after('PagoOrigenID')
                    ->comment('NULL=normal, TRASLADADO=pago movido a otro cliente');
            }
        });

        // Columnas en solicitudes_resolucion_excedente para guardar el pago a trasladar
        Schema::table('solicitudes_resolucion_excedente', function (Blueprint $table) {
            if (!Schema::hasColumn('solicitudes_resolucion_excedente', 'PagoOrigenID')) {
                $table->integer('PagoOrigenID')->nullable()->after('MontoAplicar')
                    ->comment('ID del pago del Cliente A que se va a trasladar');
            }
            if (!Schema::hasColumn('solicitudes_resolucion_excedente', 'CreditoOrigenID')) {
                $table->integer('CreditoOrigenID')->nullable()->after('PagoOrigenID')
                    ->comment('ID del crédito del Cliente A de donde viene el pago');
            }
        });

        // Hacer ExcedenteID nullable (ya no es obligatorio para traslados de pago)
        Schema::table('solicitudes_resolucion_excedente', function (Blueprint $table) {
            $table->unsignedBigInteger('ExcedenteID')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('pago', function (Blueprint $table) {
            if (Schema::hasColumn('pago', 'PagoOrigenID')) {
                $table->dropColumn('PagoOrigenID');
            }
            if (Schema::hasColumn('pago', 'EstadoTraslado')) {
                $table->dropColumn('EstadoTraslado');
            }
        });

        Schema::table('solicitudes_resolucion_excedente', function (Blueprint $table) {
            if (Schema::hasColumn('solicitudes_resolucion_excedente', 'PagoOrigenID')) {
                $table->dropColumn('PagoOrigenID');
            }
            if (Schema::hasColumn('solicitudes_resolucion_excedente', 'CreditoOrigenID')) {
                $table->dropColumn('CreditoOrigenID');
            }
        });
    }
};
