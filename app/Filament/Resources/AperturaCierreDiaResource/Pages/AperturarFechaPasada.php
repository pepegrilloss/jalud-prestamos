<?php

namespace App\Filament\Resources\AperturaCierreDiaResource\Pages;

use App\Filament\Resources\AperturaCierreDiaResource;
use App\Models\AperturaCierreDia;
use App\Events\DiaAbierto;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Builder;

class AperturarFechaPasada extends Page
{
    protected static string $resource = AperturaCierreDiaResource::class;
    protected static string $view = 'filament.resources.apertura-cierre-dia-resource.pages.aperturar-fecha-pasada';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Aperturar Fecha Pasada')
                    ->description('Seleccione una fecha pasada para aperturarla y permitir ediciones de registros.')
                    ->schema([
                        Forms\Components\DatePicker::make('fecha')
                            ->label('Fecha a Aperturar')
                            ->required()
                            ->maxDate(now()->subDay())
                            ->native(false)
                            ->helperText('Solo puede seleccionar fechas anteriores a hoy'),

                        Forms\Components\Textarea::make('observaciones')
                            ->label('Observaciones')
                            ->rows(3)
                            ->placeholder('Motivo de la reapertura (opcional)'),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Actions\Action::make('aperturar')
                ->label('Aperturar Fecha')
                ->color('info')
                ->icon('heroicon-o-lock-open')
                ->action('aperturar'),
            Actions\Action::make('cancelar')
                ->label('Cancelar')
                ->color('gray')
                ->url(AperturaCierreDiaResource::getUrl('index'))
                ->button(),
        ];
    }

    public function aperturar(): void
    {
        $data = $this->form->getState();
        $fecha = \Carbon\Carbon::parse($data['fecha']);

        try {
            // Buscar o crear registro para esa fecha
            $registro = AperturaCierreDia::where('Fecha', $fecha->format('Y-m-d'))->first();

            if ($registro) {
                // Si ya existe, actualizar a ABIERTO
                $registro->update([
                    'EstadoDia' => 'ABIERTO',
                    'FechaCierre' => null,
                    'UsuarioCierreID' => null,
                    'FechaApertura' => now(),
                    'UsuarioAperturaID' => auth()->id(),
                    'Observaciones' => $data['observaciones'] ? 
                        ($registro->Observaciones ? $registro->Observaciones . "\n[Reapertura] " . $data['observaciones'] : "[Reapertura] " . $data['observaciones']) 
                        : $registro->Observaciones,
                ]);

                // Disparar evento
                DiaAbierto::dispatch($registro);

                // Reabrir registros del día
                AperturaCierreDiaResource::reabrirDia($registro);

                Notification::make()
                    ->success()
                    ->title('Fecha reabierta')
                    ->body("La fecha {$fecha->format('d/m/Y')} ha sido reabierta. Ahora puede editar registros de ese día.")
                    ->persistent()
                    ->send();
            } else {
                // Crear nuevo registro de apertura
                $nuevoRegistro = AperturaCierreDia::create([
                    'Fecha' => $fecha->format('Y-m-d'),
                    'EstadoDia' => 'ABIERTO',
                    'FechaApertura' => now(),
                    'UsuarioAperturaID' => auth()->id(),
                    'Observaciones' => $data['observaciones'] ? "[Apertura de Fecha Pasada] " . $data['observaciones'] : '[Apertura de Fecha Pasada]',
                ]);

                // Disparar evento
                DiaAbierto::dispatch($nuevoRegistro);

                Notification::make()
                    ->success()
                    ->title('Fecha aperturada')
                    ->body("La fecha {$fecha->format('d/m/Y')} ha sido aperturada correctamente. Ahora puede crear registros de ese día.")
                    ->persistent()
                    ->send();
            }

            $this->redirect(AperturaCierreDiaResource::getUrl('index'));
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('No se pudo aperturar la fecha: ' . $e->getMessage())
                ->persistent()
                ->send();
        }
    }
}
