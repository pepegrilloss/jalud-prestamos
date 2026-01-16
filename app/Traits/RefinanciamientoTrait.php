<?php

namespace App\Traits;

use App\Models\ProposicionCredito;
use App\Models\Cuota;

trait RefinanciamientoTrait
{
    public static function marcarComoRefinanciada($proposicionCreditoRefinanciadaID, $nuevaProposicionID)
    {
        $proposicionAnterior = ProposicionCredito::find($proposicionCreditoRefinanciadaID);
        
        if (!$proposicionAnterior || !$proposicionAnterior->credito) {
            return false;
        }

        $cuotas = Cuota::where('CreditoID', $proposicionAnterior->credito->CreditoID)
            ->where('Activo', true)
            ->where('Estado', '!=', 'PAGADA')
            ->get();

        foreach ($cuotas as $cuota) {
            $cuota->update([
                'Estado' => 'PAGADA',
                'FechaPago' => now(),
                'MontoPagado' => $cuota->SaldoPendiente,
                'SaldoPendiente' => 0,
                'FechaModificacion' => now()
            ]);
        }

        $proposicionAnterior->update([
            'Estado' => 'CANCELADO',
            'FechaModificacion' => now(),
            'UserModificacionID' => auth()->id()
        ]);

        return true;
    }
}
