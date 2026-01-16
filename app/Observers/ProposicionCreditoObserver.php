<?php

namespace App\Observers;

use App\Models\ProposicionCredito;
use Illuminate\Support\Facades\DB;

class ProposicionCreditoObserver
{
    /**
     * Handle the ProposicionCredito "created" event.
     */
    public function created(ProposicionCredito $proposicionCredito): void
    {
        // Generar código si no existe
        if (!$proposicionCredito->CodigoCredito) {
            $proposicionCredito->update([
                'CodigoCredito' => ProposicionCredito::generarCodigoCredito(),
            ]);
        }

        // Usar afterCommit para asegurar que se ejecute DESPUÉS de que se guarde en BD
        DB::transaction(function () use ($proposicionCredito) {
            // Forzar refresh para asegurar que el ID está disponible en BD
            $proposicionCredito->refresh();
            
            // Crear las aprobaciones requeridas
            $proposicionCredito->crearAprobacionesRequeridas();
            
            // Si es un refinanciamiento, desactivar y marcar la proposición anterior
            if ($proposicionCredito->EsRefinanciamiento && $proposicionCredito->ProposicionCreditoAnteriorID) {
                $proposicionCredito->desactivarProposicionRefinanciada();
            }
        }, attempts: 3);
    }

    /**
     * Handle the ProposicionCredito "updated" event.
     */
    public function updated(ProposicionCredito $proposicionCredito): void
    {
        // Sin lógica adicional en actualización
    }

    /**
     * Handle the ProposicionCredito "deleted" event.
     */
    public function deleted(ProposicionCredito $proposicionCredito): void
    {
        //
    }

    /**
     * Handle the ProposicionCredito "restored" event.
     */
    public function restored(ProposicionCredito $proposicionCredito): void
    {
        //
    }

    /**
     * Handle the ProposicionCredito "force deleted" event.
     */
    public function forceDeleted(ProposicionCredito $proposicionCredito): void
    {
        //
    }
}
