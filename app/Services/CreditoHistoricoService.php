<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Credito;
use App\Models\Log;
use App\Models\ProposicionCredito;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreditoHistoricoService
{
    public function registrar(int $proposicionId, array $datos, int $usuarioId, int $sedeId): Credito
    {
        Validator::make($datos, [
            'TipoPagoID' => ['required', 'integer', Rule::exists('TipoPago', 'TipoPagoID')->where('Activo', true)],
            'FechaGeneracionHistorica' => ['required', 'date', 'before_or_equal:today'],
            'FechaSaldamientoHistorica' => [
                'required',
                'date',
                'after_or_equal:FechaGeneracionHistorica',
                'before_or_equal:today',
            ],
            'ComentarioGeneracion' => ['nullable', 'string', 'max:3000'],
        ])->validate();

        if ($usuarioId < 1 || $sedeId < 1) {
            throw ValidationException::withMessages([
                'SedeID' => 'Selecciona una sede activa para registrar el crédito histórico.',
            ]);
        }

        $fechaGeneracion = Carbon::parse($datos['FechaGeneracionHistorica'])->startOfDay();
        $fechaSaldamiento = Carbon::parse($datos['FechaSaldamientoHistorica'])->startOfDay();

        return DB::transaction(function () use (
            $proposicionId,
            $datos,
            $usuarioId,
            $sedeId,
            $fechaGeneracion,
            $fechaSaldamiento,
        ): Credito {
            $proposicion = ProposicionCredito::withoutGlobalScopes()
                ->where('ProposicionCreditoID', $proposicionId)
                ->lockForUpdate()
                ->first();

            if (! $proposicion
                || ! $proposicion->Activo
                || $proposicion->Eliminado
                || $proposicion->FechaCierre !== null
                || $proposicion->Estado !== 'APROBADO') {
                throw ValidationException::withMessages([
                    'ProposicionCreditoID' => 'Solo se pueden registrar como históricos los préstamos aprobados y vigentes.',
                ]);
            }

            $integridadSede = app(SedeIntegrityService::class);
            $integridadSede->assertRecordSede($proposicion, $sedeId, 'proposición de crédito');
            $integridadSede->assertIdSede(
                Cliente::class,
                'ClienteID',
                (int) $proposicion->ClienteID,
                $sedeId,
                'cliente',
            );

            $creditoExistente = Credito::withoutGlobalScopes()
                ->where('ProposicionCreditoID', $proposicionId)
                ->lockForUpdate()
                ->exists();

            if ($creditoExistente) {
                throw ValidationException::withMessages([
                    'ProposicionCreditoID' => 'Esta proposición ya tiene un crédito registrado.',
                ]);
            }

            $comentarioBase = 'Registro histórico: préstamo originado y pagado fuera de JALUD. Fecha real de cancelación: '
                .$fechaSaldamiento->format('d/m/Y').'.';
            $comentarioAdicional = trim((string) ($datos['ComentarioGeneracion'] ?? ''));

            $credito = Credito::create([
                'ProposicionCreditoID' => $proposicionId,
                'TipoPagoID' => (int) $datos['TipoPagoID'],
                'ComentarioGeneracion' => $comentarioBase.($comentarioAdicional !== '' ? "\n".$comentarioAdicional : ''),
                'FechaGeneracion' => $fechaGeneracion,
                'UserGeneracionID' => $usuarioId,
                'Activo' => true,
                'EstatusCreditoFinal' => 'SALDADO',
                'FechaSaldamiento' => $fechaSaldamiento,
                'EsMigracionHistorica' => true,
                'SedeID' => $sedeId,
            ]);

            DB::table('ProposicionCredito')
                ->where('ProposicionCreditoID', $proposicionId)
                ->update(['SaldoPendiente' => 0]);

            Log::registrar(
                'REGISTRAR_CREDITO_HISTORICO_SALDADO',
                'Credito',
                $credito->CreditoID,
                null,
                [
                    'ProposicionCreditoID' => $proposicionId,
                    'CodigoCredito' => $proposicion->CodigoCredito,
                    'FechaGeneracion' => $fechaGeneracion->toDateString(),
                    'FechaSaldamiento' => $fechaSaldamiento->toDateString(),
                    'EsMigracionHistorica' => true,
                ],
                $sedeId,
                $usuarioId,
            );

            return $credito;
        });
    }
}
