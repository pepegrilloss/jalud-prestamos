<?php

namespace App\Filament\Resources\CreditoResource\Pages;

use App\Filament\Resources\CreditoResource;
use App\Models\Credito;
use App\Models\ProposicionCredito;
use App\Models\TipoPago;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCredito extends CreateRecord
{
    protected static string $resource = CreditoResource::class;

    public function getTitle(): string | \Illuminate\Contracts\Support\Htmlable
    {
        $url = static::getResource()::getUrl('index');
        return new \Illuminate\Support\HtmlString("
            <div class='flex items-center gap-x-3'>
                <a href='{$url}' class='flex items-center justify-center rounded-full p-2 hover:bg-gray-500/5 focus:outline-none focus:ring-2 focus:ring-primary-500/70 transition'>
                    <svg class='w-5 h-5 text-gray-500 dark:text-gray-400' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                        <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M10 19l-7-7m0 0l7-7m-7 7h18' />
                    </svg>
                </a>
                <span>Generar Crédito</span>
            </div>
        ");
    }

    protected ?ProposicionCredito $proposicion = null;

    public function mount(): void
    {
        parent::mount();
        
        $proposicionId = request()->query('proposicion');
        if (!$proposicionId) {
            redirect()->route('filament.admin.resources.creditos.index');
        }
        
        $this->proposicion = ProposicionCredito::findOrFail($proposicionId);
    }

    protected function getFormActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Crear'),
            Actions\Action::make('cancel')
                ->label('Cancelar')
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
        ];
    }

    public function form(Form $form): Form
    {
        // Obtener la proposición del query parameter
        $proposicionId = request()->query('proposicion');
        $proposicion = ProposicionCredito::findOrFail($proposicionId);

        return $form
            ->schema([
                Forms\Components\Section::make('Información de la Proposición')
                    ->schema([
                        Forms\Components\TextInput::make('codigo_credito')
                            ->label('Código de Crédito')
                            ->default($proposicion->CodigoCredito ?? '')
                            ->disabled(),

                        Forms\Components\TextInput::make('cliente_nombres')
                            ->label('Cliente')
                            ->default($proposicion->cliente->NombresApellidos ?? '')
                            ->disabled(),

                        Forms\Components\TextInput::make('cliente_dni')
                            ->label('DNI')
                            ->default($proposicion->cliente->DNI ?? '')
                            ->disabled(),

                        Forms\Components\TextInput::make('monto_total')
                            ->label('Monto Total')
                            ->default('S/. ' . number_format($proposicion->MontoTotal, 2))
                            ->disabled(),

                        Forms\Components\TextInput::make('tasa_interes')
                            ->label('Tasa (%)')
                            ->default(number_format($proposicion->TasaInteres, 2))
                            ->disabled(),

                        Forms\Components\TextInput::make('plazo')
                            ->label('Plazo (días)')
                            ->default($proposicion->Plazo ?? '')
                            ->disabled(),

                        Forms\Components\TextInput::make('numero_cuotas')
                            ->label('Número de Cuotas')
                            ->default($proposicion->NumeroCuotas ?? '')
                            ->disabled(),

                        Forms\Components\TextInput::make('monto_cuota')
                            ->label('Monto por Cuota')
                            ->default('S/. ' . number_format($proposicion->MontoCuota, 2))
                            ->disabled(),

                        Forms\Components\TextInput::make('monto_interes_total')
                            ->label('Monto Total de Interés')
                            ->default('S/. ' . number_format($proposicion->MontoInteres, 2))
                            ->disabled(),

                        Forms\Components\TextInput::make('monto_total_pagar')
                            ->label('Monto Total a Pagar')
                            ->default('S/. ' . number_format($proposicion->MontoTotal + $proposicion->MontoInteres, 2))
                            ->disabled(),

                        Forms\Components\TextInput::make('tasa_mora')
                            ->label('Tasa de Mora (%)')
                            ->default(number_format($proposicion->TasaMora, 2))
                            ->disabled(),

                        Forms\Components\TextInput::make('tipo_credito')
                            ->label('Tipo de Crédito')
                            ->default($proposicion->tipoCredito->Descripcion ?? '')
                            ->disabled(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Generación de Crédito')
                    ->schema([
                        Forms\Components\Select::make('tipo_pago_id')
                            ->label('Tipo de Pago')
                            ->options(
                                TipoPago::where('Activo', true)
                                    ->pluck('Nombre', 'TipoPagoID')
                            )
                            ->required()
                            ->searchable()
                            ->native(false)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('comentario_generacion')
                            ->label('Comentario de Generación')
                            ->rows(4)
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Historial de Evaluaciones')
                    ->schema([
                        Forms\Components\ViewField::make('evaluaciones')
                            ->view('filament.components.evaluaciones-credito')
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),
            ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Preparar datos para crear el Credito
        return [
            'proposicion_id' => $this->proposicion->ProposicionCreditoID,
            'tipo_pago_id' => $data['tipo_pago_id'],
            'comentario_generacion' => $data['comentario_generacion'] ?? null,
        ];
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        // Obtener la fecha abierta para la generación del crédito (con hora actual)
        $fechaAbierta = \App\Services\DateFieldResolver::getFechaAbierta();
        $fechaGeneracion = $fechaAbierta ? $fechaAbierta->copy()->setTime(now()->hour, now()->minute, now()->second) : now();
        
        // Crear el registro de Credito
        $credito = Credito::create([
            'ProposicionCreditoID' => $data['proposicion_id'],
            'TipoPagoID' => $data['tipo_pago_id'],
            'ComentarioGeneracion' => $data['comentario_generacion'] ?? null,
            'FechaGeneracion' => $fechaGeneracion,
            'UserGeneracionID' => auth()->id(),
            'Activo' => true,
        ]);

        return $credito;
    }

    protected function getRedirectUrl(): string
    {
        // Redirigir al listado de créditos después de crear
        return CreditoResource::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Crédito generado exitosamente';
    }
}
