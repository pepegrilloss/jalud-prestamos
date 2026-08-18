<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Credito;
use App\Models\Pago;
use App\Models\ProposicionCredito;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RefinanciamientoPagoService
{
    private const TOLERANCIA = 0.009;

    /**
     * Separa el monto refinanciado entre el saldo que cancela la deuda anterior
     * y la diferencia que debe conservarse como pago a mayor.
     */
    public static function calcularDistribucion(float $montoRefinanciamiento, float $saldoAnterior): array
    {
        $monto = round(max(0, $montoRefinanciamiento), 2);
        $saldo = round(max(0, $saldoAnterior), 2);
        $aplicado = min($monto, $saldo);

        return [
            'monto_refinanciamiento' => $monto,
            'saldo_aplicado' => $aplicado,
            'pago_a_mayor' => round(max(0, $monto - $aplicado), 2),
        ];
    }

    /**
     * Registra los pagos internos del refinanciamiento. Es idempotente por
     * proposicion nueva y también permite reparar refinanciamientos históricos.
     */
    public function registrar(ProposicionCredito $nueva, Credito $creditoNuevo): array
    {
        return DB::transaction(function () use ($nueva, $creditoNuevo) {
            $anterior = ProposicionCredito::withoutGlobalScope('sede')
                ->whereKey($nueva->ProposicionCreditoAnteriorID)
                ->where('SedeID', $nueva->SedeID)
                ->lockForUpdate()
                ->firstOrFail();

            $creditoAnterior = Credito::withoutGlobalScope('sede')
                ->where('ProposicionCreditoID', $anterior->ProposicionCreditoID)
                ->where('SedeID', $nueva->SedeID)
                ->where('Activo', true)
                ->lockForUpdate()
                ->firstOrFail();

            app(SedeIntegrityService::class)->assertRefinanciamientoConsistente(
                $nueva,
                $anterior,
                $creditoAnterior
            );
            app(SedeIntegrityService::class)->assertRecordSede(
                $creditoNuevo,
                (int) $nueva->SedeID,
                'credito nuevo'
            );

            $pagosVinculados = Pago::withoutGlobalScope('sede')
                ->where('CreditoID', $creditoAnterior->CreditoID)
                ->where('Activo', true)
                ->where('EsPagoAutomatico', true)
                ->where('Comentario', 'like', '%Proposición #'.$nueva->ProposicionCreditoID.'%')
                ->lockForUpdate()
                ->get();

            $pagoBaseExistente = $pagosVinculados->firstWhere('EsPagoAMayor', false);
            $saldoBase = $pagoBaseExistente
                ? (float) $pagosVinculados->where('EsPagoAMayor', false)->sum('MontoPagado')
                : (float) $anterior->SaldoPendiente;

            if (! $pagoBaseExistente && (float) $nueva->MontoTotal + self::TOLERANCIA < $saldoBase) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'MontoTotal' => 'El monto refinanciado (S/ '.number_format((float) $nueva->MontoTotal, 2)
                        .') no puede ser menor que el saldo del crédito anterior (S/ '
                        .number_format($saldoBase, 2).').',
                ]);
            }

            $distribucion = self::calcularDistribucion((float) $nueva->MontoTotal, $saldoBase);
            $fechaPago = $pagoBaseExistente?->FechaPago
                ?? DateFieldResolver::getFechaAbierta()?->copy()->setTime(now()->hour, now()->minute, now()->second)
                ?? now();

            $cliente = Cliente::withoutGlobalScope('sede')->find($nueva->ClienteID);
            $promotorId = $pagoBaseExistente?->PromotorCobradorID
                ?? $cliente?->PromotorCobradorID
                ?? Auth::user()?->PromotorCobradorID;
            $usuarioRegistro = $pagoBaseExistente?->UsuarioRegistro ?? Auth::user()?->name ?? 'Sistema';

            $pagoBaseCreado = null;
            if (! $pagoBaseExistente && $distribucion['saldo_aplicado'] > self::TOLERANCIA) {
                $pagoBaseCreado = Pago::create([
                    'CreditoID' => $creditoAnterior->CreditoID,
                    'CuotaID' => null,
                    'PromotorCobradorID' => $promotorId,
                    'MontoPagado' => $distribucion['saldo_aplicado'],
                    'FechaPago' => $fechaPago,
                    'SedeID' => $creditoAnterior->SedeID,
                    'TipoConcepto' => 'C',
                    'EsMora' => false,
                    'EsPagoAMayor' => false,
                    'EsPagoAMayorPorMora' => false,
                    'EsPagoForzado' => false,
                    'EsPagoAutomatico' => true,
                    'Comentario' => 'Pago automático por refinanciamiento. Proposición #'.$nueva->ProposicionCreditoID
                        .'. Saldo aplicado: S/ '.number_format($distribucion['saldo_aplicado'], 2, '.', ''),
                    'UsuarioRegistro' => $usuarioRegistro,
                    'Activo' => true,
                ]);
            }

            $pagoMayorExistente = round((float) $pagosVinculados
                ->where('EsPagoAMayor', true)
                ->sum('MontoPagado'), 2);
            $pagoMayorFaltante = round(max(0, $distribucion['pago_a_mayor'] - $pagoMayorExistente), 2);
            $pagoMayorCreado = null;

            if ($pagoMayorFaltante > self::TOLERANCIA) {
                $pagoMayorCreado = Pago::create([
                    'CreditoID' => $creditoAnterior->CreditoID,
                    'CuotaID' => null,
                    'PromotorCobradorID' => $promotorId,
                    'MontoPagado' => $pagoMayorFaltante,
                    'FechaPago' => $fechaPago,
                    'SedeID' => $creditoAnterior->SedeID,
                    'TipoConcepto' => 'C',
                    'EsMora' => false,
                    'EsPagoAMayor' => true,
                    'EsPagoAMayorPorMora' => false,
                    'EsPagoForzado' => false,
                    'EsPagoAutomatico' => true,
                    'Comentario' => 'Pago a mayor automático por refinanciamiento. Proposición #'.$nueva->ProposicionCreditoID
                        .'. Monto refinanciado: S/ '.number_format($distribucion['monto_refinanciamiento'], 2, '.', '')
                        .'. Saldo aplicado: S/ '.number_format($distribucion['saldo_aplicado'], 2, '.', '')
                        .'. Diferencia: S/ '.number_format($pagoMayorFaltante, 2, '.', ''),
                    'UsuarioRegistro' => $usuarioRegistro,
                    'Activo' => true,
                ]);

                \App\Models\Log::registrar(
                    'CREAR',
                    'PagoMayorRefinanciamiento',
                    $pagoMayorCreado->PagoID,
                    null,
                    [
                        'CreditoAnteriorID' => $creditoAnterior->CreditoID,
                        'ProposicionNuevaID' => $nueva->ProposicionCreditoID,
                        'Monto' => $pagoMayorFaltante,
                    ],
                    (int) $nueva->SedeID
                );
            }

            $fechaSaldamiento = $fechaPago;
            $creditoAnterior->cuotas()
                ->where('Activo', true)
                ->whereIn('Estado', ['PENDIENTE', 'VENCIDA', 'MORA', 'NORMAL'])
                ->update(['Estado' => 'PAGADO', 'FechaPago' => $fechaPago]);

            $anterior->update(['FueRefinanciada' => true]);
            $creditoAnterior->update([
                'EstatusCreditoFinal' => 'SALDADO',
                'FechaSaldamiento' => $fechaSaldamiento,
            ]);

            return $distribucion + [
                'pago_base_id' => $pagoBaseExistente?->PagoID ?? $pagoBaseCreado?->PagoID,
                'pago_a_mayor_id' => $pagoMayorCreado?->PagoID,
                'pago_a_mayor_creado' => $pagoMayorFaltante,
            ];
        }, 3);
    }
}
