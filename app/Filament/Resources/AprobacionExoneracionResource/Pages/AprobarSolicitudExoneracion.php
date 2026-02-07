<?php

namespace App\Filament\Resources\AprobacionExoneracionResource\Pages;

use App\Filament\Resources\AprobacionExoneracionResource;
use App\Models\AprobacionExoneracion;
use App\Models\SolicitudExoneracion;
use App\Services\DateFieldResolver;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\Page;
use Filament\Actions;

class AprobarSolicitudExoneracion extends Page
{
    protected static string $resource = AprobacionExoneracionResource::class;
    protected static string $view = 'filament.resources.aprobacion-exoneracion-resource.pages.aprobar-solicitud-exoneracion';

    public SolicitudExoneracion $record;

    public function mount($record): void
    {
        $this->record = SolicitudExoneracion::with([
            'credito.proposicion.cliente',
            'tipoExoneracion'
        ])->findOrFail($record);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Card::make()
                ->schema([
                    Forms\Components\Section::make('Información del Crédito')
                        ->columns(2)
                        ->schema([
                            Forms\Components\TextInput::make('credito.proposicion.CodigoCredito')
                                ->label('Código Crédito')
                                ->disabled()
                                ->default($this->record->credito?->proposicion?->CodigoCredito),
                            Forms\Components\TextInput::make('credito.proposicion.cliente.NombresApellidos')
                                ->label('Cliente')
                                ->disabled()
                                ->default($this->record->credito?->proposicion?->cliente?->NombresApellidos),
                            Forms\Components\TextInput::make('credito.proposicion.MontoTotal')
                                ->label('Monto')
                                ->disabled()
                                ->default($this->record->credito?->proposicion?->MontoTotal),
                            Forms\Components\TextInput::make('credito.proposicion.SaldoPendiente')
                                ->label('Saldo Pendiente')
                                ->disabled()
                                ->default($this->record->credito?->proposicion?->SaldoPendiente),
                        ]),
                    Forms\Components\Section::make('Información de Exoneración')
                        ->columns(2)
                        ->schema([
                            Forms\Components\TextInput::make('tipoExoneracion.Nombre')
                                ->label('Tipo de Exoneración')
                                ->disabled()
                                ->default($this->record->tipoExoneracion?->Nombre),
                            Forms\Components\TextInput::make('MontoDisponible')
                                ->label('Monto Disponible')
                                ->disabled()
                                ->default($this->record->MontoDisponible),
                            Forms\Components\TextInput::make('MontoExonerado')
                                ->label('Monto Exonerado')
                                ->disabled()
                                ->default($this->record->MontoExonerado),
                        ]),
                    Forms\Components\Section::make('Aprobación')
                        ->columns(1)
                        ->schema([
                            Forms\Components\Select::make('Estado')
                                ->label('Estado')
                                ->options([
                                    'APROBADO' => 'Aprobar',
                                    'RECHAZADO' => 'Rechazar',
                                ])
                                ->required()
                                ->placeholder('Seleccione una opción'),
                            Forms\Components\Textarea::make('Comentario')
                                ->label('Comentario de Aprobación')
                                ->required()
                                ->placeholder('Escriba el motivo de la decisión'),
                        ]),
                ]),
        ])->statePath('data');
    }

    public function apruebaAction(): void
    {
        $data = $this->form->getState();

        // Obtener la fecha del día abierto
        $fechaAbierta = DateFieldResolver::getFechaAbierta();
        $fechaAprobacion = $fechaAbierta 
            ? $fechaAbierta->copy()->setTime(now()->hour, now()->minute, now()->second) 
            : now();

        // Crear registro de aprobación en AprobacionExoneracion
        $aprobacion = new AprobacionExoneracion();
        $aprobacion->SolicitudExoneracionID = $this->record->SolicitudExoneracionID;
        $aprobacion->NivelAprobacionID = 3; // Gerencia
        $aprobacion->UserAprobadorID = auth()->id();
        $aprobacion->Estado = $data['Estado'];
        $aprobacion->Comentario = $data['Comentario'];
        $aprobacion->FechaAprobacion = $fechaAprobacion;
        $aprobacion->save();

        // Actualizar el estado de la solicitud
        $this->record->update([
            'Estado' => $data['Estado'],
            'FechaModificacion' => $fechaAprobacion,
        ]);

        \Filament\Notifications\Notification::make()
            ->title('Solicitud ' . ($data['Estado'] === 'APROBADO' ? 'aprobada' : 'rechazada'))
            ->body('La solicitud de exoneración ha sido ' . strtolower($data['Estado']) . ' correctamente')
            ->success()
            ->send();

        $this->redirect(AprobacionExoneracionResource::getUrl('index'));
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('aprobar')
                ->label('Confirmar decisión')
                ->color('success')
                ->icon('heroicon-o-check')
                ->action('apruebaAction'),
            Actions\Action::make('cancelar')
                ->label('Cancelar')
                ->color('gray')
                ->icon('heroicon-o-x-mark')
                ->action(fn() => $this->redirect(AprobacionExoneracionResource::getUrl('index'))),
        ];
    }
}
