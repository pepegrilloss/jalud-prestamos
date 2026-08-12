<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClienteProposicionResource\Pages;
use App\Models\AperturaCierreDia;
use App\Models\ProposicionCredito;
use App\Models\Sede;
use App\Models\Tasa;
use App\Models\TipoCredito;
use App\Models\Zona;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ClienteProposicionResource extends Resource
{
    protected static ?string $model = ProposicionCredito::class;

    protected static ?string $navigationGroup = 'Créditos';

    protected static ?int $navigationGroupSort = 2;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Proposiciones';

    protected static ?string $modelLabel = 'Proposición';

    protected static ?string $pluralModelLabel = 'Proposiciones';

    public static function getNavigationBadge(): ?string
    {
        $count = \App\Models\ProposicionCredito::where('Estado', 'PENDIENTE')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /**
     * Override para usar los permisos de 'cliente::proposicion' en lugar de ProposicionCreditoPolicy.
     */
    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_cliente::proposicion') ?? false;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->can('view_cliente::proposicion') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detalles del Crédito')
                    ->schema([
                        Forms\Components\Select::make('ClienteID')
                            ->label('Cliente')
                            ->relationship('cliente', 'NombresApellidos')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpan(8),

                        Forms\Components\TextInput::make('CodigoCredito')
                            ->label('Código de Crédito')
                            ->disabled()
                            ->dehydrated()
                            ->columnSpan(4)
                            ->prefixIcon('heroicon-o-hashtag'),

                        Forms\Components\Select::make('TipoCreditoID')
                            ->label('Tipo de Crédito')
                            ->options(TipoCredito::where('Activo', true)->pluck('Descripcion', 'TipoCreditoID'))
                            ->required()
                            ->searchable()
                            ->native(false)
                            ->columnSpan(8)
                            ->prefixIcon('heroicon-o-tag'),

                        Forms\Components\TextInput::make('MontoTotal')
                            ->label('Monto Total')
                            ->required()
                            ->numeric()
                            ->prefix('S/')
                            ->columnSpan(4)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, Get $get, $state, $livewire) {
                                static::calcularTotales($set, $get, $state);
                                static::validarMontoMaximo($get, $state, $livewire);
                            })
                            ->helperText(function (Get $get, $state, $livewire) {
                                if (! $state || ! $get('ClienteID')) {
                                    return '';
                                }
                                $exclusionID = static::obtenerExclusionMMR($get);
                                $disponible = \App\Services\ProposicionValidatorService::calcularMontoDisponible((int) $get('ClienteID'), $exclusionID);
                                $montoActual = (float) $state;

                                if ($disponible['montoDisponible'] <= 0) {
                                    return "No hay monto disponible. (Máximo: S/ {$disponible['montoMaximoRecomendado']}, Utilizado: S/ {$disponible['montoUtilizado']})";
                                }
                                if ($montoActual > $disponible['montoDisponible']) {
                                    return "Excede el disponible de S/ {$disponible['montoDisponible']}. (Máximo: S/ {$disponible['montoMaximoRecomendado']}, Utilizado: S/ {$disponible['montoUtilizado']})";
                                }

                                return "Disponible: S/ {$disponible['montoDisponible']} (Máximo: S/ {$disponible['montoMaximoRecomendado']}, Utilizado: S/ {$disponible['montoUtilizado']})";
                            })
                            ->suffixIcon(function (Get $get, $state) {
                                if (! $state || ! $get('ClienteID')) {
                                    return null;
                                }
                                $exclusionID = static::obtenerExclusionMMR($get);
                                $disponible = \App\Services\ProposicionValidatorService::calcularMontoDisponible((int) $get('ClienteID'), $exclusionID);

                                return (float) $state > $disponible['montoDisponible']
                                    ? 'heroicon-s-exclamation-circle'
                                    : 'heroicon-s-check-circle';
                            })
                            ->rules([
                                function (Get $get) {
                                    return function ($attribute, $value, $fail) use ($get) {
                                        $clienteID = $get('ClienteID');
                                        if ($clienteID) {
                                            $exclusionID = static::obtenerExclusionMMR($get);
                                            $disponible = \App\Services\ProposicionValidatorService::calcularMontoDisponible((int) $clienteID, $exclusionID);
                                            if ((float) $value > (float) $disponible['montoDisponible']) {
                                                $fail("Excede el disponible de S/ {$disponible['montoDisponible']}. (Máximo recomendado: S/ {$disponible['montoMaximoRecomendado']}, Ya utilizado: S/ {$disponible['montoUtilizado']})");
                                            }
                                        }
                                    };
                                },
                            ]),

                        Forms\Components\Select::make('TasaID')
                            ->label('Tasa de Interés')
                            ->options(Tasa::where('Activo', true)->get()->mapWithKeys(fn ($t) => [$t->TasaID => "{$t->Nombre} - {$t->Valor}%"]))
                            ->required()
                            ->searchable()
                            ->native(false)
                            ->live()
                            ->columnSpan(8)
                            ->prefixIcon('heroicon-o-receipt-percent')
                            ->afterStateUpdated(function (Set $set, $state, Get $get) {
                                if ($tasa = Tasa::find($state)) {
                                    $set('TasaInteres', $tasa->Valor);
                                    $set('Plazo', $tasa->Dias);
                                    $set('NumeroCuotas', $tasa->Cuotas);
                                    static::calcularTotales($set, $get, $get('MontoTotal'));
                                }
                            }),

                        Forms\Components\TextInput::make('TasaInteres')
                            ->label('Tasa (%)')
                            ->disabled()
                            ->dehydrated()
                            ->suffix('%')
                            ->columnSpan(4)
                            ->extraInputAttributes(['class' => 'bg-gray-50 border-gray-200 cursor-not-allowed'])
                            ->hint('Valor según tasa seleccionada'),

                        Forms\Components\TextInput::make('Plazo')
                            ->label('Plazo (días)')
                            ->required()
                            ->numeric()
                            ->suffix('días')
                            ->columnSpan(4),

                        Forms\Components\TextInput::make('TasaMora')
                            ->label('Mora (S/)')
                            ->required()
                            ->numeric()
                            ->prefix('S/')
                            ->columnSpan(4),

                        Forms\Components\TextInput::make('NumeroCuotas')
                            ->label('N° Cuotas')
                            ->required()
                            ->numeric()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, Get $get) => static::calcularTotales($set, $get, $get('MontoTotal')))
                            ->columnSpan(4),

                        Forms\Components\Fieldset::make('Resumen del Crédito')
                            ->schema([
                                Forms\Components\TextInput::make('MontoCuota')
                                    ->label('Monto por Cuota')
                                    ->prefix('S/')
                                    ->disabled()
                                    ->dehydrated()
                                    ->columnSpan(4)
                                    ->extraInputAttributes(['class' => 'bg-gray-100 font-medium'])
                                    ->hint('Calculado'),

                                Forms\Components\TextInput::make('MontoInteres')
                                    ->label('Monto Total Interés')
                                    ->prefix('S/')
                                    ->disabled()
                                    ->dehydrated()
                                    ->columnSpan(4)
                                    ->extraInputAttributes(['class' => 'bg-gray-100 font-medium'])
                                    ->hint('Calculado'),

                                Forms\Components\TextInput::make('MontoTotalPagar')
                                    ->label('Monto Total a Pagar')
                                    ->prefix('S/')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpan(4)
                                    ->extraInputAttributes(['class' => 'bg-primary-50 border-primary-600 font-bold text-lg text-primary-700'])
                                    ->hint('Monto final estimado'),
                            ])->columns(12)
                            ->columnSpanFull(),
                    ])->columns(12),

                Forms\Components\Section::make('Información Adicional')
                    ->schema([
                        Forms\Components\Select::make('ZonaID')
                            ->label('Zona')
                            ->relationship('zona', 'Nombre')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('Observaciones')->rows(3)->columnSpanFull(),
                        Forms\Components\Select::make('Estado')
                            ->label('Estado')
                            ->options([
                                'PENDIENTE' => 'Pendiente',
                                'APROBADO' => 'Aprobado',
                                'RECHAZADO' => 'Rechazado',
                            ])
                            ->required()
                            ->native(false)
                            ->hidden(fn ($record) => $record !== null),
                    ])->columns(2),
            ]);
    }

    protected static function calcularTotales(Set $set, Get $get, $monto): void
    {
        $montoVal = static::normalizarNumero($monto);
        $tasaVal = static::normalizarNumero($get('TasaInteres'));
        $cuotasVal = (int) $get('NumeroCuotas');

        if ($montoVal > 0 && $tasaVal > 0 && $cuotasVal > 0) {
            $interes = $montoVal * ($tasaVal / 100);
            $total = $montoVal + $interes;
            $set('MontoInteres', round($interes, 2));
            $set('MontoTotalPagar', round($total, 2));
            $set('MontoCuota', round($total / $cuotasVal, 2));
        }
    }

    public static function calcularValoresCredito(mixed $monto, mixed $tasa, mixed $cuotas): array
    {
        $montoVal = static::normalizarNumero($monto);
        $tasaVal = static::normalizarNumero($tasa);
        $cuotasVal = max(1, (int) $cuotas);
        $interes = round($montoVal * ($tasaVal / 100), 2);
        $total = round($montoVal + $interes, 2);

        return [
            'MontoTotal' => $montoVal,
            'TasaInteres' => $tasaVal,
            'MontoInteres' => $interes,
            'MontoTotalPagar' => $total,
            'MontoCuota' => round($total / $cuotasVal, 2),
        ];
    }

    protected static function normalizarNumero(mixed $valor): float
    {
        if (is_numeric($valor)) {
            return (float) $valor;
        }

        $valor = trim((string) $valor);
        if ($valor === '') {
            return 0.0;
        }

        $valor = preg_replace('/[^\d,.\-]/', '', $valor) ?? '';
        $ultimaComa = strrpos($valor, ',');
        $ultimoPunto = strrpos($valor, '.');

        if ($ultimaComa !== false && $ultimoPunto !== false) {
            if ($ultimaComa > $ultimoPunto) {
                $valor = str_replace('.', '', $valor);
                $valor = str_replace(',', '.', $valor);
            } else {
                $valor = str_replace(',', '', $valor);
            }
        } elseif ($ultimaComa !== false) {
            $valor = str_replace(',', '.', $valor);
        }

        return is_numeric($valor) ? (float) $valor : 0.0;
    }

    protected static function obtenerExclusionMMR(Get $get): ?int
    {
        return \App\Services\ProposicionValidatorService::obtenerExclusionMMR(
            (int) $get('TipoCreditoID'),
            $get('ProposicionCreditoAnteriorID') ? (int) $get('ProposicionCreditoAnteriorID') : null
        );
    }

    protected static function validarMontoMaximo(Get $get, $monto, $livewire = null): void
    {
        $clienteID = $get('ClienteID');
        if (! $clienteID) {
            return;
        }

        $cliente = \App\Models\Cliente::find($clienteID);
        if (! $cliente || ! $cliente->analisisEconomico) {
            return;
        }

        $exclusionID = static::obtenerExclusionMMR($get);
        $disponible = \App\Services\ProposicionValidatorService::calcularMontoDisponible((int) $clienteID, $exclusionID);
        $montoTotal = (float) $monto;

        if ($montoTotal > $disponible['montoDisponible']) {
            \Filament\Notifications\Notification::make()
                ->warning()
                ->title('Monto Excede el Límite Disponible')
                ->body("El monto de S/ {$montoTotal} excede el disponible de S/ {$disponible['montoDisponible']}. (Máximo: S/ {$disponible['montoMaximoRecomendado']}, Utilizado: S/ {$disponible['montoUtilizado']}).")
                ->send();
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(null)
            ->modifyQueryUsing(fn ($query) => $query->whereDoesntHave('credito')
                // OPTIMIZACIÓN N+1: eager load cliente (DNI/Nombres) y zona para las columnas.
                ->with(['cliente', 'zona']))
            ->columns([
                Tables\Columns\TextColumn::make('CodigoCredito')
                    ->label('Código Proposición')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('cliente.DNI')
                    ->label('DNI Cliente')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('cliente.NombresApellidos')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('MontoTotal')
                    ->label('Monto')
                    ->money('PEN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('NumeroCuotas')
                    ->label('Cuotas')
                    ->sortable(),

                Tables\Columns\TextColumn::make('zona.Nombre')
                    ->label('Zona')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'APROBADO' => 'success',
                        'RECHAZADO' => 'danger',
                        'PENDIENTE' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('SedeID')
                    ->label('Sede')
                    ->options(Sede::where('Activo', true)->pluck('Nombre', 'SedeID'))
                    ->visible(fn () => auth()->user()->esAdmin()),
                Tables\Filters\SelectFilter::make('ZonaID')
                    ->label('Zona')
                    ->relationship('zona', 'Nombre')
                    ->searchable(),

                Tables\Filters\SelectFilter::make('Estado')
                    ->label('Estado')
                    ->options([
                        'PENDIENTE' => 'Pendiente',
                        'APROBADO' => 'Aprobado',
                        'RECHAZADO' => 'Rechazado',
                    ]),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->label('Ver'),

                    Tables\Actions\EditAction::make()
                        ->label('Editar')
                        ->visible(fn ($record) => AperturaCierreDia::estaAbierto() && static::canEdit($record)),

                    Tables\Actions\DeleteAction::make()
                        ->label('Eliminar')
                        ->visible(fn ($record) => AperturaCierreDia::estaAbierto() && static::canDelete($record)),
                ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('crear_nueva_proposicion')
                    ->label('Nueva Proposición')
                    ->icon('heroicon-o-plus')
                    ->size('lg')
                    ->visible(fn () => AperturaCierreDia::estaAbierto() && auth()->user()?->can('create_cliente::proposicion'))
                    ->url(fn (): string => '/admin/crear-proposicion-creditos/create')
                    ->openUrlInNewTab(false),

            ])
            ->defaultSort('CodigoCredito', 'desc')
            ->paginationPageOptions([10, 25, 50, 100])
            ->poll('30s');
    }

    public static function getWidgets(): array
    {
        return [
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClienteProposicions::route('/'),
            'view' => Pages\ViewClienteProposicion::route('/{record}'),
            'edit' => Pages\EditClienteProposicion::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        if (! (auth()->user()?->can('update_cliente::proposicion') ?? false)) {
            return false;
        }

        return ! \App\Models\Credito::withoutGlobalScope('sede')
            ->where('ProposicionCreditoID', $record->ProposicionCreditoID)
            ->exists();
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('delete_cliente::proposicion') ?? false;
    }
}
