<?php

namespace App\Filament\Resources\AperturaCierreDiaResource\Pages;

use App\Filament\Resources\AperturaCierreDiaResource;
use App\Models\AperturaCierreDia;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Carbon\Carbon;

class GestionarAperturaCierre extends Page
{
    protected static string $resource = AperturaCierreDiaResource::class;
    protected static string $view = 'filament.resources.apertura-cierre-dia-resource.pages.gestionar-apertura-cierre';

    public ?AperturaCierreDia $hoy = null;

    public function mount(): void
    {
        $this->hoy = AperturaCierreDia::whereDate('Fecha', today())->first();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('aperturar')
                ->label('Aperturar Hoy')
                ->color('success')
                ->icon('heroicon-o-lock-open')
                ->action(function () {
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
                        ->title('Día aperturado')
                        ->body('El día de operaciones ha sido aperturado correctamente.')
                        ->success()
                        ->send();
                })
                ->visible(fn() => !$this->hoy || $this->hoy->EstadoDia !== 'ABIERTO'),

            Action::make('cerrar')
                ->label('Cerrar Hoy')
                ->color('danger')
                ->icon('heroicon-o-lock-closed')
                ->action(function () {
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
                        ->title('Día cerrado')
                        ->body('El día de operaciones ha sido cerrado correctamente.')
                        ->success()
                        ->send();
                })
                ->visible(fn() => $this->hoy && $this->hoy->EstadoDia === 'ABIERTO'),
        ];
    }

    public function getTitle(): string
    {
        return 'Gestionar Apertura/Cierre del Día';
    }

    public function getEstado(): string
    {
        if (!$this->hoy) {
            return 'Sin registro';
        }

        $estado = $this->hoy->EstadoDia === 'ABIERTO' ? '✅ ABIERTO' : '❌ CERRADO';
        $fecha = $this->hoy->Fecha->format('d/m/Y');
        $hora = $this->hoy->EstadoDia === 'ABIERTO' 
            ? $this->hoy->FechaApertura?->format('H:i:s')
            : $this->hoy->FechaCierre?->format('H:i:s');

        return "$estado - $fecha $hora";
    }
}
