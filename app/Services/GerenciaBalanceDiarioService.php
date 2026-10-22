<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\Credito;
use App\Models\CuentaTesoreria;
use App\Models\Excedente;
use App\Models\FondoSede;
use App\Models\Gasto;
use App\Models\MovimientoFondo;
use App\Models\MovimientoTesoreria;
use App\Models\Pago;
use App\Models\Sede;
use App\Models\TransferenciaSede;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class GerenciaBalanceDiarioService
{
    private const AJUSTE_CAJA_ABIERTA_SEDE = 150000.00;

    public function generar(string $fecha): array
    {
        $fecha = Carbon::parse($fecha)->toDateString();
        $sedeGerencia = Sede::query()
            ->whereRaw('LOWER(Nombre) LIKE ?', ['%gerencia%'])
            ->orderBy('SedeID')
            ->firstOrFail();

        $movimientos = MovimientoTesoreria::query()
            ->with(['compra.proveedor'])
            ->orderBy('FechaContable')
            ->orderBy('MovimientoTesoreriaID')
            ->get();
        $gastosVinculados = $movimientos->pluck('GastoID')->filter()->map(fn ($id) => (int) $id)->unique();
        $gastos = Gasto::withoutGlobalScopes()
            ->whereIn('GastoID', $gastosVinculados->isEmpty() ? [0] : $gastosVinculados)
            ->with('motivo')
            ->get()
            ->keyBy('GastoID');
        $movimientos->each(fn (MovimientoTesoreria $movimiento) => $movimiento->setRelation(
            'gasto',
            $gastos->get($movimiento->GastoID)
        ));
        $movimientosBalance = $this->movimientosParaBalance($movimientos);
        $extornosPorGasto = $this->extornosPorGasto($movimientos);

        $movimientosFondo = MovimientoFondo::withoutGlobalScopes()
            ->where('SedeID', $sedeGerencia->SedeID)
            ->orderBy('FechaMovimiento')
            ->orderBy('MovimientoID')
            ->get();

        $transferenciaIds = $movimientosFondo->pluck('TransferenciaID')->filter()->unique();
        $transferencias = $transferenciaIds->isEmpty()
            ? collect()
            : TransferenciaSede::withoutGlobalScopes()
                ->whereIn('TransferenciaID', $transferenciaIds)
                ->with(['sedeOrigen', 'sedeDestino'])
                ->get()
                ->keyBy('TransferenciaID');

        $movimientosFondo->each(function (MovimientoFondo $movimiento) use ($transferencias): void {
            $movimiento->setRelation('transferencia', $transferencias->get($movimiento->TransferenciaID));
        });

        $fondo = FondoSede::withoutGlobalScopes()
            ->where('SedeID', $sedeGerencia->SedeID)
            ->first();

        $gastosVinculados = $gastosVinculados->all();
        $comprasVinculadas = $movimientos->pluck('CompraID')->filter()->map(fn ($id) => (int) $id)->all();

        $gastosAntiguos = Gasto::withoutGlobalScopes()
            ->where('SedeID', $sedeGerencia->SedeID)
            ->where('Activo', true)
            ->whereIn('OrigenTesoreriaTipo', [MovimientoTesoreria::CAJA_GERENCIA, MovimientoTesoreria::CUENTA_BANCARIA])
            ->whereNotIn('GastoID', $gastosVinculados ?: [0])
            ->with('motivo')
            ->get();

        $comprasAntiguas = Compra::withoutGlobalScopes()
            ->where('SedeID', $sedeGerencia->SedeID)
            ->where('Activo', true)
            ->whereIn('OrigenTesoreriaTipo', [MovimientoTesoreria::CAJA_GERENCIA, MovimientoTesoreria::CUENTA_BANCARIA])
            ->whereNotIn('CompraID', $comprasVinculadas ?: [0])
            ->with('proveedor')
            ->get();

        $caja = $this->crearEstadoCuenta(
            nombre: 'Caja Gerencia',
            tipo: MovimientoTesoreria::CAJA_GERENCIA,
            cuentaId: null,
            saldoActual: (float) ($fondo?->Saldo ?? 0),
            fecha: $fecha,
            movimientos: $movimientosBalance,
            movimientosFondo: $movimientosFondo,
            transferencias: $transferencias,
            gastosAntiguos: $gastosAntiguos,
            comprasAntiguas: $comprasAntiguas,
            extornosPorGasto: $extornosPorGasto,
            sedeGerenciaId: (int) $sedeGerencia->SedeID,
        );

        $cuentas = CuentaTesoreria::query()
            ->orderBy('Banco')
            ->orderBy('NumeroCuenta')
            ->get()
            ->map(fn (CuentaTesoreria $cuenta): array => $this->crearEstadoCuenta(
                nombre: $cuenta->NombreCompleto,
                tipo: MovimientoTesoreria::CUENTA_BANCARIA,
                cuentaId: (int) $cuenta->CuentaTesoreriaID,
                saldoActual: (float) $cuenta->SaldoActual,
                fecha: $fecha,
                movimientos: $movimientosBalance,
                movimientosFondo: collect(),
                transferencias: $transferencias,
                gastosAntiguos: collect(),
                comprasAntiguas: collect(),
                extornosPorGasto: $extornosPorGasto,
                sedeGerenciaId: (int) $sedeGerencia->SedeID,
            ))
            ->all();

        return [
            'fecha' => Carbon::parse($fecha),
            'sede' => $sedeGerencia,
            'caja' => $caja,
            'cuentas' => $cuentas,
            'saldosSedes' => $this->saldosSedes($fecha, (int) $sedeGerencia->SedeID),
            'generadoEn' => now(),
        ];
    }

    private function saldosSedes(string $fecha, int $sedeGerenciaId): array
    {
        $limite = Carbon::parse($fecha)->endOfDay();

        return Sede::query()
            ->where('Activo', true)
            ->where('SedeID', '<>', $sedeGerenciaId)
            ->orderBy('Nombre')
            ->get()
            ->map(fn (Sede $sede): array => [
                'nombre' => $sede->Nombre,
                'caja_abierta' => $this->saldoCajaAbiertaParaResumenGerencia((int) $sede->SedeID, $limite),
                'caja_chica' => $this->saldoCajaChicaHasta((int) $sede->SedeID, $limite),
            ])
            ->all();
    }

    private function saldoCajaAbiertaParaResumenGerencia(int $sedeId, Carbon $limite): float
    {
        // El reporte individual por sede descuenta este mismo ajuste fijo.
        return round(
            $this->saldoCajaAbiertaHasta($sedeId, $limite) - self::AJUSTE_CAJA_ABIERTA_SEDE,
            2
        );
    }

    private function saldoCajaAbiertaHasta(int $sedeId, Carbon $limite): float
    {
        $transferenciasRecibidas = $this->transferenciasHasta($sedeId, $limite, 'CAJA_ABIERTA', true);
        $transferenciasEnviadas = $this->transferenciasHasta($sedeId, $limite, 'CAJA_ABIERTA', false);

        $pagos = Pago::withoutGlobalScopes()
            ->where('Activo', true)
            ->where('ExcluirBalanceDiario', false)
            ->whereNull('SolicitudResolucionID')
            ->where('EsPagoAMayorPorMora', false)
            ->where(fn ($query) => $query->whereNull('TipoConcepto')->orWhere('TipoConcepto', 'C'))
            ->where('FechaPago', '<=', $limite)
            ->where('SedeID', $sedeId)
            ->get(['MontoPagado', 'Comentario'])
            ->sum(function (Pago $pago): float {
                if ($pago->Comentario && preg_match('/Pago original:\s*S\/\s*([\d,]+(?:\.\d{2})?)/', $pago->Comentario, $coincidencia)) {
                    return (float) str_replace(',', '', $coincidencia[1]);
                }

                return (float) $pago->MontoPagado;
            });

        $creditos = (float) Credito::withoutGlobalScopes()
            ->join('ProposicionCredito', 'Credito.ProposicionCreditoID', '=', 'ProposicionCredito.ProposicionCreditoID')
            ->where('Credito.Activo', true)
            ->where('Credito.SedeID', $sedeId)
            ->where('Credito.FechaGeneracion', '<=', $limite)
            ->sum('ProposicionCredito.MontoTotal');

        $otrosMovimientos = MovimientoFondo::withoutGlobalScopes()
            ->where('SedeID', $sedeId)
            ->where('FechaMovimiento', '<=', $limite)
            ->whereIn('Tipo', [
                'INGRESO_CAPITAL',
                'TRASLADO_CA_A_CC',
                'TRASLADO_CC_A_CA',
                'EGRESO_DEVOLUCION_EFECTIVO',
            ])
            ->get(['Tipo', 'Monto']);

        $inyecciones = (float) $otrosMovimientos->where('Tipo', 'INGRESO_CAPITAL')->sum('Monto');
        $trasladosEntrada = (float) $otrosMovimientos->where('Tipo', 'TRASLADO_CC_A_CA')->sum(fn (MovimientoFondo $movimiento): float => abs((float) $movimiento->Monto));
        $trasladosSalida = (float) $otrosMovimientos->where('Tipo', 'TRASLADO_CA_A_CC')->sum(fn (MovimientoFondo $movimiento): float => abs((float) $movimiento->Monto));
        $devolucionesEfectivo = (float) $otrosMovimientos->where('Tipo', 'EGRESO_DEVOLUCION_EFECTIVO')->sum(fn (MovimientoFondo $movimiento): float => abs((float) $movimiento->Monto));

        $excedentes = Excedente::withoutGlobalScopes()
            ->where('SedeID', $sedeId)
            ->where('Activo', true)
            ->where(fn ($query) => $query->where('Cuenta', 'Caja Abierta')->orWhereNull('Cuenta'))
            ->where('Fecha', '<=', $limite)
            ->withSum(['resoluciones as monto_aplicado' => fn ($query) => $query
                ->withoutGlobalScopes()
                ->where('Estado', 'APROBADA')], 'MontoAplicar')
            ->get()
            ->sum(fn (Excedente $excedente): float => (float) $excedente->Monto + (float) ($excedente->monto_aplicado ?? 0));

        $moras = (float) Pago::withoutGlobalScopes()
            ->where('Activo', true)
            ->where('EsPagoAMayorPorMora', true)
            ->where('FechaPago', '<=', $limite)
            ->where('SedeID', $sedeId)
            ->sum('MontoPagado');

        return round(
            $transferenciasRecibidas + $pagos + $inyecciones + $trasladosEntrada + $excedentes + $moras
                - $transferenciasEnviadas - $creditos - $trasladosSalida - $devolucionesEfectivo,
            2
        );
    }

    private function saldoCajaChicaHasta(int $sedeId, Carbon $limite): float
    {
        $movimientosChica = MovimientoFondo::withoutGlobalScopes()
            ->where('SedeID', $sedeId)
            ->where('FechaMovimiento', '<=', $limite)
            ->get(['Tipo', 'Monto', 'Observacion', 'TransferenciaID']);

        $ingresosHistoricosChica = $movimientosChica
            ->whereIn('Tipo', ['INGRESO_CAJA_CHICA', 'TRASLADO_CA_A_CC'])
            ->filter(function (MovimientoFondo $movimiento): bool {
                $observacion = mb_strtolower((string) $movimiento->Observacion, 'UTF-8');

                return $movimiento->Observacion !== null
                    && !str_contains($observacion, 'ajuste')
                    && !str_contains($observacion, 'reversión');
            });

        $ingresosManuales = (float) $ingresosHistoricosChica->where('Tipo', 'INGRESO_CAJA_CHICA')->sum('Monto');

        $trasladosEntrada = (float) $ingresosHistoricosChica
            ->where('Tipo', 'TRASLADO_CA_A_CC')
            ->sum(fn (MovimientoFondo $movimiento): float => abs((float) $movimiento->Monto));

        $transferenciasRecibidas = $this->movimientosTransferenciaCajaChicaHasta($sedeId, $limite, 'RECEPCION_TRANSFERENCIA', 'CuentaDestino')
            ->sum(fn (MovimientoFondo $movimiento): float => (float) $movimiento->Monto);

        $gastos = (float) Gasto::withoutGlobalScopes()
            ->where('SedeID', $sedeId)
            ->where('Activo', true)
            ->where('MetodoGasto', 'CAJA CHICA')
            ->where('FechaEmision', '<=', $limite)
            ->sum('Total');

        $compras = (float) Compra::withoutGlobalScopes()
            ->where('SedeID', $sedeId)
            ->where('Activo', true)
            ->where('TipoCompra', 'CONTADO')
            ->where('FechaEmision', '<=', $limite)
            ->sum('Total');

        $trasladosSalida = (float) $movimientosChica
            ->where('Tipo', 'TRASLADO_CC_A_CA')
            ->sum(fn (MovimientoFondo $movimiento): float => abs((float) $movimiento->Monto));

        $transferenciasEnviadas = $this->movimientosTransferenciaCajaChicaHasta($sedeId, $limite, 'ENVIO_TRANSFERENCIA', 'CuentaOrigen')
            ->sum(fn (MovimientoFondo $movimiento): float => abs((float) $movimiento->Monto));

        return round(
            $ingresosManuales + $trasladosEntrada + $transferenciasRecibidas
                - $gastos - $compras - $trasladosSalida - $transferenciasEnviadas,
            2
        );
    }

    private function movimientosTransferenciaCajaChicaHasta(int $sedeId, Carbon $limite, string $tipo, string $campoCuenta): Collection
    {
        $consulta = MovimientoFondo::withoutGlobalScopes()
            ->where('movimientos_fondo.SedeID', $sedeId)
            ->where('movimientos_fondo.Tipo', $tipo)
            ->where('movimientos_fondo.FechaMovimiento', '<=', $limite)
            ->join('transferencia_sedes', 'movimientos_fondo.TransferenciaID', '=', 'transferencia_sedes.TransferenciaID')
            ->where("transferencia_sedes.{$campoCuenta}", 'CAJA_CHICA');

        if ($tipo === 'RECEPCION_TRANSFERENCIA') {
            $consulta->where('movimientos_fondo.Observacion', 'NOT LIKE', '%Ajuste%')
                ->where('movimientos_fondo.Observacion', 'NOT LIKE', '%Reversión%');
        }

        return $consulta->get(['movimientos_fondo.Tipo', 'movimientos_fondo.Monto', 'movimientos_fondo.Observacion']);
    }

    private function transferenciasHasta(int $sedeId, Carbon $limite, string $cuenta, bool $recibidas): float
    {
        $consulta = TransferenciaSede::withoutGlobalScopes()
            ->where('Estado', 'ACEPTADO')
            ->where($recibidas ? 'CuentaDestino' : 'CuentaOrigen', $cuenta)
            ->where(function ($query) use ($limite): void {
                $query->where('FechaRespuesta', '<=', $limite)
                    ->orWhere(fn ($q) => $q->whereNull('FechaRespuesta')->where('FechaTransferencia', '<=', $limite));
            })
            ->where(function ($query) use ($sedeId, $recibidas): void {
                $solicitudGerencia = $recibidas ? 'SedeOrigenID' : 'SedeDestinoID';
                $transferenciaNormal = $recibidas ? 'SedeDestinoID' : 'SedeOrigenID';

                $query->where(fn ($q) => $q->where('EsSolicitudGerencia', true)->where($solicitudGerencia, $sedeId))
                    ->orWhere(fn ($q) => $q
                        ->where(fn ($q2) => $q2->where('EsSolicitudGerencia', false)->orWhereNull('EsSolicitudGerencia'))
                        ->where($transferenciaNormal, $sedeId));
            });

        return (float) $consulta->sum('Monto');
    }

    private function crearEstadoCuenta(
        string $nombre,
        string $tipo,
        ?int $cuentaId,
        float $saldoActual,
        string $fecha,
        $movimientos,
        $movimientosFondo,
        $transferencias,
        $gastosAntiguos,
        $comprasAntiguas,
        $extornosPorGasto,
        int $sedeGerenciaId,
    ): array {
        $transacciones = [];

        foreach ($movimientos as $movimiento) {
            $esOrigen = $movimiento->OrigenTipo === $tipo
                && ($tipo === MovimientoTesoreria::CAJA_GERENCIA || (int) $movimiento->CuentaOrigenID === $cuentaId);
            $esDestino = $movimiento->DestinoTipo === $tipo
                && ($tipo === MovimientoTesoreria::CAJA_GERENCIA || (int) $movimiento->CuentaDestinoID === $cuentaId);

            if (! $esOrigen && ! $esDestino) {
                continue;
            }

            $monto = (float) $movimiento->Monto * ($esDestino ? 1 : -1);
            $concepto = $movimiento->Concepto;
            $categoria = 'otros';

            if ($movimiento->GastoID) {
                $categoria = 'gastos';
                $gasto = $movimiento->gasto;
                $esAjusteGasto = in_array($movimiento->Tipo, [
                    MovimientoTesoreria::TIPO_AJUSTE_GASTO,
                    MovimientoTesoreria::TIPO_EXTORNO_GASTO,
                ], true);

                if (! $esAjusteGasto) {
                    $concepto = ($gasto?->motivo?->Nombre ?: 'Gasto') . " #{$movimiento->GastoID}";
                }

                if ($movimiento->Tipo === MovimientoTesoreria::TIPO_EGRESO_GASTO && $gasto) {
                    $diferenciaDocumento = round((float) $movimiento->Monto - (float) $gasto->Total, 2);
                    if (abs($diferenciaDocumento) >= 0.01) {
                        $concepto .= ' (documento S/ ' . number_format((float) $gasto->Total, 2, '.', ',') . ')';
                    }

                    if (! $gasto->Activo) {
                        $extornos = $extornosPorGasto->get((int) $movimiento->GastoID);
                        $concepto .= $extornos
                            ? ' - ANULADO; extorno(s): ' . $extornos
                            : ' - INACTIVO SIN EXTORNO';
                    }
                }
            } elseif ($movimiento->CompraID) {
                $categoria = 'compras';
                $concepto = $movimiento->compra?->proveedor?->Nombre ?: $concepto;
            } elseif (in_array($movimiento->Tipo, [MovimientoTesoreria::TIPO_TRANSFERENCIA, MovimientoTesoreria::TIPO_EXTORNO], true)) {
                $categoria = 'remesa';
            }

            $transacciones[] = [
                'fecha' => Carbon::parse($movimiento->FechaContable)->toDateString(),
                'orden' => $movimiento->FechaMovimiento?->format('Y-m-d H:i:s') ?? $movimiento->created_at?->format('Y-m-d H:i:s') ?? '',
                'id_orden' => (int) $movimiento->MovimientoTesoreriaID,
                'numero' => (string) $movimiento->MovimientoTesoreriaID,
                'categoria' => $categoria,
                'concepto' => $concepto ?: $movimiento->Tipo,
                'contraparte' => $esOrigen ? $movimiento->CuentaDestinoNombre : $movimiento->CuentaOrigenNombre,
                'cuenta' => $nombre,
                'monto' => $monto,
                'saldo_antes' => $esOrigen ? $movimiento->SaldoAnteriorOrigen : $movimiento->SaldoAnteriorDestino,
                'saldo_despues' => $esOrigen ? $movimiento->SaldoNuevoOrigen : $movimiento->SaldoNuevoDestino,
                'es_remesa' => $categoria === 'remesa',
            ];
        }

        foreach ($movimientosFondo as $movimiento) {
            $transferencia = $transferencias->get($movimiento->TransferenciaID);
            $esRemesa = $transferencia !== null;
            if (! $this->movimientoFondoAfectaCajaGerencia($movimiento, $transferencia, $sedeGerenciaId)) {
                continue;
            }

            $contraparte = $transferencia
                ? ((int) $transferencia->SedeOrigenID === $sedeGerenciaId
                    ? $transferencia->sedeDestino?->Nombre
                    : $transferencia->sedeOrigen?->Nombre)
                : null;

            $transacciones[] = [
                'fecha' => Carbon::parse($movimiento->FechaMovimiento)->toDateString(),
                'orden' => $movimiento->FechaMovimiento?->format('Y-m-d H:i:s') ?? '',
                'id_orden' => (int) $movimiento->MovimientoID,
                'numero' => (string) ($movimiento->TransferenciaID ?: $movimiento->MovimientoID),
                'categoria' => $esRemesa ? 'remesa' : 'otros',
                'concepto' => $transferencia
                    ? 'Remesa ' . strtolower(str_replace('_', ' ', $movimiento->Tipo))
                    : ($movimiento->Tipo === 'AJUSTE_CONCILIACION_CAJA'
                        ? 'Ajuste de conciliación de Caja Gerencia'
                        : ($movimiento->Observacion ?: $movimiento->Tipo)),
                'contraparte' => $contraparte ?: 'Caja Gerencia',
                'cuenta' => 'Caja Abierta',
                'monto' => (float) $movimiento->Monto,
                'saldo_antes' => $movimiento->Tipo === 'TRASLADO_CC_A_CA' ? null : $movimiento->SaldoAnterior,
                'saldo_despues' => $movimiento->Tipo === 'TRASLADO_CC_A_CA' ? null : $movimiento->SaldoNuevo,
                'es_remesa' => $esRemesa,
            ];
        }

        foreach ($gastosAntiguos as $gasto) {
            if (! $this->perteneceACuenta($gasto->OrigenTesoreriaTipo, $gasto->CuentaTesoreriaID, $tipo, $cuentaId)) {
                continue;
            }

            $transacciones[] = $this->crearTransaccionAntigua(
                $gasto->FechaEmision,
                $gasto->GastoID,
                'gastos',
                $gasto->motivo?->Nombre ?: 'Gasto',
                $nombre,
                (float) $gasto->Total,
            );
        }

        foreach ($comprasAntiguas as $compra) {
            if (! $this->perteneceACuenta($compra->OrigenTesoreriaTipo, $compra->CuentaTesoreriaID, $tipo, $cuentaId)) {
                continue;
            }

            $transacciones[] = $this->crearTransaccionAntigua(
                $compra->FechaPago ?? $compra->FechaEmision,
                $compra->CompraID,
                'compras',
                $compra->proveedor?->Nombre ?: 'Compra',
                $nombre,
                (float) $compra->Total,
            );
        }

        usort($transacciones, fn (array $a, array $b): int => [$a['fecha'], $a['orden'], $a['id_orden']] <=> [$b['fecha'], $b['orden'], $b['id_orden']]);

        $dia = array_values(array_filter($transacciones, fn (array $item): bool => $item['fecha'] === $fecha));
        $opening = $this->calcularSaldoInicial($transacciones, $fecha, $saldoActual);
        $closing = $this->calcularSaldoCierre($transacciones, $fecha, $saldoActual);

        $running = $opening;
        foreach ($dia as &$transaccion) {
            $running = round($running + $transaccion['monto'], 2);
            $transaccion['saldo'] = $running;
            $transaccion['ingreso'] = $transaccion['monto'] > 0 ? $transaccion['monto'] : 0;
            $transaccion['salida'] = $transaccion['monto'] < 0 ? abs($transaccion['monto']) : 0;
        }
        unset($transaccion);

        $excedente = round($closing - $running, 2);
        $secciones = [
            'remesas_entrada' => [],
            'remesas_salida' => [],
            'gastos' => [],
            'compras' => [],
            'otros' => [],
        ];

        foreach ($dia as $transaccion) {
            if ($transaccion['categoria'] === 'remesa') {
                $secciones[$transaccion['monto'] >= 0 ? 'remesas_entrada' : 'remesas_salida'][] = $transaccion;
            } else {
                $secciones[$transaccion['categoria']][] = $transaccion;
            }
        }

        return [
            'nombre' => $nombre,
            'saldo_inicial' => round($opening, 2),
            'saldo_cierre' => round($closing, 2),
            'excedente' => $excedente,
            'movimientos' => $dia,
            'secciones' => $secciones,
            'total_ingresos' => round(array_sum(array_column($dia, 'ingreso')), 2),
            'total_salidas' => round(array_sum(array_column($dia, 'salida')), 2),
        ];
    }

    private function crearTransaccionAntigua($fecha, int $id, string $categoria, string $concepto, string $cuenta, float $monto): array
    {
        return [
            'fecha' => Carbon::parse($fecha)->toDateString(),
            'orden' => Carbon::parse($fecha)->format('Y-m-d H:i:s'),
            'id_orden' => $id,
            'numero' => (string) $id,
            'categoria' => $categoria,
            'concepto' => $concepto,
            'contraparte' => $concepto,
            'cuenta' => $cuenta,
            'monto' => -abs($monto),
            'saldo_antes' => null,
            'saldo_despues' => null,
            'es_remesa' => false,
        ];
    }

    private function perteneceACuenta(?string $origenTipo, ?int $origenCuentaId, string $tipo, ?int $cuentaId): bool
    {
        if ($origenTipo !== $tipo) {
            return false;
        }

        return $tipo === MovimientoTesoreria::CAJA_GERENCIA
            || (int) $origenCuentaId === $cuentaId;
    }

    private function movimientoFondoAfectaCajaGerencia(MovimientoFondo $movimiento, ?TransferenciaSede $transferencia, int $sedeGerenciaId): bool
    {
        if ($transferencia) {
            if ($transferencia->EsSolicitudGerencia || $transferencia->EsSolicitudCapital) {
                return true;
            }

            $gerenciaEsOrigen = (int) $transferencia->SedeOrigenID === $sedeGerenciaId;
            $cuenta = $gerenciaEsOrigen ? $transferencia->CuentaOrigen : $transferencia->CuentaDestino;

            return ($cuenta ?? 'CAJA_ABIERTA') !== 'CAJA_CHICA';
        }

        return ! in_array($movimiento->Tipo, [
            'INGRESO_CAJA_CHICA',
            'EGRESO_CAJA_CHICA',
        ], true);
    }

    private function calcularSaldoInicial(array $transacciones, string $fecha, float $saldoActual): float
    {
        $saldoCierre = $this->calcularSaldoCierre($transacciones, $fecha, $saldoActual);
        $movimientosDelDia = array_sum(array_map(
            fn (array $transaccion): float => $transaccion['fecha'] === $fecha ? $transaccion['monto'] : 0,
            $transacciones,
        ));

        return round($saldoCierre - $movimientosDelDia, 2);
    }

    private function calcularSaldoCierre(array $transacciones, string $fecha, float $saldoActual): float
    {
        $fechaActual = now()->toDateString();
        if ($fecha === $fechaActual) {
            return round($saldoActual, 2);
        }

        $saldoPorFlujos = $saldoActual - array_sum(array_map(
            fn (array $transaccion): float => $transaccion['fecha'] > $fecha ? $transaccion['monto'] : 0,
            $transacciones,
        ));

        return round($saldoPorFlujos, 2);
    }

    private function extornosPorGasto($movimientos)
    {
        return $movimientos
            ->filter(fn (MovimientoTesoreria $movimiento): bool =>
                $movimiento->Tipo === MovimientoTesoreria::TIPO_EXTORNO_GASTO
                && $movimiento->GastoID !== null
            )
            ->groupBy('GastoID')
            ->map(fn ($extornos): string => $extornos
                ->map(fn (MovimientoTesoreria $extorno): string => '#'
                    . $extorno->MovimientoTesoreriaID
                    . ' (' . Carbon::parse($extorno->FechaContable)->format('d/m/Y') . ')'
                )
                ->implode(', ')
            );
    }

    private function movimientosParaBalance(Collection $movimientos): Collection
    {
        return $movimientos->values();
    }
}
