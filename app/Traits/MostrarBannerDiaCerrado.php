<?php

namespace App\Traits;

use App\Models\AperturaCierreDia;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Illuminate\View\View;

trait MostrarBannerDiaCerrado
{
    /**
     * Mostrar banner de día cerrado en el header
     */
    protected function getHeader(): ?View
    {
        return null; // Usaremos getSubheader en su lugar
    }

    protected function getHeaderWidgets(): array
    {
        $widgets = parent::getHeaderWidgets() ?? [];
        
        // Si el día está cerrado, agregamos widget de advertencia al inicio
        if (!AperturaCierreDia::estaAbierto()) {
            $widgets = array_merge([
                'dia-cerrado' => fn() => view('filament.components.dia-cerrado-banner'),
            ], $widgets);
        }
        
        return $widgets;
    }
}
