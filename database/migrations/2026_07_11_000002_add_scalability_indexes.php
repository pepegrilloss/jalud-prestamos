<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->safeIndex('Credito', ['SedeID', 'Activo', 'FechaVencimiento', 'CreditoID'], 'idx_cre_sede_act_venc_id');
        $this->safeIndex('mora', ['CreditoID', 'FechaMora'], 'idx_mora_credito_fecha');
        $this->safeIndex('mora', ['SedeID', 'FechaMora'], 'idx_mora_sede_fecha');
        $this->safeIndex('pago', ['CreditoID', 'FechaPago', 'PagoID'], 'idx_pago_credito_fecha_id');
        $this->safeIndex('ProposicionCredito', ['ClienteID', 'ZonaID', 'Activo', 'FueRefinanciada', 'SaldoPendiente'], 'idx_prop_cliente_zona_saldo');
        $this->safeIndex('calendario_no_morosos', ['SedeID', 'Activo', 'Tipo', 'Fecha'], 'idx_cal_sede_act_tipo_fecha');
    }

    public function down(): void
    {
        foreach ([
            ['Credito', 'idx_cre_sede_act_venc_id'],
            ['mora', 'idx_mora_credito_fecha'],
            ['mora', 'idx_mora_sede_fecha'],
            ['pago', 'idx_pago_credito_fecha_id'],
            ['ProposicionCredito', 'idx_prop_cliente_zona_saldo'],
            ['calendario_no_morosos', 'idx_cal_sede_act_tipo_fecha'],
        ] as [$table, $index]) {
            $this->dropIndexIfExists($table, $index);
        }
    }

    private function safeIndex(string $table, array $columns, string $name): void
    {
        if (!Schema::hasTable($table) || $this->indexExists($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $name) {
            $blueprint->index($columns, $name);
        });
    }

    private function dropIndexIfExists(string $table, string $name): void
    {
        if (!Schema::hasTable($table) || !$this->indexExists($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($name) {
            $blueprint->dropIndex($name);
        });
    }

    private function indexExists(string $table, string $name): bool
    {
        $database = DB::getDatabaseName();

        return (int) DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $name)
            ->count() > 0;
    }
};
