<?php

namespace App\Console\Commands;

use App\Models\FondoSede;
use App\Models\Log;
use App\Models\MovimientoFondo;
use App\Models\Sede;
use App\Services\GerenciaBalanceDiarioService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AjustarSaldoCierreGerencia extends Command
{
    protected $signature = 'gerencia:caja-ajustar-saldo
                            {--fecha= : Fecha efectiva del cierre (YYYY-MM-DD)}
                            {--saldo= : Saldo de efectivo confirmado para esa fecha}
                            {--motivo= : Motivo y sustento del ajuste}
                            {--usuario-id= : Usuario responsable, obligatorio para aplicar}
                            {--aplicar : Registra el ajuste real en Caja Gerencia}
                            {--confirmar= : Confirmación literal APLICAR-AJUSTE-GERENCIA-YYYY-MM-DD}';

    protected $description = 'Previsualiza o registra un ajuste auditado del saldo de cierre de Caja Gerencia';

    private const TIPO_AJUSTE = 'AJUSTE_CONCILIACION_CAJA';

    public function handle(GerenciaBalanceDiarioService $balanceService): int
    {
        try {
            $fecha = $this->fechaObjetivo();
            $saldoObjetivo = $this->saldoObjetivo();
            $motivo = trim((string) $this->option('motivo'));
            $aplicar = (bool) $this->option('aplicar');
            $usuarioId = $this->option('usuario-id');

            if ($motivo === '' || mb_strlen($motivo) > 350) {
                throw new RuntimeException('Indique --motivo con un texto de 1 a 350 caracteres.');
            }

            if ($aplicar) {
                $this->validarAplicacion($fecha, $usuarioId);
                $resultado = $this->aplicarAjuste($balanceService, $fecha, $saldoObjetivo, $motivo, (int) $usuarioId);
            } else {
                $resultado = $this->previsualizar($balanceService, $fecha, $saldoObjetivo);
            }
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Sede', 'Fecha', 'Saldo actual reportado', 'Saldo objetivo', 'Ajuste', 'Saldo Caja Gerencia después'],
            [[
                $resultado['sede'],
                $fecha,
                $this->importe($resultado['saldo_actual']),
                $this->importe($saldoObjetivo),
                $this->importe($resultado['diferencia']),
                $this->importe($resultado['saldo_nuevo']),
            ]]
        );

        if (! $aplicar) {
            $confirmacion = 'APLICAR-AJUSTE-GERENCIA-'.$fecha;
            $this->warn('Previsualización: no se modificó ningún dato.');
            $this->line('Para aplicar tras validar el saldo y el motivo, agregue --aplicar --usuario-id=ID --confirmar='.$confirmacion);

            return self::SUCCESS;
        }

        if ($resultado['movimiento_id'] === null) {
            $this->info('El saldo ya coincide con el objetivo; no se registró un movimiento.');

            return self::SUCCESS;
        }

        $this->info('Ajuste registrado solo en Caja Gerencia. MovimientoID: '.$resultado['movimiento_id'].'.');

        return self::SUCCESS;
    }

    private function fechaObjetivo(): string
    {
        $valor = (string) $this->option('fecha');
        $fecha = Carbon::createFromFormat('!Y-m-d', $valor);

        if (! $fecha || $fecha->format('Y-m-d') !== $valor || $fecha->isFuture()) {
            throw new RuntimeException('Indique una fecha válida no futura con formato YYYY-MM-DD.');
        }

        return $fecha->toDateString();
    }

    private function saldoObjetivo(): float
    {
        $valor = $this->option('saldo');
        if (! is_numeric($valor) || (float) $valor < 0) {
            throw new RuntimeException('Indique --saldo como un monto numérico mayor o igual a cero.');
        }

        return round((float) $valor, 2);
    }

    private function validarAplicacion(string $fecha, mixed $usuarioId): void
    {
        if (! is_numeric($usuarioId) || (int) $usuarioId <= 0 || ! DB::table('users')->where('id', (int) $usuarioId)->exists()) {
            throw new RuntimeException('Para aplicar indique --usuario-id con un usuario existente.');
        }

        $esperado = 'APLICAR-AJUSTE-GERENCIA-'.$fecha;
        if ($this->option('confirmar') !== $esperado) {
            throw new RuntimeException('La confirmación no coincide. Debe indicar --confirmar='.$esperado);
        }
    }

    private function previsualizar(GerenciaBalanceDiarioService $balanceService, string $fecha, float $saldoObjetivo): array
    {
        $sede = $this->sedeGerencia();
        $fondo = FondoSede::withoutGlobalScope('sede')->where('SedeID', $sede->SedeID)->firstOrFail();
        $saldoActual = round((float) $balanceService->generar($fecha)['caja']['saldo_cierre'], 2);
        $diferencia = round($saldoObjetivo - $saldoActual, 2);

        return [
            'sede' => $sede->Nombre,
            'saldo_actual' => $saldoActual,
            'diferencia' => $diferencia,
            'saldo_nuevo' => round((float) $fondo->Saldo + $diferencia, 2),
            'movimiento_id' => null,
        ];
    }

    private function aplicarAjuste(
        GerenciaBalanceDiarioService $balanceService,
        string $fecha,
        float $saldoObjetivo,
        string $motivo,
        int $usuarioId,
    ): array {
        return DB::transaction(function () use ($balanceService, $fecha, $saldoObjetivo, $motivo, $usuarioId): array {
            $sede = $this->sedeGerencia();
            $fondo = FondoSede::withoutGlobalScope('sede')
                ->where('SedeID', $sede->SedeID)
                ->lockForUpdate()
                ->firstOrFail();
            $marca = "[AJUSTE_CIERRE_GERENCIA:{$fecha}]";

            $yaExiste = MovimientoFondo::withoutGlobalScopes()
                ->where('SedeID', $sede->SedeID)
                ->where('Tipo', self::TIPO_AJUSTE)
                ->whereDate('FechaMovimiento', $fecha)
                ->get(['Observacion'])
                ->contains(fn (MovimientoFondo $movimiento): bool => str_starts_with((string) $movimiento->Observacion, $marca));

            if ($yaExiste) {
                throw new RuntimeException("Ya existe un ajuste de cierre de Gerencia para {$fecha}; no se aplicó otro.");
            }

            $saldoActual = round((float) $balanceService->generar($fecha)['caja']['saldo_cierre'], 2);
            $diferencia = round($saldoObjetivo - $saldoActual, 2);
            $saldoAnteriorCaja = round((float) $fondo->Saldo, 2);
            $saldoNuevoCaja = round($saldoAnteriorCaja + $diferencia, 2);

            if ($saldoNuevoCaja < 0) {
                throw new RuntimeException('El ajuste dejaría el saldo operativo de Caja Gerencia en negativo.');
            }

            if (abs($diferencia) < 0.01) {
                return [
                    'sede' => $sede->Nombre,
                    'saldo_actual' => $saldoActual,
                    'diferencia' => 0.0,
                    'saldo_nuevo' => $saldoAnteriorCaja,
                    'movimiento_id' => null,
                ];
            }

            $fondo->Saldo = $saldoNuevoCaja;
            FondoSede::withoutEvents(fn (): bool => $fondo->save());

            $observacion = $marca.' Ajuste de conciliación de efectivo. '
                .'Saldo de cierre confirmado: '.$this->importe($saldoObjetivo).'. '
                .'Saldo previo reportado: '.$this->importe($saldoActual).'. '
                .'Motivo: '.$motivo;
            $ahora = now();

            $movimiento = MovimientoFondo::withoutEvents(fn (): MovimientoFondo => MovimientoFondo::create([
                'SedeID' => $sede->SedeID,
                'Tipo' => self::TIPO_AJUSTE,
                'Monto' => $diferencia,
                'SaldoAnterior' => $saldoAnteriorCaja,
                'SaldoNuevo' => $saldoNuevoCaja,
                'UsuarioID' => $usuarioId,
                'Observacion' => $observacion,
                'FechaMovimiento' => Carbon::parse($fecha)->endOfDay(),
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]));

            Log::registrar(
                'ACTUALIZAR',
                'FondoSede',
                $fondo->getKey(),
                ['Saldo' => $saldoAnteriorCaja],
                ['Saldo' => $saldoNuevoCaja],
                (int) $sede->SedeID,
                $usuarioId,
            );
            Log::registrar(
                'CREAR',
                'MovimientoFondo',
                $movimiento->MovimientoID,
                null,
                $movimiento->toArray(),
                (int) $sede->SedeID,
                $usuarioId,
            );

            return [
                'sede' => $sede->Nombre,
                'saldo_actual' => $saldoActual,
                'diferencia' => $diferencia,
                'saldo_nuevo' => $saldoNuevoCaja,
                'movimiento_id' => $movimiento->MovimientoID,
            ];
        });
    }

    private function sedeGerencia(): Sede
    {
        return Sede::query()
            ->whereRaw('LOWER(Nombre) LIKE ?', ['%gerencia%'])
            ->orderBy('SedeID')
            ->firstOrFail();
    }

    private function importe(float $monto): string
    {
        return 'S/ '.number_format($monto, 2, '.', ',');
    }
}
