<?php

namespace App\Observers;

use App\Models\ProposicionCredito;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProposicionCreditoObserver
{
    public function created(ProposicionCredito $proposicionCredito): void
    {
        if (!$proposicionCredito->CodigoCredito) {
            $proposicionCredito->update([
                'CodigoCredito' => ProposicionCredito::generarCodigoCredito(),
            ]);
        }

        DB::transaction(function () use ($proposicionCredito) {
            $proposicionCredito->refresh();
            $proposicionCredito->crearAprobacionesRequeridas();
        }, attempts: 3);

        try {
            $cliente = $proposicionCredito->cliente;
            $nombre = $cliente?->NombresApellidos ?? 'N/A';
            $monto = number_format((float) $proposicionCredito->MontoTotal, 2);
            $codigo = $proposicionCredito->CodigoCredito;

            User::notificarAdmin(
                'Nueva proposición de crédito',
                "{$codigo} — {$nombre} — S/ {$monto}",
                'heroicon-o-document-plus',
                $proposicionCredito->SedeID
            );
        } catch (\Exception $e) {
        }
    }

    /**
     * Handle the ProposicionCredito "updated" event.
     */
    public function updated(ProposicionCredito $proposicionCredito): void
    {
        if ($proposicionCredito->wasChanged('MontoTotal')) {
            DB::transaction(function () use ($proposicionCredito) {
                $proposicionCredito->refresh();
                $proposicionCredito->crearAprobacionesRequeridas();
            }, attempts: 3);
        }
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
