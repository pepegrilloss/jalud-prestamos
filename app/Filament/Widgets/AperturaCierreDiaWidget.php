<?php

namespace App\Filament\Widgets;

use App\Models\AperturaCierreDia;
use Filament\Widgets\Widget;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class AperturaCierreDiaWidget extends Widget
{
    protected static string $view = 'filament.widgets.apertura-cierre-dia-widget';
    protected static ?int $sort = 1;

    public ?AperturaCierreDia $hoy = null;

    public function mount(): void
    {
        $this->hoy = AperturaCierreDia::whereDate('Fecha', today())->first();
    }

    public function aperturar(): void
    {
        if ($this->hoy && $this->hoy->EstadoDia === 'ABIERTO') {
            Notification::make()
                ->title('Día ya abierto')
                ->body('El día ya está aperturado.')
                ->warning()
                ->send();
            return;
        }

        if ($this->hoy) {
            $this->hoy->update([
                'EstadoDia' => 'ABIERTO',
                'FechaApertura' => now(),
                'UsuarioAperturaID' => auth()->id(),
            ]);
        } else {
            AperturaCierreDia::create([
                'Fecha' => today(),
                'EstadoDia' => 'ABIERTO',
                'FechaApertura' => now(),
                'UsuarioAperturaID' => auth()->id(),
            ]);
        }

        $this->hoy = AperturaCierreDia::whereDate('Fecha', today())->first();

        Notification::make()
            ->title('✅ Día aperturado')
            ->body('El día de operaciones ha sido aperturado correctamente.')
            ->success()
            ->send();

        $this->dispatch('refresh');
    }

    public function cerrar(): void
    {
        if (!$this->hoy || $this->hoy->EstadoDia === 'CERRADO') {
            Notification::make()
                ->title('Día no abierto')
                ->body('No hay un día abierto para cerrar.')
                ->warning()
                ->send();
            return;
        }

        $this->hoy->update([
            'EstadoDia' => 'CERRADO',
            'FechaCierre' => now(),
            'UsuarioCierreID' => auth()->id(),
        ]);

        $this->hoy = AperturaCierreDia::whereDate('Fecha', today())->first();

        Notification::make()
            ->title('❌ Día cerrado')
            ->body('El día de operaciones ha sido cerrado correctamente.')
            ->success()
            ->send();

        $this->dispatch('refresh');
    }

    public function getEstado(): string
    {
        if (!$this->hoy) {
            return 'Sin registro';
        }
        return $this->hoy->EstadoDia;
    }

    public function getHoy()
    {
        return $this->hoy;
    }
}
