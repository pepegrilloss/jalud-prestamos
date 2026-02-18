<?php

namespace App\Events;

use App\Models\AperturaCierreDia;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * DiaAbierto
 * 
 * Evento que se dispara cuando se abre un nuevo día en el sistema.
 * Esto desencadena el cálculo automático de mora.
 */
class DiaAbierto
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $aperturaCierre;

    public function __construct(AperturaCierreDia $aperturaCierre)
    {
        $this->aperturaCierre = $aperturaCierre;
    }
}
