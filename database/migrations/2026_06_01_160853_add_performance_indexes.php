<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function safeIndex(string $table, array|string $columns, string $name): void
    {
        $columns = (array) $columns;
        $cols = implode(', ', array_map(fn($c) => "`{$c}`", $columns));

        try { DB::statement("DROP INDEX `{$name}` ON `{$table}`"); } catch (\Exception) {}
        DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$name}` ({$cols})");
    }

    public function up(): void
    {
        // excedentes
        $this->safeIndex('excedentes', 'SedeID', 'idx_exc_sede');
        $this->safeIndex('excedentes', 'Fecha', 'idx_exc_fecha');
        $this->safeIndex('excedentes', 'ZonaID', 'idx_exc_zona');
        $this->safeIndex('excedentes', 'ClienteOrigenID', 'idx_exc_cliente_origen');
        $this->safeIndex('excedentes', 'PagoOrigenID', 'idx_exc_pago_origen');
        $this->safeIndex('excedentes', 'Activo', 'idx_exc_activo');

        // solicitudes_resolucion_excedente
        $this->safeIndex('solicitudes_resolucion_excedente', 'SedeID', 'idx_sre_sede');
        $this->safeIndex('solicitudes_resolucion_excedente', ['Estado', 'created_at'], 'idx_sre_estado_created');
        $this->safeIndex('solicitudes_resolucion_excedente', 'ExcedenteID', 'idx_sre_excedente');
        $this->safeIndex('solicitudes_resolucion_excedente', 'ClienteDestinoID', 'idx_sre_cliente_destino');
        $this->safeIndex('solicitudes_resolucion_excedente', 'CreditoDestinoID', 'idx_sre_credito_destino');
        $this->safeIndex('solicitudes_resolucion_excedente', 'UserSolicitanteID', 'idx_sre_user_solicitante');
        $this->safeIndex('solicitudes_resolucion_excedente', 'UserAprobadorID', 'idx_sre_user_aprobador');
        $this->safeIndex('solicitudes_resolucion_excedente', 'PagoOrigenID', 'idx_sre_pago_origen');
        $this->safeIndex('solicitudes_resolucion_excedente', 'CreditoOrigenID', 'idx_sre_credito_origen');
        $this->safeIndex('solicitudes_resolucion_excedente', 'ClienteOrigenID', 'idx_sre_cliente_origen');

        // transferencia_sedes
        $this->safeIndex('transferencia_sedes', 'SedeOrigenID', 'idx_ts_origen');
        $this->safeIndex('transferencia_sedes', 'SedeDestinoID', 'idx_ts_destino');
        $this->safeIndex('transferencia_sedes', ['Estado', 'FechaRespuesta'], 'idx_ts_estado_resp');
        $this->safeIndex('transferencia_sedes', ['Estado', 'FechaTransferencia'], 'idx_ts_estado_trans');
        $this->safeIndex('transferencia_sedes', 'UsuarioOrigenID', 'idx_ts_usuario_origen');
        $this->safeIndex('transferencia_sedes', 'UsuarioRespondeID', 'idx_ts_usuario_responde');

        // movimientos_fondo
        $this->safeIndex('movimientos_fondo', ['SedeID', 'FechaMovimiento'], 'idx_mf_sede_fecha');
        $this->safeIndex('movimientos_fondo', ['SedeID', 'Tipo'], 'idx_mf_sede_tipo');
        $this->safeIndex('movimientos_fondo', 'TransferenciaID', 'idx_mf_transferencia');
        $this->safeIndex('movimientos_fondo', 'UsuarioID', 'idx_mf_usuario');

        // pago
        $this->safeIndex('pago', ['Activo', 'FechaPago'], 'idx_pgo_activo_fecha');
        $this->safeIndex('pago', ['Activo', 'EsPagoAMayor', 'FechaPago'], 'idx_pgo_activo_mayor_fecha');

        // Cliente
        $this->safeIndex('Cliente', 'Activo', 'idx_cli_activo');
        $this->safeIndex('Cliente', 'NombresApellidos', 'idx_cli_nombres');

        // Compra
        $this->safeIndex('Compra', ['Activo', 'FechaEmision'], 'idx_com_activo_fecha');

        // Gasto
        $this->safeIndex('Gasto', ['Activo', 'FechaEmision'], 'idx_gas_activo_fecha');

        // Credito
        $this->safeIndex('Credito', 'FechaSaldamiento', 'idx_cre_saldamiento');
        $this->safeIndex('Credito', ['EstatusCreditoFinal', 'FechaSaldamiento'], 'idx_cre_estatus_sald');

        // ProposicionCredito
        $this->safeIndex('ProposicionCredito', 'ClienteID', 'idx_prop_cliente');

        // Zona
        $this->safeIndex('Zona', 'Nombre', 'idx_zna_nombre');

        // TipoCredito
        $this->safeIndex('TipoCredito', 'Descripcion', 'idx_tcr_descripcion');

        // apertura_cierre_dia
        $this->safeIndex('apertura_cierre_dia', 'Fecha', 'idx_acd_fecha');
    }

    public function down(): void
    {
        $indexes = [
            ['excedentes', 'idx_exc_sede'], ['excedentes', 'idx_exc_fecha'],
            ['excedentes', 'idx_exc_zona'], ['excedentes', 'idx_exc_cliente_origen'],
            ['excedentes', 'idx_exc_pago_origen'], ['excedentes', 'idx_exc_activo'],
            ['solicitudes_resolucion_excedente', 'idx_sre_sede'],
            ['solicitudes_resolucion_excedente', 'idx_sre_estado_created'],
            ['solicitudes_resolucion_excedente', 'idx_sre_excedente'],
            ['solicitudes_resolucion_excedente', 'idx_sre_cliente_destino'],
            ['solicitudes_resolucion_excedente', 'idx_sre_credito_destino'],
            ['solicitudes_resolucion_excedente', 'idx_sre_user_solicitante'],
            ['solicitudes_resolucion_excedente', 'idx_sre_user_aprobador'],
            ['solicitudes_resolucion_excedente', 'idx_sre_pago_origen'],
            ['solicitudes_resolucion_excedente', 'idx_sre_credito_origen'],
            ['solicitudes_resolucion_excedente', 'idx_sre_cliente_origen'],
            ['transferencia_sedes', 'idx_ts_origen'], ['transferencia_sedes', 'idx_ts_destino'],
            ['transferencia_sedes', 'idx_ts_estado_resp'], ['transferencia_sedes', 'idx_ts_estado_trans'],
            ['transferencia_sedes', 'idx_ts_usuario_origen'], ['transferencia_sedes', 'idx_ts_usuario_responde'],
            ['movimientos_fondo', 'idx_mf_sede_fecha'], ['movimientos_fondo', 'idx_mf_sede_tipo'],
            ['movimientos_fondo', 'idx_mf_transferencia'], ['movimientos_fondo', 'idx_mf_usuario'],
            ['pago', 'idx_pgo_activo_fecha'], ['pago', 'idx_pgo_activo_mayor_fecha'],
            ['Cliente', 'idx_cli_activo'], ['Cliente', 'idx_cli_nombres'],
            ['Compra', 'idx_com_activo_fecha'], ['Gasto', 'idx_gas_activo_fecha'],
            ['Credito', 'idx_cre_saldamiento'], ['Credito', 'idx_cre_estatus_sald'],
            ['ProposicionCredito', 'idx_prop_cliente'],
            ['Zona', 'idx_zna_nombre'], ['TipoCredito', 'idx_tcr_descripcion'],
            ['apertura_cierre_dia', 'idx_acd_fecha'],
        ];

        foreach ($indexes as [$table, $name]) {
            try { DB::statement("DROP INDEX `{$name}` ON `{$table}`"); } catch (\Exception) {}
        }
    }
};
