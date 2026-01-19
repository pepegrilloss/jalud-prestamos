<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CrearProposicionCreditoResource\Pages;
use App\Models\ProposicionCredito;
use App\Models\Cliente;
use App\Models\TipoCredito;
use App\Models\Tasa;
use App\Models\Zona;
use App\Models\Credito;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Support\RawJs;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;
use App\Models\Pago;

class CrearProposicionCreditoResource extends Resource
{
    protected static ?string $model = ProposicionCredito::class;
    protected static ?string $navigationGroup = 'Créditos';
    protected static ?string $navigationIcon = 'heroicon-o-plus-circle';
    protected static ?int $navigationSort = 5;
    protected static ?string $navigationLabel = 'Nueva Proposición';
    protected static ?string $modelLabel = 'Proposición de Crédito';
    protected static ?string $pluralModelLabel = 'Proposiciones de Crédito';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Banner de advertencia si el cliente tiene crédito corriendo
                Forms\Components\Placeholder::make('alerta_credito_corriendo')
                    ->label('')
                    ->content(
                        fn(Get $get) => self::verificarCreditoCorriendo($get)
                    )
                    ->visible(fn(Get $get) => $get('ClienteID') && self::clienteTieneCreditoCorriendo($get('ClienteID')))
                    ->extraAttributes([
                        'class' => 'text-danger-600 font-bold text-center p-4 bg-danger-50 rounded-lg border-2 border-danger-600'
                    ]),

                Forms\Components\Section::make('Detalles del Crédito')
                    ->schema([
                        Forms\Components\Select::make('ClienteID')
                            ->label('Cliente')
                            ->options(
                                Cliente::where('Activo', true)
                                    ->orderBy('NombresApellidos')
                                    ->get()
                                    ->mapWithKeys(fn($cliente) => [
                                        $cliente->ClienteID => "{$cliente->NombresApellidos} (DNI: {$cliente->DNI})"
                                    ])
                            )
                            ->required()
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->columnSpanFull()
                            ->dehydrated()
                            ->live(debounce: 0)
                            ->afterStateUpdated(function (Set $set, $state) {
                                if ($state) {
                                    $cliente = Cliente::find($state);
                                    if ($cliente) {
                                        $set('CodigoCliente', $cliente->DNI);
                                        $set('ZonaID', $cliente->negocio?->ZonaID ?? null);
                                    }
                                }
                            }),

                        Forms\Components\TextInput::make('CodigoCredito')
                            ->label('Código de Crédito')
                            ->disabled()
                            ->dehydrated()
                            ->default(fn() => ProposicionCredito::generarCodigoCredito())
                            ->columnSpanFull(),

                        Forms\Components\Select::make('TipoCreditoID')
                            ->label('Tipo de Crédito')
                            ->options(TipoCredito::where('Activo', true)->pluck('Descripcion', 'TipoCreditoID'))
                            ->required()
                            ->searchable()
                            ->native(false)
                            ->live()
                            ->columnSpan(2)
                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                // Si se selecciona Refinanciamiento, crear una acción
                                if ($state) {
                                    $tipoCredito = TipoCredito::find($state);
                                    if ($tipoCredito && strtolower($tipoCredito->Descripcion) === 'refinanciamiento') {
                                        $clienteID = $get('ClienteID');
                                        if (!$clienteID) {
                                            Notification::make()
                                                ->warning()
                                                ->title('⚠️ Seleccione un Cliente')
                                                ->body("Primero debe seleccionar un cliente para proceder con el refinanciamiento.")
                                                ->send();
                                            $set('TipoCreditoID', null);
                                            return;
                                        }

                                        $creditosDisponibles = ProposicionCredito::obtenerCreditosActivosConSaldo($clienteID);

                                        if ($creditosDisponibles->isEmpty()) {
                                            Notification::make()
                                                ->warning()
                                                ->title('⚠️ Sin Créditos Disponibles')
                                                ->body("Este cliente no tiene créditos activos con saldo pendiente para refinanciar.")
                                                ->send();
                                            $set('TipoCreditoID', null);
                                            return;
                                        }

                                        // Guardar datos en sesión para mostrar opciones
                                        session()->put(
                                            'creditos_refinanciamiento',
                                            $creditosDisponibles->map(fn($p) => $p->obtenerInfoRefinanciamiento())->toArray()
                                        );
                                    }
                                }
                            })
                            ->hint(function (Get $get) {
                                $tipoID = $get('TipoCreditoID');
                                if ($tipoID) {
                                    $tipoCredito = TipoCredito::find($tipoID);
                                    if ($tipoCredito && strtolower($tipoCredito->Descripcion) === 'refinanciamiento') {
                                        $clienteID = $get('ClienteID');
                                        if ($clienteID) {
                                            $creditosDisponibles = ProposicionCredito::obtenerCreditosActivosConSaldo($clienteID);
                                            if (!$creditosDisponibles->isEmpty()) {
                                                return "👉 Se encontraron " . count($creditosDisponibles) . " crédito(s) para refinanciar";
                                            }
                                        }
                                    }
                                }
                                return '';
                            }),

                        // Campo oculto para almacenar la selección del crédito
                        Forms\Components\Hidden::make('ProposicionCreditoAnteriorID')
                            ->dehydrated(),

                        // Sección visible solo para refinanciamiento con Modal
                        Forms\Components\Section::make('Crédito a Refinanciar')
                            ->visible(function (Get $get) {
                                $tipoID = $get('TipoCreditoID');
                                if (!$tipoID)
                                    return false;
                                $tipoCredito = TipoCredito::find($tipoID);
                                return $tipoCredito && strtolower($tipoCredito->Descripcion) === 'refinanciamiento';
                            })
                            ->schema([
                                Forms\Components\Placeholder::make('credito_seleccionado')
                                    ->label('Crédito Seleccionado')
                                    ->content(function (Get $get) {
                                        $proposicionID = $get('ProposicionCreditoAnteriorID');
                                        if (!$proposicionID) {
                                            return '❌ No se ha seleccionado un crédito';
                                        }
                                        $proposicion = ProposicionCredito::find($proposicionID);
                                        if (!$proposicion)
                                            return '❌ Crédito no encontrado';
                                        $info = $proposicion->obtenerInfoRefinanciamiento();
                                        return "✓ {$proposicion->CodigoCredito} | Saldo: S/ " . number_format($info['SaldoPendiente'], 2) . " | Cuotas Pendientes: {$info['CuotasPendientes']}";
                                    })
                                    ->dehydrated(false),

                                Forms\Components\Actions::make([
                                    Forms\Components\Actions\Action::make('abrirModalRefinanciamiento')
                                        ->label('🔍 Seleccionar Crédito')
                                        ->modalHeading('Seleccionar Crédito a Refinanciar')
                                        ->modalDescription('Seleccione el crédito que desea refinanciar')
                                        ->form(function (Get $get) {
                                            $clienteID = $get('ClienteID');
                                            $creditosDisponibles = [];

                                            if ($clienteID) {
                                                $creditosDisponibles = ProposicionCredito::obtenerCreditosActivosConSaldo($clienteID)
                                                    ->mapWithKeys(function ($proposicion) {
                                                        $info = $proposicion->obtenerInfoRefinanciamiento();
                                                        return [
                                                            $proposicion->ProposicionCreditoID =>
                                                                "📌 {$proposicion->CodigoCredito} | Saldo: S/ " . number_format($info['SaldoPendiente'], 2) . " | Cuotas: {$info['CuotasPendientes']} | Tasa: {$info['TasaInteres']}% | Plazo: {$info['Plazo']} días"
                                                        ];
                                                    })
                                                    ->toArray();
                                            }

                                            return [
                                                Forms\Components\Select::make('credito_seleccionado_modal')
                                                    ->label('Créditos Disponibles')
                                                    ->options($creditosDisponibles)
                                                    ->required()
                                                    ->searchable()
                                                    ->native(false)
                                                    ->columnSpanFull(),
                                            ];
                                        })
                                        ->fillForm(function (Get $get) {
                                            return [
                                                'credito_seleccionado_modal' => $get('ProposicionCreditoAnteriorID'),
                                            ];
                                        })
                                        ->after(function (Set $set, Get $get, array $data) {
                                            if (isset($data['credito_seleccionado_modal']) && $data['credito_seleccionado_modal']) {
                                                self::cargarDatosRefinanciamiento($set, $get, $data['credito_seleccionado_modal']);
                                            }
                                        }),
                                ])->columnSpanFull(),
                            ])->columns(1)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('MontoTotal')
                            ->label('Monto Total')
                            ->required()
                            ->numeric()
                            ->columnSpan(1)
                            ->live(debounce: 500)
                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                // Calcular totales siempre
                                static::calcularTotales($set, $get, $state);

                                // Solo validar máximo si NO es refinanciamiento
                                $tipoID = $get('TipoCreditoID');
                                if ($tipoID) {
                                    $tipoCredito = TipoCredito::find($tipoID);
                                    if (!($tipoCredito && strtolower($tipoCredito->Descripcion) === 'refinanciamiento')) {
                                        static::validarMontoMaximo($set, $get, $state);
                                    }
                                }
                            })
                            ->helperText(function (Get $get, $state) {
                                if (!$state || !$get('ClienteID')) {
                                    return '';
                                }

                                $disponible = self::calcularMontoDisponible($get('ClienteID'));
                                $montoActual = (float) $state;

                                if ($disponible['montoDisponible'] <= 0) {
                                    return "❌ No hay monto disponible. (Máximo: S/ {$disponible['montoMaximoRecomendado']}, Utilizado: S/ {$disponible['montoUtilizado']})";
                                }

                                if ($montoActual > $disponible['montoDisponible']) {
                                    return "❌ Excede el disponible de S/ {$disponible['montoDisponible']}. (Máximo: S/ {$disponible['montoMaximoRecomendado']}, Utilizado: S/ {$disponible['montoUtilizado']})";
                                }

                                return "✓ Disponible: S/ {$disponible['montoDisponible']} (Máximo: S/ {$disponible['montoMaximoRecomendado']}, Utilizado: S/ {$disponible['montoUtilizado']})";
                            })
                            ->suffixIcon(function (Get $get, $state) {
                                if (!$state || !$get('ClienteID')) {
                                    return null;
                                }

                                $disponible = self::calcularMontoDisponible($get('ClienteID'));
                                $montoActual = (float) $state;

                                return $montoActual > $disponible['montoDisponible'] ? 'heroicon-s-exclamation-circle' : 'heroicon-s-check-circle';
                            })
                            ->rules([
                                function (Get $get) {
                                    return function ($attribute, $value, $fail) use ($get) {
                                        $clienteID = $get('ClienteID');
                                        if ($clienteID) {
                                            $disponible = self::calcularMontoDisponible($clienteID);
                                            if ((float) $value > (float) $disponible['montoDisponible']) {
                                                $fail("Excede el disponible de S/ {$disponible['montoDisponible']}. (Máximo recomendado: S/ {$disponible['montoMaximoRecomendado']}, Ya utilizado: S/ {$disponible['montoUtilizado']})");
                                            }
                                        }
                                    };
                                }
                            ]),

                        Forms\Components\Select::make('TasaID')
                            ->label('Tasa de Interés')
                            ->options(Tasa::where('Activo', true)->get()->mapWithKeys(fn($t) => [$t->TasaID => "{$t->Nombre} - {$t->Valor}%"]))
                            ->required()
                            ->live()
                            ->columnSpan(1)
                            ->afterStateUpdated(function (Set $set, $state, Get $get) {
                                if ($tasa = Tasa::find($state)) {
                                    $set('TasaInteres', $tasa->Valor);
                                    $set('Plazo', $tasa->Dias);
                                    $set('NumeroCuotas', $tasa->Cuotas);
                                    static::calcularTotales($set, $get, $get('MontoTotal'));
                                }
                            }),

                        Forms\Components\TextInput::make('TasaInteres')->label('Tasa (%)')->disabled()->dehydrated(),
                        Forms\Components\TextInput::make('Plazo')->label('Plazo (días)')->required()->numeric(),
                        Forms\Components\TextInput::make('NumeroCuotas')->label('N° Cuotas')->required()->numeric()
                            ->live(onBlur: true)->afterStateUpdated(fn(Set $set, Get $get) => static::calcularTotales($set, $get, $get('MontoTotal'))),

                        Forms\Components\TextInput::make('MontoCuota')->label('Monto por Cuota')->dehydrated(),
                        Forms\Components\TextInput::make('MontoInteres')->label('Monto Total Interés')->disabled()->dehydrated(),
                        Forms\Components\TextInput::make('MontoTotalPagar')->label('Monto Total a Pagar')->disabled()->dehydrated(),
                        Forms\Components\TextInput::make('TasaMora')->label('Mora (S/)')->required()->numeric()->default(0.50),
                    ])->columns(3),

                Forms\Components\Section::make('Información Adicional')
                    ->schema([
                        Forms\Components\Select::make('ZonaID')
                            ->label('Zona')
                            ->options(Zona::where('Activo', true)->pluck('Nombre', 'ZonaID'))
                            ->required()
                            ->searchable()
                            ->native(false)
                            ->dehydrated(),
                        Forms\Components\Textarea::make('Observaciones')->rows(3)->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    protected static function verificarCreditoCorriendo(Get $get): string
    {
        $clienteID = $get('ClienteID');
        if (!$clienteID || !self::clienteTieneCreditoCorriendo($clienteID)) {
            return '';
        }

        $cliente = Cliente::find($clienteID);
        $creditoCorriendo = $cliente->obtenerCreditoCorriendo();

        if ($creditoCorriendo && $creditoCorriendo->credito) {
            $totalPagado = $creditoCorriendo->credito->cuotas()->sum('MontoPagado');
            $montoCuotasTotal = $creditoCorriendo->credito->cuotas()->sum('MontoCuota');
            $saldoTotal = number_format(max(0, $montoCuotasTotal - $totalPagado), 2);

            return "🔴 Este cliente tiene un crédito corriendo con saldo pendiente de S/ {$saldoTotal}";
        }

        return '🔴 Este cliente tiene un crédito corriendo';
    }

    protected static function clienteTieneCreditoCorriendo($clienteID): bool
    {
        if (!$clienteID) {
            return false;
        }

        $cliente = Cliente::find($clienteID);
        return $cliente && $cliente->tieneCreditoCorriendo();
    }

    protected static function calcularTotales(Set $set, Get $get, $monto): void
    {
        $montoVal = (float) $monto;
        $tasaVal = (float) $get('TasaInteres');
        $cuotasVal = (int) $get('NumeroCuotas');

        if ($montoVal > 0 && $tasaVal > 0 && $cuotasVal > 0) {
            $interes = $montoVal * ($tasaVal / 100);
            $total = $montoVal + $interes;
            $set('MontoInteres', round($interes, 2));
            $set('MontoTotalPagar', round($total, 2));
            $set('MontoCuota', round($total / $cuotasVal, 2));
        }
    }

    protected static function calcularMontoDisponible($clienteID): array
    {
        $cliente = Cliente::find($clienteID);
        if (!$cliente || !$cliente->analisisEconomico) {
            return [
                'montoMaximoRecomendado' => 0,
                'montoUtilizado' => 0,
                'montoDisponible' => 0,
            ];
        }

        $montoMaximoRecomendado = (float) $cliente->analisisEconomico->MontoMaxRecomendado;

        // Obtener todas las proposiciones ACTIVAS del cliente (excluyendo rechazadas y refinanciadas)
        $montoUtilizado = (float) ProposicionCredito::where('ClienteID', $clienteID)
            ->where('Activo', true)
            ->whereNotIn('Estado', ['RECHAZADO']) // Excluir rechazadas
            ->where(function ($subquery) {
                // Excluir proposiciones que fueron refinanciadas
                $subquery->where('FueRefinanciada', false)
                    ->orWhereNull('FueRefinanciada');
            })
            ->sum('MontoTotal');

        $montoDisponible = max(0, $montoMaximoRecomendado - $montoUtilizado);

        return [
            'montoMaximoRecomendado' => $montoMaximoRecomendado,
            'montoUtilizado' => $montoUtilizado,
            'montoDisponible' => $montoDisponible,
        ];
    }

    protected static function validarMontoMaximo(Set $set, Get $get, $monto): void
    {
        $clienteID = $get('ClienteID');
        if (!$clienteID) {
            return;
        }

        $cliente = Cliente::find($clienteID);
        if (!$cliente || !$cliente->analisisEconomico) {
            return;
        }

        $disponible = self::calcularMontoDisponible($clienteID);
        $montoTotal = (float) $monto;

        if ($montoTotal > $disponible['montoDisponible']) {
            Notification::make()
                ->warning()
                ->title('⚠️ Monto Excede el Límite Disponible')
                ->body("El monto de S/ {$montoTotal} excede el disponible de S/ {$disponible['montoDisponible']}. (Máximo: S/ {$disponible['montoMaximoRecomendado']}, Utilizado: S/ {$disponible['montoUtilizado']}).")
                ->send();
        }
    }

    /**
     * Mostrar modal de selección de crédito para refinanciamiento
     */
    protected static function mostrarModalRefinanciamiento(Set $set, Get $get, $clienteID): void
    {
        $creditosDisponibles = ProposicionCredito::obtenerCreditosActivosConSaldo($clienteID);

        if ($creditosDisponibles->isEmpty()) {
            Notification::make()
                ->warning()
                ->title('⚠️ Sin Créditos Disponibles')
                ->body("Este cliente no tiene créditos activos con saldo pendiente para refinanciar.")
                ->send();
            return;
        }

        // Crear tabla de opciones para el modal
        $opciones = $creditosDisponibles->mapWithKeys(function ($proposicion) {
            $info = $proposicion->obtenerInfoRefinanciamiento();
            return [
                $proposicion->ProposicionCreditoID => "Código: {$proposicion->CodigoCredito} | Saldo: S/ {$info['SaldoPendiente']}"
            ];
        })->toArray();

        // Mostrar notificación con instrucciones
        Notification::make()
            ->title('📋 Seleccionar Crédito para Refinanciar')
            ->body('Se abrirá un modal con los créditos disponibles. Seleccione el que desea refinanciar.')
            ->info()
            ->send();

        // Guardar créditos disponibles en sesión temporalmente
        session()->put('creditos_refinanciamiento', $creditosDisponibles->toArray());
    }

    /**
     * Obtener y cargar datos del crédito seleccionado para refinanciamiento
     */
    public static function cargarDatosRefinanciamiento(Set $set, Get $get, $proposicionAnteriorID): void
    {
        $proposicionAnterior = ProposicionCredito::find($proposicionAnteriorID);

        if (!$proposicionAnterior) {
            Notification::make()
                ->danger()
                ->title('❌ Error')
                ->body("No se encontró el crédito seleccionado.")
                ->send();
            return;
        }

        $infoRefinanciamiento = $proposicionAnterior->obtenerInfoRefinanciamiento();

        // Cargar datos en el formulario
        $set('ProposicionCreditoAnteriorID', $proposicionAnteriorID);
        $set('EsRefinanciamiento', true);
        $set('MontoTotal', $infoRefinanciamiento['SaldoPendiente']);
        $set('TasaID', $infoRefinanciamiento['TasaID']);
        $set('TasaInteres', $infoRefinanciamiento['TasaInteres']);
        $set('Plazo', $infoRefinanciamiento['Plazo']);
        $set('NumeroCuotas', $infoRefinanciamiento['NumeroCuotas']);
        $set('TasaMora', $infoRefinanciamiento['TasaMora']);

        // Recalcular totales
        static::calcularTotales($set, $get, $infoRefinanciamiento['SaldoPendiente']);

        Notification::make()
            ->success()
            ->title('✓ Datos Cargados')
            ->body("Se han cargado los datos del crédito {$proposicionAnterior->CodigoCredito} con saldo S/ {$infoRefinanciamiento['SaldoPendiente']}")
            ->send();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(null)
            ->columns([
                Tables\Columns\TextColumn::make('CodigoCredito')->label('Código')->searchable(),
                Tables\Columns\TextColumn::make('cliente.NombresApellidos')->label('Cliente')->searchable(),
                Tables\Columns\TextColumn::make('MontoTotal')->label('Monto')->money('PEN'),
                Tables\Columns\TextColumn::make('Estado')->badge()->color(fn(string $state): string => match ($state) {
                    'PENDIENTE' => 'warning',
                    'APROBADO' => 'success',
                    'RECHAZADO' => 'danger',
                    default => 'gray',
                }),
            ])
            ->modifyQueryUsing(function (Builder $query) {
                return $query->where('Estado', 'PENDIENTE');
            })
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCrearProposicionCreditos::route('/'),
            'create' => Pages\CreateCrearProposicionCredito::route('/create'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}