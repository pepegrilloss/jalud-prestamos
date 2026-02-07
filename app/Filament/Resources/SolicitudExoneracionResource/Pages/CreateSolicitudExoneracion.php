<?php

namespace App\Filament\Resources\SolicitudExoneracionResource\Pages;

use App\Filament\Resources\SolicitudExoneracionResource;
use App\Models\SolicitudExoneracion;
use App\Models\Credito;
use App\Models\TipoExoneracion;
use App\Services\ExoneracionService;
use App\Services\DateFieldResolver;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Model;

class CreateSolicitudExoneracion extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = SolicitudExoneracionResource::class;
    protected static string $view = 'filament.pages.create-solicitud-exoneracion';
    
    public ?Model $record = null;
    public Credito $credito;
    public ?array $data = [];

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function mount(): void
    {
        $creditoID = request()->query('CreditoID');
        
        if (!$creditoID) {
            abort(404, 'Crédito no encontrado');
        }

        $this->credito = Credito::with('proposicion.cliente', 'proposicion.tipoCredito', 'proposicion.zona')
            ->findOrFail($creditoID);

        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Card::make()
                    ->schema([
                        Forms\Components\Section::make('Información del Crédito')
                            ->columns(3)
                            ->schema([
                                Forms\Components\TextInput::make('credito_codigo')
                                    ->label('Código Crédito')
                                    ->default($this->credito->proposicion?->CodigoCredito)
                                    ->disabled(),
                                Forms\Components\TextInput::make('credito_cliente')
                                    ->label('Cliente')
                                    ->default($this->credito->proposicion?->cliente?->NombresApellidos)
                                    ->disabled()
                                    ->columnSpan(2),
                                Forms\Components\TextInput::make('credito_monto')
                                    ->label('Monto')
                                    ->default($this->credito->proposicion?->MontoTotal)
                                    ->disabled(),
                                Forms\Components\TextInput::make('credito_monto_interes')
                                    ->label('Monto + Interés')
                                    ->default($this->credito->proposicion?->MontoTotalPagar)
                                    ->disabled(),
                                Forms\Components\TextInput::make('credito_saldo')
                                    ->label('Saldo Pendiente')
                                    ->default($this->credito->proposicion?->SaldoPendiente)
                                    ->disabled(),
                            ]),
                        Forms\Components\Section::make('Información de Exoneración')
                            ->columns(2)
                            ->schema([
                                Forms\Components\Select::make('TipoExoneracionID')
                                    ->label('Tipo de Exoneración')
                                    ->options(TipoExoneracion::where('Activo', 1)->pluck('Nombre', 'TipoExoneracionID'))
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        $monto = $this->calcularMontoDisponible($state);
                                        $set('MontoDisponible', $monto);
                                    }),
                                Forms\Components\TextInput::make('MontoDisponible')
                                    ->label('Monto Disponible')
                                    ->numeric()
                                    ->readOnly()
                                    ->dehydrated(false),
                                Forms\Components\TextInput::make('MontoExonerado')
                                    ->label('Monto a Exonerar')
                                    ->numeric()
                                    ->required()
                                    ->step(0.01),
                            ]),
                        Forms\Components\Section::make('Detalles')
                            ->columns(1)
                            ->schema([
                                Forms\Components\Textarea::make('Comentario')
                                    ->label('Comentario/Justificación')
                                    ->required()
                                    ->rows(4),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function create()
    {
        $this->validate();

        $data = $this->form->getState();
        $creditoID = $this->credito->CreditoID;
        $tipoExoneracionID = $data['TipoExoneracionID'];
        $montoDisponible = $this->calcularMontoDisponible($tipoExoneracionID);

        // Obtener la fecha del día abierto con la hora actual
        $fechaAbierta = DateFieldResolver::getFechaAbierta();
        $fechaSolicitud = $fechaAbierta 
            ? $fechaAbierta->copy()->setTime(now()->hour, now()->minute, now()->second) 
            : now();

        $solicitud = new SolicitudExoneracion();
        $solicitud->CreditoID = $creditoID;
        $solicitud->TipoExoneracionID = $tipoExoneracionID;
        $solicitud->MontoDisponible = $montoDisponible;
        $solicitud->MontoExonerado = $data['MontoExonerado'];
        $solicitud->Comentario = $data['Comentario'];
        $solicitud->UserSolicitanteID = auth()->id();
        $solicitud->Estado = 'PENDIENTE';
        $solicitud->NivelAprobacionRequerido = 3; // Solo Gerencia puede aprobar
        $solicitud->FechaSolicitud = $fechaSolicitud;
        $solicitud->FechaModificacion = $fechaSolicitud;

        $solicitud->save();

        \Filament\Notifications\Notification::make()
            ->title('Solicitud creada')
            ->body('Solicitud de exoneración creada correctamente')
            ->success()
            ->send();

        return $this->redirect(SolicitudExoneracionResource::getUrl('index'));
    }

    private function calcularMontoDisponible($tipoExoneracionID): float
    {
        if (!$tipoExoneracionID) {
            return 0;
        }

        $service = new ExoneracionService();
        $tipo = TipoExoneracion::find($tipoExoneracionID);

        if (!$tipo) {
            return 0;
        }

        return match($tipo->Codigo) {
            'I' => $service->obtenerMontoDisponibleInteres($this->credito->CreditoID),
            'M' => $service->obtenerMontoDisponibleMora($this->credito->CreditoID),
            'P' => $this->credito->proposicion?->SaldoPendiente ?? 0,
            default => 0
        };
    }
}
