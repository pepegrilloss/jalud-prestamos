<?php

namespace App\Filament\Components;

use App\Models\AperturaCierreDia;
use Filament\Support\View;

class DiaEstadoWidget
{
    public static function render()
    {
        $estado = AperturaCierreDia::estadoDiaActual();
        $registro = AperturaCierreDia::hoyOHoy();

        return view('filament.components.dia-estado-widget', [
            'estado' => $estado,
            'registro' => $registro,
            'abierto' => AperturaCierreDia::estaAbierto(),
        ]);
    }
}
