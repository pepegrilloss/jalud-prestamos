<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClienteProposicionResource\Pages;
use App\Models\Cliente;
use App\Models\ProposicionCredito;
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

class ClienteProposicionResource extends Resource
{
    protected static ?string $model = Cliente::class;

    protected static ?string $navigationGroup = 'Créditos';
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?int $navigationSort = 9;

    protected static ?string $navigationLabel = 'Clientes - Nueva Proposición';
    protected static ?string $modelLabel = 'Cliente';
    protected static ?string $pluralModelLabel = 'Clientes para Proposición';

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
                            ->default(function () {
                                try {
                                    if ($encrypted = request()->query('cliente')) {
                                        session()->put('cliente_predefinido', true);
                                        return Crypt::decrypt($encrypted);
                                    }
                                } catch (\Exception $e) { return null; }
                            })
                            ->disabled(fn() => session()->has('cliente_predefinido'))
                            ->dehydrated(true)
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
            ->columns([
                Tables\Columns\TextColumn::make('DNI')
                    ->label('DNI')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('NombresApellidos')
                    ->label('Nombres y Apellidos')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),


                Tables\Columns\TextColumn::make('zona.Nombre')
                    ->label('Zona')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('proposiciones_count')
                    ->label('Proposiciones')
                    ->counts('proposiciones')
                    ->sortable()
                    ->badge()
                    ->color('info'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('ZonaID')
                    ->label('Zona')
                    ->relationship('zona', 'Nombre')
                    ->searchable(),

                Tables\Filters\TernaryFilter::make('Activo')
                    ->label('Estado')
                    ->placeholder('Todos')
                    ->trueLabel('Activos')
                    ->falseLabel('Inactivos'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('nueva_proposicion')
                        ->label('Nueva Proposición')
                        ->icon('heroicon-o-document-plus')
                        ->color('success')
                        ->url(fn (Cliente $record): string => 
                            self::getUrl('crear_proposicion', ['cliente' => Crypt::encrypt($record->ClienteID)])
                        )
                        ->visible(fn (Cliente $record) => $record->Activo),

                    Tables\Actions\Action::make('ver_proposiciones')
                        ->label('Ver Proposiciones')
                        ->icon('heroicon-o-document-text')
                        ->color('info')
                        ->url(fn (Cliente $record): string => 
                            self::getUrl('index') . '?tableFilters[cliente][value]=' . $record->ClienteID
                        )
                        ->visible(fn (Cliente $record) => $record->proposiciones_count > 0),
                ]),
            ])
            ->defaultSort('NombresApellidos', 'asc')
            ->paginationPageOptions([10, 25, 50, 100])
            ->poll('30s'); // Actualiza cada 30 segundos
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClienteProposicions::route('/'),
            'crear_proposicion' => Pages\CreateClienteProposicion::route('/crear-proposicion'),
        ];
    }

    public static function canCreate(): bool
    {
        return true;
    }
}