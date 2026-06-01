<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // === excedentes ===
        Schema::table('excedentes', function (Blueprint $table) {
            $table->index('SedeID', 'idx_exc_sede');
            $table->index('Fecha', 'idx_exc_fecha');
            $table->index('ZonaID', 'idx_exc_zona');
            $table->index('ClienteOrigenID', 'idx_exc_cliente_origen');
            $table->index('PagoOrigenID', 'idx_exc_pago_origen');
            $table->index('Activo', 'idx_exc_activo');
        });

        // === solicitudes_resolucion_excedente ===
        Schema::table('solicitudes_resolucion_excedente', function (Blueprint $table) {
            $table->index('SedeID', 'idx_sre_sede');
            $table->index(['Estado', 'created_at'], 'idx_sre_estado_created');
            $table->index('ExcedenteID', 'idx_sre_excedente');
            $table->index('ClienteDestinoID', 'idx_sre_cliente_destino');
            $table->index('CreditoDestinoID', 'idx_sre_credito_destino');
            $table->index('UserSolicitanteID', 'idx_sre_user_solicitante');
            $table->index('UserAprobadorID', 'idx_sre_user_aprobador');
            $table->index('PagoOrigenID', 'idx_sre_pago_origen');
            $table->index('CreditoOrigenID', 'idx_sre_credito_origen');
            $table->index('ClienteOrigenID', 'idx_sre_cliente_origen');
        });

        // === transferencia_sedes ===
        Schema::table('transferencia_sedes', function (Blueprint $table) {
            $table->index('SedeOrigenID', 'idx_ts_origen');
            $table->index('SedeDestinoID', 'idx_ts_destino');
            $table->index(['Estado', 'FechaRespuesta'], 'idx_ts_estado_resp');
            $table->index(['Estado', 'FechaTransferencia'], 'idx_ts_estado_trans');
            $table->index('UsuarioOrigenID', 'idx_ts_usuario_origen');
            $table->index('UsuarioRespondeID', 'idx_ts_usuario_responde');
        });

        // === movimientos_fondo ===
        Schema::table('movimientos_fondo', function (Blueprint $table) {
            $table->index(['SedeID', 'FechaMovimiento'], 'idx_mf_sede_fecha');
            $table->index(['SedeID', 'Tipo'], 'idx_mf_sede_tipo');
            $table->index('TransferenciaID', 'idx_mf_transferencia');
            $table->index('UsuarioID', 'idx_mf_usuario');
        });

        // === pago ===
        Schema::table('pago', function (Blueprint $table) {
            $table->index(['Activo', 'FechaPago'], 'idx_pgo_activo_fecha');
            $table->index(['Activo', 'EsPagoAMayor', 'FechaPago'], 'idx_pgo_activo_mayor_fecha');
        });

        // === cliente ===
        Schema::table('Cliente', function (Blueprint $table) {
            $table->index('Activo', 'idx_cli_activo');
            $table->index('NombresApellidos', 'idx_cli_nombres');
        });

        // === Compra ===
        Schema::table('Compra', function (Blueprint $table) {
            $table->index(['Activo', 'FechaEmision'], 'idx_com_activo_fecha');
        });

        // === Gasto ===
        Schema::table('Gasto', function (Blueprint $table) {
            $table->index(['Activo', 'FechaEmision'], 'idx_gas_activo_fecha');
        });

        // === Credito ===
        Schema::table('Credito', function (Blueprint $table) {
            $table->index('FechaSaldamiento', 'idx_cre_saldamiento');
            $table->index(['EstatusCreditoFinal', 'FechaSaldamiento'], 'idx_cre_estatus_sald');
        });

        // === ProposicionCredito ===
        Schema::table('ProposicionCredito', function (Blueprint $table) {
            $table->index('ClienteID', 'idx_prop_cliente');
        });

        // === Zona ===
        Schema::table('Zona', function (Blueprint $table) {
            $table->index('Nombre', 'idx_zna_nombre');
        });

        // === TipoCredito ===
        Schema::table('TipoCredito', function (Blueprint $table) {
            $table->index('Descripcion', 'idx_tcr_descripcion');
        });

        // === apertura_cierre_dia ===
        Schema::table('apertura_cierre_dia', function (Blueprint $table) {
            $table->index('Fecha', 'idx_acd_fecha');
        });
    }

    public function down(): void
    {
        Schema::table('excedentes', function (Blueprint $t) {
            $t->dropIndex('idx_exc_sede'); $t->dropIndex('idx_exc_fecha');
            $t->dropIndex('idx_exc_zona'); $t->dropIndex('idx_exc_cliente_origen');
            $t->dropIndex('idx_exc_pago_origen'); $t->dropIndex('idx_exc_activo');
        });
        Schema::table('solicitudes_resolucion_excedente', function (Blueprint $t) {
            $t->dropIndex('idx_sre_sede'); $t->dropIndex('idx_sre_estado_created');
            $t->dropIndex('idx_sre_excedente'); $t->dropIndex('idx_sre_cliente_destino');
            $t->dropIndex('idx_sre_credito_destino'); $t->dropIndex('idx_sre_user_solicitante');
            $t->dropIndex('idx_sre_user_aprobador'); $t->dropIndex('idx_sre_pago_origen');
            $t->dropIndex('idx_sre_credito_origen'); $t->dropIndex('idx_sre_cliente_origen');
        });
        Schema::table('transferencia_sedes', function (Blueprint $t) {
            $t->dropIndex('idx_ts_origen'); $t->dropIndex('idx_ts_destino');
            $t->dropIndex('idx_ts_estado_resp'); $t->dropIndex('idx_ts_estado_trans');
            $t->dropIndex('idx_ts_usuario_origen'); $t->dropIndex('idx_ts_usuario_responde');
        });
        Schema::table('movimientos_fondo', function (Blueprint $t) {
            $t->dropIndex('idx_mf_sede_fecha'); $t->dropIndex('idx_mf_sede_tipo');
            $t->dropIndex('idx_mf_transferencia'); $t->dropIndex('idx_mf_usuario');
        });
        Schema::table('pago', function (Blueprint $t) {
            $t->dropIndex('idx_pgo_activo_fecha'); $t->dropIndex('idx_pgo_activo_mayor_fecha');
        });
        Schema::table('Cliente', function (Blueprint $t) {
            $t->dropIndex('idx_cli_activo'); $t->dropIndex('idx_cli_nombres');
        });
        Schema::table('Compra', function (Blueprint $t) { $t->dropIndex('idx_com_activo_fecha'); });
        Schema::table('Gasto', function (Blueprint $t) { $t->dropIndex('idx_gas_activo_fecha'); });
        Schema::table('Credito', function (Blueprint $t) {
            $t->dropIndex('idx_cre_saldamiento'); $t->dropIndex('idx_cre_estatus_sald');
        });
        Schema::table('ProposicionCredito', function (Blueprint $t) { $t->dropIndex('idx_prop_cliente'); });
        Schema::table('Zona', function (Blueprint $t) { $t->dropIndex('idx_zna_nombre'); });
        Schema::table('TipoCredito', function (Blueprint $t) { $t->dropIndex('idx_tcr_descripcion'); });
        Schema::table('apertura_cierre_dia', function (Blueprint $t) { $t->dropIndex('idx_acd_fecha'); });
    }
};
