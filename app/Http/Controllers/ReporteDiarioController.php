<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AperturaCierreDia;
use App\Models\Pago;
use App\Models\Credito;
use App\Models\Sede;
use App\Models\Gasto;
use App\Models\TransferenciaSede;
use App\Models\SolicitudExoneracion;
use App\Models\Excedente;
use App\Models\FondoSede;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ReporteDiarioController extends Controller
{
    /**
     * Genera el reporte general del día (cierre de caja) en PDF.
     *
     * Estructura según requerimiento del cliente:
     *   1. CAJA CHICA - Gastos (Total de gastos diarios)
     *   2. INGRESO DE REMESAS - Transferencias recibidas
     *   3. SALIDA DE REMESAS - Transferencias enviadas
     *   4. CAJA ABIERTA:
     *      - Exoneración (Moras Intereses)
     *      - Extornos / Devoluciones
     *      - Amortizaciones (Pagos en orden)
     *   5. CREDITOS EMITIDOS
     */
    public function descargar(Request $request)
    {
        $fecha = $request->get('fecha');
        $aperturaCierreDiaId = $request->get('id');
        $sedeIdParam = $request->get('sede_id') ?? $request->get('sede');

        if (!$fecha) {
            abort(404, 'Fecha no proporcionada');
        }

        $fechaCarbon = Carbon::createFromFormat('Y-m-d', $fecha);
        $ahora = Carbon::now();
        $fechaInicioDia = $fechaCarbon->copy()->startOfDay();
        $fechaFinDia = $fechaCarbon->copy()->endOfDay();

        // Obtener el registro de apertura/cierre para la sede
        $aperturaCierre = null;
        if ($aperturaCierreDiaId) {
            $aperturaCierre = AperturaCierreDia::withoutGlobalScopes()
                ->find($aperturaCierreDiaId);
        }

        $user = auth()->user();
        if ($user && $user->isPrivileged()) {
            if ($sedeIdParam === '0' || $sedeIdParam === 'todas' || $sedeIdParam === '') {
                $sedeId = null;
            } elseif ($sedeIdParam) {
                $sedeId = (int) $sedeIdParam;
            } else {
                $sedeId = $aperturaCierre?->SedeID ?? $user->getEffectiveSedeId();
            }
        } else {
            $sedeId = $user?->getEffectiveSedeId() ?? $aperturaCierre?->SedeID;
        }

        $sede = $sedeId ? Sede::find($sedeId) : null;
        $sedeNombre = $sede?->Nombre ?? 'SEDE NO ESPECIFICADA';

        $fondo = $sedeId ? FondoSede::where('SedeID', $sedeId)->first() : null;
        $saldoCajaAbierta = $fondo ? $fondo->Saldo : 0;
        $saldoCajaChica = 0; // Se calculará dinámicamente más abajo
        $saldoInicialCajaChica = 0; // Se calculará dinámicamente más abajo

        $saldoCuentaAMayor = 0;
        if ($sedeId) {
            $saldoCuentaAMayor = Pago::withoutGlobalScopes()
                ->where('SedeID', $sedeId)
                ->where('Activo', true)
                ->where('EsPagoAMayor', true)
                ->where('FechaPago', '<=', $fechaFinDia)
                ->whereHas('solicitudResolucion', function($q) {
                    $q->where('TipoResolucion', '!=', 'TRASLADO_DE_PAGO');
                })
                ->sum('MontoPagado');
        }

        // ─── 1. CAJA CHICA: GASTOS DEL DÍA ───
        $gastosQuery = Gasto::withoutGlobalScopes()
            ->where('Activo', true)
            ->whereDate('FechaEmision', $fecha);

        if ($sedeId) {
            $gastosQuery->where('SedeID', $sedeId);
        }

        $gastos = $gastosQuery
            ->with(['proveedor', 'motivo', 'detalles'])
            ->orderBy('GastoID', 'asc')
            ->get();

        $totalGastos = $gastos->sum('Total');

        // ─── 1B. CAJA CHICA: COMPRAS DEL DÍA ───
        $comprasQuery = \App\Models\Compra::withoutGlobalScopes()
            ->where('Activo', true)
            ->whereDate('FechaEmision', $fecha);

        if ($sedeId) {
            $comprasQuery->where('SedeID', $sedeId);
        }

        $compras = $comprasQuery
            ->with(['proveedor', 'detalles'])
            ->orderBy('CompraID', 'asc')
            ->get();

        $totalCompras = $compras->sum('Total');

        // ─── 2. INGRESO DE REMESAS (transferencias recibidas y aceptadas) ───
        $ingresosRemesasQuery = TransferenciaSede::withoutGlobalScopes()
            ->where('Estado', 'ACEPTADO')
            ->where(function($q) use ($fechaInicioDia, $fechaFinDia) {
                $q->whereBetween('FechaRespuesta', [$fechaInicioDia, $fechaFinDia])
                  ->orWhere(function($q2) use ($fechaInicioDia, $fechaFinDia) {
                      $q2->whereNull('FechaRespuesta')->whereBetween('FechaTransferencia', [$fechaInicioDia, $fechaFinDia]);
                  });
            });

        if ($sedeId) {
            $ingresosRemesasQuery->where('SedeDestinoID', $sedeId);
        }

        $ingresosRemesas = $ingresosRemesasQuery
            ->with(['sedeOrigen', 'sedeDestino', 'usuarioOrigen'])
            ->orderBy('TransferenciaID', 'asc')
            ->get();

        $totalIngresosRemesas = $ingresosRemesas->sum('Monto');

        // ─── 3. SALIDA DE REMESAS (transferencias enviadas y aceptadas) ───
        $salidasRemesasQuery = TransferenciaSede::withoutGlobalScopes()
            ->where('Estado', 'ACEPTADO')
            ->where(function($q) use ($fechaInicioDia, $fechaFinDia) {
                $q->whereBetween('FechaRespuesta', [$fechaInicioDia, $fechaFinDia])
                  ->orWhere(function($q2) use ($fechaInicioDia, $fechaFinDia) {
                      $q2->whereNull('FechaRespuesta')->whereBetween('FechaTransferencia', [$fechaInicioDia, $fechaFinDia]);
                  });
            });

        if ($sedeId) {
            $salidasRemesasQuery->where('SedeOrigenID', $sedeId);
        }

        $salidasRemesas = $salidasRemesasQuery
            ->with(['sedeOrigen', 'sedeDestino', 'usuarioOrigen'])
            ->orderBy('TransferenciaID', 'asc')
            ->get();

        $totalSalidasRemesas = $salidasRemesas->sum('Monto');

        // ─── 4a. CAJA ABIERTA - EXONERACIONES (Moras e Intereses) ───
        $exoneracionesQuery = SolicitudExoneracion::withoutGlobalScopes()
            ->where('Estado', 'APROBADO')
            ->where('Activo', true)
            ->whereBetween('FechaAprobacion', [$fechaInicioDia, $fechaFinDia]);

        if ($sedeId) {
            $exoneracionesQuery->where('SedeID', $sedeId);
        }

        $exoneraciones = $exoneracionesQuery
            ->with(['credito.proposicion.cliente', 'tipoExoneracion'])
            ->orderBy('SolicitudExoneracionID', 'asc')
            ->get();

        $totalExoneraciones = $exoneraciones->sum('MontoExonerado');

        // ─── 4b. CAJA ABIERTA - EXTORNOS / DEVOLUCIONES (Solicitudes resueltas) ───
        $extornosQuery = \App\Models\SolicitudResolucionExcedente::withoutGlobalScopes()
            ->where('Estado', 'APROBADA')
            ->whereBetween('created_at', [$fechaInicioDia, $fechaFinDia]);

        if ($sedeId) {
            $extornosQuery->where('SedeID', $sedeId);
        }

        $extornos = $extornosQuery
            ->with(['clienteOrigen', 'clienteDestino', 'excedente', 'creditoDestino.proposicion.cliente'])
            ->orderBy('SolicitudID', 'asc')
            ->get();

        $totalExtornos = $extornos->sum('MontoAplicar');

        // ─── 4c. CAJA ABIERTA - EXCEDENTES DEL DÍA ───
        $excedentesDiaParaTabla = \App\Models\Excedente::withoutGlobalScopes()
            ->where('SedeID', $sedeId)
            ->where('Activo', true)
            ->where('Cuenta', 'Caja Abierta')
            ->whereBetween('Fecha', [$fechaInicioDia, $fechaFinDia])
            ->with(['resoluciones' => function($q) {
                $q->where('Estado', 'APROBADA')
                  ->with(['creditoDestino.proposicion.cliente', 'clienteDestino']);
            }])
            ->orderBy('ExcedenteID', 'asc')
            ->get();

        // ─── 4d. CAJA ABIERTA - AMORTIZACIONES (Pagos Físicos) ───
        $pagosQuery = Pago::withoutGlobalScopes()
            ->where('pago.Activo', true)
            ->where('pago.EsPagoAMayor', false)
            ->where('pago.EsPagoAMayorPorMora', false)
            ->where(function($q) {
                $q->whereNull('pago.EstadoTraslado')
                  ->orWhere('pago.EstadoTraslado', '!=', 'TRASLADADO');
            })
            ->whereDate('pago.FechaPago', $fecha);

        if ($sedeId) {
            $pagosQuery->where('pago.SedeID', $sedeId);
        }

        $pagos = $pagosQuery
            ->join('Credito', 'pago.CreditoID', '=', 'Credito.CreditoID')
            ->join('ProposicionCredito', 'Credito.ProposicionCreditoID', '=', 'ProposicionCredito.ProposicionCreditoID')
            ->leftJoin('Zona', 'ProposicionCredito.ZonaID', '=', 'Zona.ZonaID')
            ->join('Cliente', 'ProposicionCredito.ClienteID', '=', 'Cliente.ClienteID')
            ->leftJoin('TipoCredito', 'ProposicionCredito.TipoCreditoID', '=', 'TipoCredito.TipoCreditoID')
            ->select(
                'pago.PagoID',
                'ProposicionCredito.CodigoCredito',
                'Zona.Nombre as ZonaNombre',
                'TipoCredito.Codigo as TipoCreditoCodigo',
                'Cliente.NombresApellidos',
                'pago.MontoPagado',
                'pago.Comentario',
                'pago.TipoPago'
            )
            ->orderBy('pago.PagoID', 'asc')
            ->get()
            ->each(function($p) {
                if ($p->Comentario && preg_match('/Pago original:\s*S\/\s*([\d,]+(?:\.\d{2})?)/', $p->Comentario, $m)) {
                    $p->MontoOriginal = (float) str_replace(',', '', $m[1]);
                } else {
                    $p->MontoOriginal = (float)$p->MontoPagado;
                }
            });

        $totalAmortizaciones = $pagos->sum(function($p) {
            return $p->MontoOriginal;
        });

        // ─── 5. CREDITOS EMITIDOS ───
        $creditosQuery = Credito::withoutGlobalScopes()
            ->where('Credito.Activo', true)
            ->whereDate('Credito.FechaGeneracion', $fecha);

        if ($sedeId) {
            $creditosQuery->where('Credito.SedeID', $sedeId);
        }

        $creditos = $creditosQuery
            ->join('ProposicionCredito', 'Credito.ProposicionCreditoID', '=', 'ProposicionCredito.ProposicionCreditoID')
            ->join('Cliente', 'ProposicionCredito.ClienteID', '=', 'Cliente.ClienteID')
            ->leftJoin('TipoCredito', 'ProposicionCredito.TipoCreditoID', '=', 'TipoCredito.TipoCreditoID')
            ->select(
                'ProposicionCredito.CodigoCredito',
                'TipoCredito.Codigo as TipoCreditoCodigo',
                'Cliente.NombresApellidos',
                'ProposicionCredito.MontoTotal',
                'ProposicionCredito.MontoInteres',
                'ProposicionCredito.MontoTotalPagar',
                'ProposicionCredito.NumeroCuotas',
                'ProposicionCredito.MontoCuota'
            )
            ->orderBy('Credito.CreditoID', 'asc')
            ->get();

        $totalCreditosEmitidos = $creditos->sum('MontoTotal');

        // ─── CÁLCULO DE SALDOS EXACTOS POR DÍA (BASADO EN TABLAS REALES) ───
        $saldoInicialCajaAbierta = 0;
        $saldoCierreCajaAbierta = 0;
        $totalInyeccionesDia = 0;
        $totalOtrasOperacionesDia = 0;
        $totalIngresosCajaChica = 0;

        if ($sedeId) {
            // Función auxiliar para calcular el saldo real hasta un momento dado
            $calcularSaldoHasta = function ($fechaLimite) use ($sedeId) {
                // 1. Transferencias Recibidas (Entrada)
                $transferenciasRecibidas = \App\Models\TransferenciaSede::withoutGlobalScopes()
                    ->where('SedeDestinoID', $sedeId)
                    ->where('Estado', 'ACEPTADO')
                    ->where('CuentaDestino', 'CAJA_ABIERTA')
                    ->where(function($q) use ($fechaLimite) {
                        $q->where('FechaRespuesta', '<=', $fechaLimite)
                          ->orWhere(function($q2) use ($fechaLimite) {
                              $q2->whereNull('FechaRespuesta')->where('FechaTransferencia', '<=', $fechaLimite);
                          });
                    })
                    ->sum('Monto');

                // 2. Transferencias Enviadas (Salida)
                $transferenciasEnviadas = \App\Models\TransferenciaSede::withoutGlobalScopes()
                    ->where('SedeOrigenID', $sedeId)
                    ->where('Estado', 'ACEPTADO')
                    ->where('CuentaOrigen', 'CAJA_ABIERTA')
                    ->where(function($q) use ($fechaLimite) {
                        $q->where('FechaRespuesta', '<=', $fechaLimite)
                          ->orWhere(function($q2) use ($fechaLimite) {
                              $q2->whereNull('FechaRespuesta')->where('FechaTransferencia', '<=', $fechaLimite);
                          });
                    })
                    ->sum('Monto');

                // 3. Pagos / Amortizaciones (Entrada física) — descontando porciones aplicadas por excedentes
                $pagosRaw = \App\Models\Pago::withoutGlobalScopes()
                    ->where('Activo', true)
                    ->where('EsPagoAMayor', false)
                    ->where('EsPagoAMayorPorMora', false)
                    ->where(function($q) {
                        $q->whereNull('EstadoTraslado')
                          ->orWhere('EstadoTraslado', '!=', 'TRASLADADO');
                    })
                    ->where('FechaPago', '<=', $fechaLimite)
                    ->where('SedeID', $sedeId)
                    ->select('MontoPagado', 'Comentario')
                    ->get();

                $pagos = $pagosRaw->sum(function($p) {
                    if ($p->Comentario && preg_match('/Pago original:\s*S\/\s*([\d,]+(?:\.\d{2})?)/', $p->Comentario, $m)) {
                        return (float) str_replace(',', '', $m[1]);
                    }
                    return (float)$p->MontoPagado;
                });

                // 4. Créditos Emitidos (Salida)
                $creditos = \App\Models\Credito::withoutGlobalScopes()
                    ->join('ProposicionCredito', 'Credito.ProposicionCreditoID', '=', 'ProposicionCredito.ProposicionCreditoID')
                    ->where('Credito.Activo', true)
                    ->where('Credito.SedeID', $sedeId)
                    ->where('Credito.FechaGeneracion', '<=', $fechaLimite)
                    ->sum('ProposicionCredito.MontoTotal'); // El capital prestado

                // 5. Inyecciones Manuales y Traslados (desde MovimientoFondo, si existen)
                $otrosMovimientos = \App\Models\MovimientoFondo::where('SedeID', $sedeId)
                    ->where('FechaMovimiento', '<=', $fechaLimite)
                    ->whereIn('Tipo', ['INGRESO_CAPITAL', 'TRASLADO_CA_A_CC', 'TRASLADO_CC_A_CA'])
                    ->get();

                $inyecciones = $otrosMovimientos->where('Tipo', 'INGRESO_CAPITAL')->sum('Monto');
                $trasladosEntrada = $otrosMovimientos->where('Tipo', 'TRASLADO_CC_A_CA')->sum(function($m) { return abs($m->Monto); });
                $trasladosSalida = $otrosMovimientos->where('Tipo', 'TRASLADO_CA_A_CC')->sum(function($m) { return abs($m->Monto); });

                // 6. Excedentes (sobrantes registrados que entran a Caja Abierta)
                $excedentes = \App\Models\Excedente::withoutGlobalScopes()
                    ->where('SedeID', $sedeId)
                    ->where('Activo', true)
                    ->where('Cuenta', 'Caja Abierta')
                    ->where('Fecha', '<=', $fechaLimite)
                    ->withSum(['resoluciones as monto_aplicado' => function($q) {
                        $q->where('Estado', 'APROBADA');
                    }], 'MontoAplicar')
                    ->get()
                    ->sum(function($e) {
                        return (float)$e->Monto + (float)($e->monto_aplicado ?? 0);
                    });

                return $transferenciasRecibidas + $pagos + $inyecciones + $trasladosEntrada + $excedentes
                     - $transferenciasEnviadas - $creditos - $trasladosSalida;
            };

            // Calcular saldo un milisegundo antes de iniciar el día
            $saldoInicialCajaAbierta = $calcularSaldoHasta($fechaInicioDia->copy()->subSecond());
            
            // Calcular saldo al terminar el día
            $saldoCierreCajaAbierta = $calcularSaldoHasta($fechaFinDia);

            // ─── CÁLCULO DE SALDO DE CAJA CHICA (basado en Gasto/Compra, no MovimientoFondo) ───
            // Ingresos totales a CC hasta el inicio del día (transferencias, inyecciones)
            // NOTA: Se excluyen movimientos con 'Ajuste' o 'Reversión' para mantener consistencia
            //       con el reporte diario que no los muestra como ingresos del día.
            $ingresosCCHistoricos = \App\Models\MovimientoFondo::where('SedeID', $sedeId)
                ->where('FechaMovimiento', '<=', $fechaInicioDia->copy()->subSecond())
                ->where(function($q) {
                    $q->where('Tipo', 'INGRESO_CAJA_CHICA')
                      ->orWhere('Tipo', 'TRASLADO_CA_A_CC');
                })
                ->where('Observacion', 'NOT LIKE', '%Ajuste%')
                ->where('Observacion', 'NOT LIKE', '%Reversión%')
                ->get()
                ->sum(function($m) {
                    return $m->Tipo === 'TRASLADO_CA_A_CC' ? abs($m->Monto) : $m->Monto;
                });

            $ingresosCCHistoricos += \App\Models\MovimientoFondo::where('movimientos_fondo.SedeID', $sedeId)
                ->where('FechaMovimiento', '<=', $fechaInicioDia->copy()->subSecond())
                ->where('movimientos_fondo.Tipo', 'RECEPCION_TRANSFERENCIA')
                ->where('movimientos_fondo.Observacion', 'NOT LIKE', '%Ajuste%')
                ->where('movimientos_fondo.Observacion', 'NOT LIKE', '%Reversión%')
                ->join('transferencia_sedes', 'movimientos_fondo.TransferenciaID', '=', 'transferencia_sedes.TransferenciaID')
                ->where('transferencia_sedes.CuentaDestino', 'CAJA_CHICA')
                ->sum('movimientos_fondo.Monto');

            // Deducciones totales de CC hasta el inicio del día (desde Gasto/Compra)
            $deduccionesCCHistoricas = \App\Models\Gasto::withoutGlobalScopes()
                ->where('SedeID', $sedeId)
                ->where('Activo', true)
                ->where('MetodoGasto', 'CAJA CHICA')
                ->whereDate('FechaEmision', '<', $fecha)
                ->sum('Total');

            $deduccionesCCHistoricas += \App\Models\Compra::withoutGlobalScopes()
                ->where('SedeID', $sedeId)
                ->where('Activo', true)
                ->where('TipoCompra', 'CONTADO')
                ->whereDate('FechaEmision', '<', $fecha)
                ->sum('Total');

            // Traslados de CC → CA (dinero que salió de CC, no son gastos)
            $deduccionesCCHistoricas += \App\Models\MovimientoFondo::where('SedeID', $sedeId)
                ->where('Tipo', 'TRASLADO_CC_A_CA')
                ->where('FechaMovimiento', '<', $fechaInicioDia)
                ->sum(\DB::raw('ABS(Monto)'));

            // Envíos de transferencia desde CC
            $deduccionesCCHistoricas += \App\Models\MovimientoFondo::where('movimientos_fondo.SedeID', $sedeId)
                ->where('FechaMovimiento', '<', $fechaInicioDia)
                ->where('movimientos_fondo.Tipo', 'ENVIO_TRANSFERENCIA')
                ->join('transferencia_sedes', 'movimientos_fondo.TransferenciaID', '=', 'transferencia_sedes.TransferenciaID')
                ->where('transferencia_sedes.CuentaOrigen', 'CAJA_CHICA')
                ->sum(\DB::raw('ABS(movimientos_fondo.Monto)'));

            // SALDO INICIAL DE CAJA CHICA = Ingresos totales - Deducciones totales (hasta ayer)
            $saldoInicialCajaChica = $ingresosCCHistoricos - $deduccionesCCHistoricas;

            // Ingresos a Caja Chica del DÍA
            $ingresosCCManuales = \App\Models\MovimientoFondo::where('SedeID', $sedeId)
                ->whereBetween('FechaMovimiento', [$fechaInicioDia, $fechaFinDia])
                ->where('Tipo', 'INGRESO_CAJA_CHICA')
                ->where('Observacion', 'NOT LIKE', '%Ajuste%')
                ->sum('Monto');

            $trasladosACC = \App\Models\MovimientoFondo::where('SedeID', $sedeId)
                ->whereBetween('FechaMovimiento', [$fechaInicioDia, $fechaFinDia])
                ->where('Tipo', 'TRASLADO_CA_A_CC')
                ->sum(\DB::raw('ABS(Monto)'));

            $transferenciasCC = \App\Models\MovimientoFondo::where('movimientos_fondo.SedeID', $sedeId)
                ->whereBetween('FechaMovimiento', [$fechaInicioDia, $fechaFinDia])
                ->where('movimientos_fondo.Tipo', 'RECEPCION_TRANSFERENCIA')
                ->join('transferencia_sedes', 'movimientos_fondo.TransferenciaID', '=', 'transferencia_sedes.TransferenciaID')
                ->where('transferencia_sedes.CuentaDestino', 'CAJA_CHICA')
                ->sum('movimientos_fondo.Monto');

            $totalIngresosCajaChica = $ingresosCCManuales + $trasladosACC + $transferenciasCC;

            // SALDO CAJA CHICA = Inicial + Ingresos - Gastos/Compras del día (SOLO Gasto/Compra)
            $saldoCajaChica = $saldoInicialCajaChica + $totalIngresosCajaChica - ($totalGastos + $totalCompras);

            // Inyecciones o transferencias recibidas DEL DÍA (para mostrar en reporte)
            $transferenciasRecibidasDia = \App\Models\TransferenciaSede::withoutGlobalScopes()
                ->where('SedeDestinoID', $sedeId)
                ->where('Estado', 'ACEPTADO')
                ->where('CuentaDestino', 'CAJA_ABIERTA')
                ->where(function($q) use ($fechaInicioDia, $fechaFinDia) {
                    $q->whereBetween('FechaRespuesta', [$fechaInicioDia, $fechaFinDia])
                      ->orWhere(function($q2) use ($fechaInicioDia, $fechaFinDia) {
                          $q2->whereNull('FechaRespuesta')->whereBetween('FechaTransferencia', [$fechaInicioDia, $fechaFinDia]);
                      });
                })
                ->sum('Monto');
                
            $inyeccionesManualesDia = \App\Models\MovimientoFondo::where('SedeID', $sedeId)
                ->whereBetween('FechaMovimiento', [$fechaInicioDia, $fechaFinDia])
                ->where('Tipo', 'INGRESO_CAPITAL')
                ->sum('Monto');
                
            $totalInyeccionesDia = $transferenciasRecibidasDia + $inyeccionesManualesDia;

            // Excedentes del día (monto original = Monto actual + resoluciones aprobadas)
            $excedentesDia = \App\Models\Excedente::withoutGlobalScopes()
                ->where('SedeID', $sedeId)
                ->where('Activo', true)
                ->where('Cuenta', 'Caja Abierta')
                ->whereBetween('Fecha', [$fechaInicioDia, $fechaFinDia])
                ->withSum(['resoluciones as monto_aplicado' => function($q) {
                    $q->where('Estado', 'APROBADA');
                }], 'MontoAplicar')
                ->get();
            $totalExcedentesDia = $excedentesDia->sum(function($e) {
                return (float)$e->Monto + (float)($e->monto_aplicado ?? 0);
            });

            // Remesas netas del día para Caja Abierta
            $ingresosRemesasCA = \App\Models\TransferenciaSede::withoutGlobalScopes()
                ->where('SedeDestinoID', $sedeId)
                ->where('Estado', 'ACEPTADO')
                ->where('CuentaDestino', 'CAJA_ABIERTA')
                ->where(function($q) use ($fechaInicioDia, $fechaFinDia) {
                    $q->whereBetween('FechaRespuesta', [$fechaInicioDia, $fechaFinDia])
                      ->orWhere(function($q2) use ($fechaInicioDia, $fechaFinDia) {
                          $q2->whereNull('FechaRespuesta')->whereBetween('FechaTransferencia', [$fechaInicioDia, $fechaFinDia]);
                      });
                })
                ->sum('Monto');
            $salidasRemesasCA = \App\Models\TransferenciaSede::withoutGlobalScopes()
                ->where('SedeOrigenID', $sedeId)
                ->where('Estado', 'ACEPTADO')
                ->where('CuentaOrigen', 'CAJA_ABIERTA')
                ->where(function($q) use ($fechaInicioDia, $fechaFinDia) {
                    $q->whereBetween('FechaRespuesta', [$fechaInicioDia, $fechaFinDia])
                      ->orWhere(function($q2) use ($fechaInicioDia, $fechaFinDia) {
                          $q2->whereNull('FechaRespuesta')->whereBetween('FechaTransferencia', [$fechaInicioDia, $fechaFinDia]);
                      });
                })
                ->sum('Monto');
            $remesasNetCajaAbierta = $ingresosRemesasCA - $salidasRemesasCA;

            // Remesas netas del día para Caja Chica
            $ingresosRemesasCC = \App\Models\TransferenciaSede::withoutGlobalScopes()
                ->where('SedeDestinoID', $sedeId)
                ->where('Estado', 'ACEPTADO')
                ->where('CuentaDestino', 'CAJA_CHICA')
                ->where(function($q) use ($fechaInicioDia, $fechaFinDia) {
                    $q->whereBetween('FechaRespuesta', [$fechaInicioDia, $fechaFinDia])
                      ->orWhere(function($q2) use ($fechaInicioDia, $fechaFinDia) {
                          $q2->whereNull('FechaRespuesta')->whereBetween('FechaTransferencia', [$fechaInicioDia, $fechaFinDia]);
                      });
                })
                ->sum('Monto');
            $salidasRemesasCC = \App\Models\TransferenciaSede::withoutGlobalScopes()
                ->where('SedeOrigenID', $sedeId)
                ->where('Estado', 'ACEPTADO')
                ->where('CuentaOrigen', 'CAJA_CHICA')
                ->where(function($q) use ($fechaInicioDia, $fechaFinDia) {
                    $q->whereBetween('FechaRespuesta', [$fechaInicioDia, $fechaFinDia])
                      ->orWhere(function($q2) use ($fechaInicioDia, $fechaFinDia) {
                          $q2->whereNull('FechaRespuesta')->whereBetween('FechaTransferencia', [$fechaInicioDia, $fechaFinDia]);
                      });
                })
                ->sum('Monto');
            $remesasNetCajaChica = $ingresosRemesasCC - $salidasRemesasCC;

            // Devoluciones en efectivo del día (dinero que sale de caja abierta)
            $devolucionesDia = \App\Models\SolicitudResolucionExcedente::withoutGlobalScopes()
                ->where('SedeID', $sedeId)
                ->where('Estado', 'APROBADA')
                ->where('TipoResolucion', 'DEVOLUCION_EFECTIVO')
                ->whereBetween('created_at', [$fechaInicioDia, $fechaFinDia])
                ->sum('MontoAplicar');

            // Totales finales
            $totalCajaAbierta = $saldoInicialCajaAbierta + $totalAmortizaciones - $totalCreditosEmitidos + $remesasNetCajaAbierta + $totalExcedentesDia - $devolucionesDia;
            $totalCajaChica = $saldoInicialCajaChica - ($totalGastos + $totalCompras) + $remesasNetCajaChica;

            // Otras operaciones (para cuadrar el reporte matemáticamente)
            $variacionCaja = $saldoCierreCajaAbierta - $saldoInicialCajaAbierta;
            $operacionesConocidas = $totalAmortizaciones - $totalCreditosEmitidos + $totalInyeccionesDia + $totalExcedentesDia;
            $totalOtrasOperacionesDia = $variacionCaja - $operacionesConocidas;
        }

        $saldoRealAjustado = $saldoCierreCajaAbierta - 150000;
        $saldoInicialReal = $saldoInicialCajaAbierta - 150000;
        $totalCajaAbiertaReal = $saldoInicialReal + $totalAmortizaciones - $totalCreditosEmitidos + $remesasNetCajaAbierta + $totalExcedentesDia - $devolucionesDia;

        $data = [
            'fecha'                 => $fechaCarbon,
            'sedeNombre'            => strtoupper($sedeNombre),
            'emision'               => $ahora,
            // Saldos
            'saldoInicialCajaAbierta' => $saldoInicialCajaAbierta,
            'saldoInicialReal'      => $saldoInicialReal,
            'saldoCajaAbierta'      => $saldoCierreCajaAbierta, // REEMPLAZADO por el saldo de cierre del día, no el live
            'saldoLiveCajaAbierta'  => $saldoCajaAbierta, // Pasamos el live por si acaso
            'saldoRealAjustado'     => $saldoRealAjustado,
            'saldoCajaChica'        => $saldoCajaChica,
            'saldoInicialCajaChica' => $saldoInicialCajaChica,
            'totalIngresosCajaChica' => $totalIngresosCajaChica ?? 0,
            'saldoCuentaAMayor'     => $saldoCuentaAMayor,
            'totalInyeccionesDia'   => $totalInyeccionesDia,
            'totalExcedentesDia'    => $totalExcedentesDia,
            'totalOtrasOperacionesDia' => $totalOtrasOperacionesDia,
            'remesasNetCajaAbierta' => $remesasNetCajaAbierta,
            'remesasNetCajaChica'   => $remesasNetCajaChica,
            'totalCajaAbierta'      => $totalCajaAbiertaReal,
            'totalCajaChica'        => $totalCajaChica,
            'devolucionesDia'       => $devolucionesDia,
            // 1. Caja Chica - Gastos
            'gastos'                => $gastos,
            'totalGastos'           => $totalGastos,
            // 1B. Caja Chica - Compras
            'compras'               => $compras,
            'totalCompras'          => $totalCompras,
            // 2. Ingreso de Remesas
            'ingresosRemesas'       => $ingresosRemesas,
            'totalIngresosRemesas'  => $totalIngresosRemesas,
            // 3. Salida de Remesas
            'salidasRemesas'        => $salidasRemesas,
            'totalSalidasRemesas'   => $totalSalidasRemesas,
            // 4a. Exoneraciones
            'exoneraciones'         => $exoneraciones,
            'totalExoneraciones'    => $totalExoneraciones,
            // 4b. Extornos
            'extornos'              => $extornos,
            'totalExtornos'         => $totalExtornos,
            // 4c. Excedentes del día
            'excedentesDia'         => $excedentesDiaParaTabla,
            // 4d. Amortizaciones
            'pagos'                 => $pagos,
            'totalAmortizaciones'   => $totalAmortizaciones,
            // 5. Créditos Emitidos
            'creditos'              => $creditos,
            'totalCreditosEmitidos' => $totalCreditosEmitidos,
        ];

        $pdf = Pdf::loadView('reportes.reporte-diario', $data);
        $pdf->setPaper('a4', 'portrait');
        $pdf->setOptions([
            'margin-top'    => 20,
            'margin-bottom' => 20,
            'margin-left'   => 20,
            'margin-right'  => 20,
        ]);

        return $pdf->stream('Reporte_Diario_' . $fechaCarbon->format('d-m-Y') . '.pdf');
    }
}
