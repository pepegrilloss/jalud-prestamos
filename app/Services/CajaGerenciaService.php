<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\CuentaTesoreria;
use App\Models\MovimientoTesoreria;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CajaGerenciaService
{
    public function pagarFacturaPendiente(Compra $compra, string|int $origen, int $usuarioId): Compra
    {
        return DB::transaction(function () use ($compra, $origen, $usuarioId): Compra {
            $compraBloqueada = Compra::withoutGlobalScope('sede')
                ->whereKey($compra->CompraID)
                ->where('SedeID', $compra->SedeID)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $compraBloqueada->Activo
                || $compraBloqueada->TipoCompra !== 'CREDITO'
                || $compraBloqueada->EstadoPago !== 'PENDIENTE') {
                throw ValidationException::withMessages([
                    'factura' => 'La factura ya no se encuentra pendiente de pago.',
                ]);
            }

            $fechaPago = DateFieldResolver::getFechaAbierta() ?? now();
            $tesoreria = app(TesoreriaGerenciaService::class);

            $tesoreria->registrarEgresoDocumento([
                'Origen' => $origen,
                'Monto' => $compraBloqueada->Total,
                'FechaContable' => $fechaPago->toDateString(),
                'TipoDocumento' => MovimientoTesoreria::COMPRA,
                'DocumentoID' => $compraBloqueada->CompraID,
                'Concepto' => "Pago de compra al crédito #{$compraBloqueada->CompraID}",
                'Observaciones' => $compraBloqueada->Observaciones,
            ], $usuarioId);

            $compraBloqueada->update(array_merge(
                [
                    'EstadoPago' => 'PAGADO',
                    'FechaPago' => $fechaPago,
                    'UsuarioPagoID' => $usuarioId,
                ],
                $tesoreria->descomponerReferencia($origen)
            ));

            return $compraBloqueada->fresh();
        });
    }

    public function actualizarCuentaBancaria(CuentaTesoreria $cuenta, array $data): CuentaTesoreria
    {
        return DB::transaction(function () use ($cuenta, $data): CuentaTesoreria {
            $cuentaBloqueada = CuentaTesoreria::lockForUpdate()
                ->findOrFail($cuenta->CuentaTesoreriaID);

            if (($data['Estado'] ?? $cuentaBloqueada->Estado) === CuentaTesoreria::ESTADO_INACTIVA
                && round((float) $cuentaBloqueada->SaldoActual, 2) !== 0.0) {
                throw ValidationException::withMessages([
                    'Estado' => 'No se puede inactivar una cuenta con saldo distinto de cero.',
                ]);
            }

            $cuentaBloqueada->fill([
                'Banco' => trim($data['Banco']),
                'NumeroCuenta' => trim($data['NumeroCuenta']),
                'Estado' => $data['Estado'],
            ]);
            $cuentaBloqueada->save();

            return $cuentaBloqueada;
        });
    }
}
