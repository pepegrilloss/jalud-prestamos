<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CrearProposicionCreditoResource\Pages;
use App\Models\ProposicionCredito;
use App\Models\Cliente;
use App\Models\TipoCredito;
use App\Models\Tasa;
use App\Models\Zona;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Database\Eloquent\Builder;

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
                                        $set('ZonaID', $cliente->ZonaID);
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
                            ->native(false),

                        Forms\Components\TextInput::make('MontoTotal')
                            ->label('Monto Total')
                            ->required()
                            ->numeric()
                            ->prefix('S/')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn(Set $set, Get $get, $state) => static::calcularTotales($set, $get, $state)),

                        Forms\Components\Select::make('TasaID')
                            ->label('Tasa de Interés')
                            ->options(Tasa::where('Activo', true)->get()->mapWithKeys(fn($t) => [$t->TasaID => "{$t->Nombre} - {$t->Valor}%"]))
                            ->required()
                            ->live()
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

                        Forms\Components\TextInput::make('MontoCuota')->label('Monto por Cuota')->prefix('S/')->disabled()->dehydrated(),
                        Forms\Components\TextInput::make('MontoInteres')->label('Monto Total Interés')->prefix('S/')->disabled()->dehydrated(),
                        Forms\Components\TextInput::make('MontoTotalPagar')->label('Monto Total a Pagar')->prefix('S/')->disabled()->dehydrated(false),
                        Forms\Components\TextInput::make('TasaMora')->label('Mora (S/)')->required()->numeric()->default(0.50)->prefix('S/'),
                    ])->columns(3),

                Forms\Components\Section::make('Información Adicional')
                    ->schema([
                        Forms\Components\Select::make('ZonaID')
                            ->label('Zona')
                            ->options(Zona::where('Activo', true)->pluck('Nombre', 'ZonaID'))
                            ->searchable(),
                        Forms\Components\Textarea::make('Observaciones')->rows(3)->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    protected static function calcularTotales(Set $set, Get $get, $monto): void
    {
        $montoVal = (float)$monto;
        $tasaVal = (float)$get('TasaInteres');
        $cuotasVal = (int)$get('NumeroCuotas');

        if ($montoVal > 0 && $tasaVal > 0 && $cuotasVal > 0) {
            $interes = $montoVal * ($tasaVal / 100);
            $total = $montoVal + $interes;
            $set('MontoInteres', round($interes, 2));
            $set('MontoTotalPagar', round($total, 2));
            $set('MontoCuota', round($total / $cuotasVal, 2));
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(null)
            ->columns([
                Tables\Columns\TextColumn::make('CodigoCredito')->label('Código')->searchable(),
                Tables\Columns\TextColumn::make('cliente.NombresApellidos')->label('Cliente')->searchable(),
                Tables\Columns\TextColumn::make('MontoTotal')->label('Monto')->money('PEN'),
                Tables\Columns\TextColumn::make('Estado')->badge()->color(fn (string $state): string => match ($state) {
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
