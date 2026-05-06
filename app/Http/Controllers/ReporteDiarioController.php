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
        $sedeIdParam = $request->get('sede');

        if (!$fecha) {
            abort(404, 'Fecha no proporcionada');
        }

        $fechaCarbon = Carbon::createFromFormat('Y-m-d', $fecha);

        // Obtener el registro de apertura/cierre para la sede
        $aperturaCierre = null;
        if ($aperturaCierreDiaId) {
            $aperturaCierre = AperturaCierreDia::withoutGlobalScopes()
                ->find($aperturaCierreDiaId);
        }

        // Resolver SedeID: del registro, del parámetro, o del usuario autenticado
        $sedeId = $aperturaCierre?->SedeID
            ?? $sedeIdParam
            ?? (auth()->check() ? (auth()->user()->esAdmin() ? session('sede_activa') : auth()->user()->SedeID) : null);

        $sede = $sedeId ? Sede::find($sedeId) : null;
        $sedeNombre = $sede?->Nombre ?? 'CHICLAYO';

        // Obtener saldo de Caja Abierta y Caja Chica
        $fondo = $sedeId ? FondoSede::where('SedeID', $sedeId)->first() : null;
        $saldoCajaAbierta = $fondo ? $fondo->Saldo : 0;
        $saldoCajaChica = $fondo ? $fondo->SaldoCajaChica : 0;

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
            ->whereDate('FechaRespuesta', $fecha);

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
            ->whereDate('FechaRespuesta', $fecha);

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
            ->whereDate('FechaAprobacion', $fecha);

        if ($sedeId) {
            $exoneracionesQuery->where('SedeID', $sedeId);
        }

        $exoneraciones = $exoneracionesQuery
            ->with(['credito.proposicion.cliente', 'tipoExoneracion'])
            ->orderBy('SolicitudExoneracionID', 'asc')
            ->get();

        $totalExoneraciones = $exoneraciones->sum('MontoExonerado');

        // ─── 4b. CAJA ABIERTA - EXTORNOS / DEVOLUCIONES (Excedentes resueltos) ───
        $extornosQuery = Excedente::withoutGlobalScopes()
            ->where('Activo', true)
            ->where('EstadoResolucion', 'RESUELTO')
            ->whereDate('Fecha', $fecha);

        if ($sedeId) {
            $extornosQuery->where('SedeID', $sedeId);
        }

        $extornos = $extornosQuery
            ->with(['clienteOrigen', 'zona'])
            ->orderBy('ExcedenteID', 'asc')
            ->get();

        $totalExtornos = $extornos->sum('Monto');

        // ─── 4c. CAJA ABIERTA - AMORTIZACIONES (Pagos) ───
        $pagosQuery = Pago::withoutGlobalScopes()
            ->where('pago.Activo', true)
            ->whereDate('pago.FechaPago', $fecha);

        if ($sedeId) {
            $pagosQuery->where('pago.SedeID', $sedeId);
        }

        $pagos = $pagosQuery
            ->join('Credito', 'pago.CreditoID', '=', 'Credito.CreditoID')
            ->join('ProposicionCredito', 'Credito.ProposicionCreditoID', '=', 'ProposicionCredito.ProposicionCreditoID')
            ->join('Cliente', 'ProposicionCredito.ClienteID', '=', 'Cliente.ClienteID')
            ->join('TipoCredito', 'ProposicionCredito.TipoCreditoID', '=', 'TipoCredito.TipoCreditoID')
            ->select(
                'pago.PagoID',
                'ProposicionCredito.CodigoCredito',
                'TipoCredito.Codigo as TipoCreditoCodigo',
                'Cliente.NombresApellidos',
                'pago.MontoPagado'
            )
            ->orderBy('pago.PagoID', 'asc')
            ->get();

        $totalAmortizaciones = $pagos->sum('MontoPagado');

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
            ->join('TipoCredito', 'ProposicionCredito.TipoCreditoID', '=', 'TipoCredito.TipoCreditoID')
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

        // Calcular datos
        $ahora = Carbon::now();

        $data = [
            'fecha'                 => $fechaCarbon,
            'sedeNombre'            => strtoupper($sedeNombre),
            'emision'               => $ahora,
            // Saldos
            'saldoCajaAbierta'      => $saldoCajaAbierta,
            'saldoCajaChica'        => $saldoCajaChica,
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
            // 4c. Amortizaciones
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
