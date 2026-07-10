<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Credito;
use App\Models\Cuota;
use App\Models\Excedente;
use App\Models\Pago;
use App\Models\ProposicionCredito;
use App\Models\SolicitudResolucionExcedente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class SedeIntegrityService
{
    public function assertPagoConsistente(
        ?int $creditoId,
        ?int $cuotaId,
        ?int $clienteId,
        ?int $promotorCobradorId,
        ?int $sedeId
    ): void {
        if (!$creditoId) {
            $this->fail('El pago no tiene credito asignado.');
        }

        $credito = $this->find(Credito::class, 'CreditoID', $creditoId, 'credito');
        $expectedSedeId = $sedeId ?: (int) $credito->SedeID;

        $this->assertRecordSede($credito, $expectedSedeId, 'credito');

        $proposicion = ProposicionCredito::withoutGlobalScope('sede')
            ->where('ProposicionCreditoID', $credito->ProposicionCreditoID)
            ->first();
        $this->assertRecordSede($proposicion, $expectedSedeId, 'proposicion del credito');

        if ($clienteId) {
            $this->assertIdSede(Cliente::class, 'ClienteID', $clienteId, $expectedSedeId, 'cliente');
            if ($proposicion && (int) $proposicion->ClienteID !== (int) $clienteId) {
                $this->fail('El cliente seleccionado no corresponde al credito del pago.');
            }
        }

        if ($cuotaId) {
            $cuota = $this->find(Cuota::class, 'CuotaID', $cuotaId, 'cuota');
            $this->assertRecordSede($cuota, $expectedSedeId, 'cuota');
            if ((int) $cuota->CreditoID !== (int) $creditoId) {
                $this->fail('La cuota seleccionada no corresponde al credito del pago.');
            }
        }

        if ($promotorCobradorId) {
            $this->assertIdSede(
                \App\Models\PromotorCobrador::class,
                'PromotorCobradorID',
                $promotorCobradorId,
                $expectedSedeId,
                'promotor cobrador'
            );
        }
    }

    public function assertSolicitudResolucionConsistente(SolicitudResolucionExcedente $solicitud): void
    {
        $sedeId = (int) $solicitud->SedeID;

        if (!$sedeId) {
            $this->fail('La solicitud no tiene sede asignada.');
        }

        if ($solicitud->ClienteOrigenID) {
            $this->assertIdSede(Cliente::class, 'ClienteID', $solicitud->ClienteOrigenID, $sedeId, 'cliente origen');
        }

        if ($solicitud->ClienteDestinoID) {
            $this->assertIdSede(Cliente::class, 'ClienteID', $solicitud->ClienteDestinoID, $sedeId, 'cliente destino');
        }

        if ($solicitud->ExcedenteID) {
            $this->assertIdSede(Excedente::class, 'ExcedenteID', $solicitud->ExcedenteID, $sedeId, 'excedente');
        }

        if ($solicitud->CreditoOrigenID) {
            $creditoOrigen = $this->find(Credito::class, 'CreditoID', $solicitud->CreditoOrigenID, 'credito origen');
            $this->assertRecordSede($creditoOrigen, $sedeId, 'credito origen');
        }

        if ($solicitud->PagoOrigenID) {
            $pagoOrigen = $this->find(Pago::class, 'PagoID', $solicitud->PagoOrigenID, 'pago origen');
            $this->assertRecordSede($pagoOrigen, $sedeId, 'pago origen');

            if ($solicitud->CreditoOrigenID && (int) $pagoOrigen->CreditoID !== (int) $solicitud->CreditoOrigenID) {
                $this->fail('El pago origen no pertenece al credito origen seleccionado.');
            }
        }

        if ($solicitud->CreditoDestinoID) {
            $creditoDestino = $this->find(Credito::class, 'CreditoID', $solicitud->CreditoDestinoID, 'credito destino');
            $this->assertRecordSede($creditoDestino, $sedeId, 'credito destino');
        }
    }

    public function assertRefinanciamientoConsistente(ProposicionCredito $nueva, ProposicionCredito $anterior, ?Credito $creditoAnterior = null): void
    {
        if ((int) $nueva->SedeID !== (int) $anterior->SedeID) {
            $this->fail('La proposicion refinanciada pertenece a otra sede.');
        }

        if ((int) $nueva->ClienteID !== (int) $anterior->ClienteID) {
            $this->fail('La proposicion refinanciada pertenece a otro cliente.');
        }

        if ($creditoAnterior) {
            $this->assertRecordSede($creditoAnterior, (int) $nueva->SedeID, 'credito anterior');
        }
    }

    public function assertIdSede(string $modelClass, string $keyName, int $id, int $expectedSedeId, string $label): Model
    {
        $record = $this->find($modelClass, $keyName, $id, $label);
        $this->assertRecordSede($record, $expectedSedeId, $label);

        return $record;
    }

    public function assertRecordSede(?Model $record, int $expectedSedeId, string $label): void
    {
        if (!$record) {
            $this->fail("No se encontro {$label}.");
        }

        if (!isset($record->SedeID)) {
            $this->fail("{$label} no tiene SedeID para validar integridad.");
        }

        if ((int) $record->SedeID !== (int) $expectedSedeId) {
            $this->fail("Cruce de sede bloqueado: {$label} pertenece a otra sede.");
        }
    }

    private function find(string $modelClass, string $keyName, int $id, string $label): ?Model
    {
        return $modelClass::withoutGlobalScope('sede')
            ->where($keyName, $id)
            ->first()
            ?? $this->fail("No se encontro {$label}.");
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages([
            'SedeID' => $message,
        ]);
    }
}
