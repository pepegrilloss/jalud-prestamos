<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClienteProposicionResource\Pages;
use App\Filament\Resources\ClienteProposicionResource\Widgets\ClienteProposicionStats;
use App\Models\ProposicionCredito;
use App\Models\AperturaCierreDia;
use App\Models\TipoCredito;
use App\Models\Tasa;
use App\Models\Zona;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Support\RawJs;
use Filament\Tables;
use Filament\Tables\Table;

use App\Models\Sede;
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


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detalles del Crédito')
                    ->schema([
                        Forms\Components\TextInput::make('CodigoCredito')
                            ->label('Código de Crédito')
                            ->disabled()
                            ->dehydrated()
                            ->columnSpanFull(),

                        Forms\Components\Select::make('ClienteID')
                            ->label('Cliente')
                            ->relationship('cliente', 'NombresApellidos')
                            ->disabled()
                            ->dehydrated(false)
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

                        Forms\Components\TextInput::make('MontoCuota')->label('Monto por Cuota')->numeric()->required()->dehydrated(),
                        Forms\Components\TextInput::make('MontoInteres')->label('Monto Total Interés')->disabled()->dehydrated(),
                        Forms\Components\TextInput::make('MontoTotalPagar')->label('Monto Total a Pagar')->disabled()->dehydrated(false),
                        Forms\Components\TextInput::make('TasaMora')->label('Mora (S/)')->required()->numeric(),
                    ])->columns(3),

                Forms\Components\Section::make('Información Adicional')
                    ->schema([
                        Forms\Components\Select::make('ZonaID')
                            ->label('Zona')
                            ->relationship('zona', 'Nombre')
                            ->disabled()
                            ->dehydrated(false),
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
                            ->hidden(fn($record) => $record !== null),
                    ])->columns(2),
            ]);
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

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(null)
            ->modifyQueryUsing(fn($query) => $query->where('Estado', '<>', 'APROBADO'))
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
                    ->color(fn(string $state): string => match ($state) {
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
                        ->visible(fn() => AperturaCierreDia::estaAbierto()),

                    Tables\Actions\DeleteAction::make()
                        ->label('Eliminar')
                        ->visible(fn() => AperturaCierreDia::estaAbierto()),
                ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('crear_nueva_proposicion')
                    ->label('Nueva Proposición')
                    ->icon('heroicon-o-plus')
                    ->size('lg')
                    ->visible(fn() => AperturaCierreDia::estaAbierto())
                    ->url(fn(): string => '/admin/crear-proposicion-creditos/create')
                    ->openUrlInNewTab(false),

            ])
            ->defaultSort('CodigoCredito', 'desc')
            ->paginationPageOptions([10, 25, 50, 100])
            ->poll('30s');
    }

    public static function getWidgets(): array
    {
        return [
            ClienteProposicionStats::class,
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
}