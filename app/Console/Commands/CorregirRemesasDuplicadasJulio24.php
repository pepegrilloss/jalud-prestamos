<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CorregirRemesasDuplicadasJulio24 extends Command
{
    protected $signature = 'remesas:corregir-duplicadas-2026-07-24
        {--aplicar : Confirma la anulacion y el ajuste de saldos}
        {--usuario-id=13 : Usuario responsable de la correccion}';

    protected $description = 'Conserva la remesa 149 y anula de forma auditable las remesas duplicadas 146 y 148.';

    private const MONTO = 796.70;
    private const SEDE_CHICLAYO = 1;
    private const SEDE_GERENCIA = 3;
    private const TRANSFERENCIA_CONSERVADA = 149;
    private const TRANSFERENCIAS_ANULADAS = [146, 148];

    public function handle(): int
    {
        $usuarioId = (int) $this->option('usuario-id');

        if (! DB::table('users')->where('id', $usuarioId)->exists()) {
            $this->error("No existe el usuario {$usuarioId}.");
            return self::FAILURE;
        }

        try {
            $resumen = DB::transaction(function () use ($usuarioId): array {
                $ids = array_merge([self::TRANSFERENCIA_CONSERVADA], self::TRANSFERENCIAS_ANULADAS);
                $transferencias = DB::table('transferencia_sedes')
                    ->whereIn('TransferenciaID', $ids)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('TransferenciaID');

                if ($transferencias->count() !== count($ids)) {
                    throw new RuntimeException('No se encontraron las transferencias 146, 148 y 149 completas.');
                }

                $this->validarTransferencia($transferencias->get(self::TRANSFERENCIA_CONSERVADA), false);

                $pendientes = collect(self::TRANSFERENCIAS_ANULADAS)
                    ->map(fn (int $id) => $transferencias->get($id))
                    ->filter(function (object $transferencia): bool {
                        $this->validarTransferencia($transferencia, true);
                        return $transferencia->Estado === 'ACEPTADO';
                    })
                    ->values();

                $montoRevertir = round($pendientes->count() * self::MONTO, 2);

                if (! $this->option('aplicar') || $montoRevertir === 0.0) {
                    return [
                        'aplicado' => false,
                        'pendientes' => $pendientes->pluck('TransferenciaID')->all(),
                        'monto' => $montoRevertir,
                    ];
                }

                $fondos = DB::table('fondo_sedes')
                    ->whereIn('SedeID', [self::SEDE_CHICLAYO, self::SEDE_GERENCIA])
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('SedeID');

                $fondoChiclayo = $fondos->get(self::SEDE_CHICLAYO);
                $fondoGerencia = $fondos->get(self::SEDE_GERENCIA);

                if (! $fondoChiclayo || ! $fondoGerencia) {
                    throw new RuntimeException('No se encontraron los fondos de Chiclayo y Gerencia.');
                }

                if ((float) $fondoGerencia->Saldo < $montoRevertir) {
                    throw new RuntimeException('Gerencia no tiene saldo suficiente para revertir las remesas duplicadas.');
                }

                $saldoChiclayo = (float) $fondoChiclayo->Saldo;
                $saldoGerencia = (float) $fondoGerencia->Saldo;
                $ahora = now();

                foreach ($pendientes as $transferencia) {
                    $reversionExistente = DB::table('movimientos_fondo')
                        ->where('TransferenciaID', $transferencia->TransferenciaID)
                        ->where('Tipo', 'REVERSO_TRANSFERENCIA')
                        ->exists();

                    if ($reversionExistente) {
                        throw new RuntimeException("La transferencia {$transferencia->TransferenciaID} ya tiene movimientos de reversion.");
                    }

                    $nuevoSaldoChiclayo = round($saldoChiclayo + self::MONTO, 2);
                    $nuevoSaldoGerencia = round($saldoGerencia - self::MONTO, 2);

                    DB::table('movimientos_fondo')->insert([
                        [
                            'SedeID' => self::SEDE_CHICLAYO,
                            'Tipo' => 'REVERSO_TRANSFERENCIA',
                            'Monto' => self::MONTO,
                            'SaldoAnterior' => $saldoChiclayo,
                            'SaldoNuevo' => $nuevoSaldoChiclayo,
                            'TransferenciaID' => $transferencia->TransferenciaID,
                            'UsuarioID' => $usuarioId,
                            'Observacion' => "Correccion auditada: anulacion de remesa duplicada #{$transferencia->TransferenciaID}.",
                            'FechaMovimiento' => $ahora,
                            'created_at' => $ahora,
                            'updated_at' => $ahora,
                        ],
                        [
                            'SedeID' => self::SEDE_GERENCIA,
                            'Tipo' => 'REVERSO_TRANSFERENCIA',
                            'Monto' => -self::MONTO,
                            'SaldoAnterior' => $saldoGerencia,
                            'SaldoNuevo' => $nuevoSaldoGerencia,
                            'TransferenciaID' => $transferencia->TransferenciaID,
                            'UsuarioID' => $usuarioId,
                            'Observacion' => "Correccion auditada: anulacion de remesa duplicada #{$transferencia->TransferenciaID}.",
                            'FechaMovimiento' => $ahora,
                            'created_at' => $ahora,
                            'updated_at' => $ahora,
                        ],
                    ]);

                    DB::table('transferencia_sedes')
                        ->where('TransferenciaID', $transferencia->TransferenciaID)
                        ->update([
                            'Estado' => 'ANULADO',
                            'Observacion' => trim(($transferencia->Observacion ?? '') . " [ANULADA: duplicada; se conserva la remesa #" . self::TRANSFERENCIA_CONSERVADA . ']'),
                            'updated_at' => $ahora,
                        ]);

                    $saldoChiclayo = $nuevoSaldoChiclayo;
                    $saldoGerencia = $nuevoSaldoGerencia;
                }

                DB::table('fondo_sedes')->where('SedeID', self::SEDE_CHICLAYO)->update([
                    'Saldo' => $saldoChiclayo,
                    'updated_at' => $ahora,
                ]);
                DB::table('fondo_sedes')->where('SedeID', self::SEDE_GERENCIA)->update([
                    'Saldo' => $saldoGerencia,
                    'updated_at' => $ahora,
                ]);

                return [
                    'aplicado' => true,
                    'pendientes' => $pendientes->pluck('TransferenciaID')->all(),
                    'monto' => $montoRevertir,
                    'saldo_chiclayo' => $saldoChiclayo,
                    'saldo_gerencia' => $saldoGerencia,
                ];
            });
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        if ($resumen['monto'] === 0.0) {
            $this->info('Las remesas 146 y 148 ya estaban anuladas. No se modifico ningun saldo.');
            return self::SUCCESS;
        }

        $this->table(
            ['Accion', 'Valor'],
            [
                ['Conservar', '#' . self::TRANSFERENCIA_CONSERVADA],
                ['Anular', implode(', ', array_map(fn ($id) => "#{$id}", $resumen['pendientes']))],
                ['Revertir a Chiclayo', 'S/ ' . number_format($resumen['monto'], 2)],
                ['Descontar de Gerencia', 'S/ ' . number_format($resumen['monto'], 2)],
            ]
        );

        if (! $resumen['aplicado']) {
            $this->warn('Diagnostico solamente. Ejecute nuevamente con --aplicar para confirmar.');
            return self::SUCCESS;
        }

        $this->info('Correccion aplicada correctamente y con movimientos de auditoria.');
        $this->line('Nuevo saldo Chiclayo: S/ ' . number_format($resumen['saldo_chiclayo'], 2));
        $this->line('Nuevo saldo Gerencia: S/ ' . number_format($resumen['saldo_gerencia'], 2));

        return self::SUCCESS;
    }

    private function validarTransferencia(object $transferencia, bool $permiteAnulada): void
    {
        $esSolicitudGerencia = (bool) $transferencia->EsSolicitudGerencia;
        $origenReal = $esSolicitudGerencia ? (int) $transferencia->SedeDestinoID : (int) $transferencia->SedeOrigenID;
        $destinoReal = $esSolicitudGerencia ? (int) $transferencia->SedeOrigenID : (int) $transferencia->SedeDestinoID;
        $estadosPermitidos = $permiteAnulada ? ['ACEPTADO', 'ANULADO'] : ['ACEPTADO'];

        if (
            round((float) $transferencia->Monto, 2) !== self::MONTO
            || $origenReal !== self::SEDE_CHICLAYO
            || $destinoReal !== self::SEDE_GERENCIA
            || $transferencia->CuentaOrigen !== 'CAJA_ABIERTA'
            || $transferencia->CuentaDestino !== 'CAJA_ABIERTA'
            || ! in_array($transferencia->Estado, $estadosPermitidos, true)
        ) {
            throw new RuntimeException("La transferencia {$transferencia->TransferenciaID} no coincide con los datos esperados. No se aplico ningun cambio.");
        }
    }
}
