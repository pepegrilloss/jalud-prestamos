<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GenerarCreditoResource\Pages;
use App\Models\ProposicionCredito;
use App\Models\Cliente;
use App\Models\TipoCredito;
use App\Models\Tasa;
use App\Models\Zona;
use App\Models\Credito;
use App\Models\TipoPago;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Support\RawJs;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\HtmlString;
use Illuminate\Database\Eloquent\Builder;

class GenerarCreditoResource extends Resource
{
    protected static ?string $model = ProposicionCredito::class;
    protected static ?string $navigationGroup = 'Créditos';
    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';
    protected static ?int $navigationSort = 6;
    protected static ?string $navigationLabel = 'Generar Crédito';
    protected static ?string $modelLabel = 'Generar Crédito';
    protected static ?string $pluralModelLabel = 'Generar Crédito';

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

                        Forms\Components\TextInput::make('MontoCuota')->label('Monto por Cuota')->disabled()->dehydrated(),
                        Forms\Components\TextInput::make('MontoInteres')->label('Monto Total Interés')->disabled()->dehydrated(),
                        Forms\Components\TextInput::make('MontoTotalPagar')->label('Monto Total a Pagar')->disabled()->dehydrated(),
                        Forms\Components\TextInput::make('TasaMora')->label('Mora (S/)')->required()->numeric()->default(0.50),
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
                Tables\Columns\TextColumn::make('CodigoCredito')->label('Código')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('cliente.NombresApellidos')->label('Cliente')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('MontoTotal')->label('Monto')->money('PEN')->sortable(),
                Tables\Columns\TextColumn::make('TasaInteres')->label('Tasa (%)')->formatStateUsing(fn($state) => number_format((float)$state, 2, '.', '') . ' %')->sortable(),
                Tables\Columns\TextColumn::make('MontoInteres')
                    ->label('Intereses')
                    ->sortable()
                    ->getStateUsing(fn($record) => (float)(($record->MontoTotal ?? 0) * (($record->TasaInteres ?? 0) / 100)))
                    ->formatStateUsing(fn($state) => 'S/ ' . number_format((float)$state, 2, '.', '')),

                Tables\Columns\TextColumn::make('MontoTotalPagar')
                    ->label('Monto Total')
                    ->sortable()
                    ->getStateUsing(fn($record) => (float)(($record->MontoTotal ?? 0) + (($record->MontoTotal ?? 0) * (($record->TasaInteres ?? 0) / 100))))
                    ->formatStateUsing(fn($state) => 'S/ ' . number_format((float)$state, 2, '.', '')),
                Tables\Columns\TextColumn::make('NumeroCuotas')->label('Cuotas')->sortable(),
                Tables\Columns\TextColumn::make('Plazo')->label('Días')->sortable(),
                Tables\Columns\TextColumn::make('Estado')->badge()->color(fn (string $state): string => match ($state) {
                    'APROBADO' => 'success',
                    default => 'gray',
                })->sortable(),
            ])
            // CORRECCIÓN: Se cambió $q por $query para evitar el error de resolución
            ->modifyQueryUsing(function (Builder $query) {
                return $query->where('Estado', 'APROBADO')
                             ->whereDoesntHave('credito');
            })
            ->actions([
                Tables\Actions\Action::make('ver_comentarios')
                    ->label('Comentarios')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('info')
                    ->modalHeading('Historial de Evaluación')
                    ->modalSubmitAction(false)
                    ->form([ 
                        Forms\Components\ViewField::make('evaluaciones')
                            ->view('filament.components.evaluaciones-credito') 
                    ]),

                Action::make('generar_credito')
                    ->label('Generar Crédito')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->modalHeading('Confirmar Formalización')
                    ->modalWidth('2xl')
                    ->form([
                        Forms\Components\ViewField::make('resumen_moderno')
                            ->columnSpanFull()
                            ->view('filament.components.resumen-credito-moderno'),

                        Forms\Components\Section::make('Datos de Formalización')
                            ->schema([
                                Forms\Components\Select::make('TipoPagoID')
                                    ->label('Frecuencia de Pago')
                                    ->options(TipoPago::where('Activo', true)->pluck('Nombre', 'TipoPagoID'))
                                    ->required()
                                    ->native(false),
                                Forms\Components\Textarea::make('ComentarioGeneracion')->label('Notas')->rows(2),
                            ])
                    ])
                    ->action(function (ProposicionCredito $record, array $data) {
                        Credito::create([
                            'ProposicionCreditoID' => $record->ProposicionCreditoID,
                            'TipoPagoID' => $data['TipoPagoID'],
                            'ComentarioGeneracion' => $data['ComentarioGeneracion'],
                            'FechaGeneracion' => now(),
                            'UserGeneracionID' => auth()->id(),
                            'Activo' => true,
                        ]);
                        Notification::make()->title('Crédito Generado')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGenerarCreditos::route('/'),
            'view' => Pages\ViewGenerarCredito::route('/{record}'),
        ];
    }
}