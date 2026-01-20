<?php

namespace App\Filament\Widgets;

use App\Models\AperturaCierreDia;
use Filament\Widgets\Widget;

class DiaCerradoBanner extends Widget
{
    protected static string $view = 'filament.widgets.dia-cerrado-banner';

    protected static bool $isLazy = false;

    public function getViewData(): array
    {
        return [
            'estaCerrado' => !AperturaCierreDia::estaAbierto(),
        ];
    }
}
