<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PagoResource\Pages;
use App\Models\Pago;
use App\Models\Credito;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\RawJs;
use Filament\Forms\Get;
use Filament\Forms\Set;

class PagoResource extends Resource
{
    protected static ?string $model = Pago::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Gestión de Pagos';
    protected static ?int $navigationGroupSort = 3;
    protected static ?int $navigationSort = 8;
    protected static ?string $modelLabel = 'Pago';
    protected static ?string $pluralModelLabel = 'Pagos';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información del Pago')
                    ->schema([
                        Forms\Components\Select::make('CreditoID')
                            ->label('Cliente - Dni - Crédito')
                            ->options(
                                Credito::with('proposicion.cliente')
                                    ->where('Activo', 1)
                                    ->get()
                                    ->mapWithKeys(fn($credito) => [
                                        $credito->CreditoID => "{$credito->proposicion->cliente->NombresApellidos} - {$credito->proposicion->cliente->DNI} - {$credito->proposicion->CodigoCredito}"
                                    ])
                            )
                            ->required()
                            ->searchable()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (Set $set, $state) {
                                $set('CuotaID', null);
                            }),

                        Forms\Components\Select::make('CuotaID')
                            ->label('Cuota - Control de Pagos')
                            ->options(function (Forms\Get $get) {
                                $creditoID = $get('CreditoID');
                                if (!$creditoID) {
                                    return [];
                                }
                                
                                return \App\Models\Cuota::where('CreditoID', $creditoID)
                                    ->where('Activo', 1)
                                    ->where('NumeroCuota', '>', 0)
                                    ->where('Estado', '!=', \App\Models\Cuota::ESTADO_PAGADA)
                                    ->orderBy('NumeroCuota')
                                    ->get()
                                    ->mapWithKeys(fn($cuota) => [
                                        $cuota->CuotaID => "Cuota #{$cuota->NumeroCuota} - " . 
                                            (\Carbon\Carbon::parse($cuota->FechaVencimiento)->format('d/m/Y')) . 
                                            " - S/ {$cuota->MontoCuota}"
                                    ]);
                            })
                            ->required()
                            ->searchable()
                            ->native(false)
                            ->hidden()
                            ->disabled(fn(Forms\Get $get) => !$get('CreditoID'))
                            ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get) {
                                // Si es la primera vez que se abre el formulario, auto-seleccionar la primera cuota pendiente
                                $creditoID = $get('CreditoID');
                                if ($creditoID && !$get('CuotaID')) {
                                    $primeraCuota = \App\Models\Cuota::where('CreditoID', $creditoID)
                                        ->where('Activo', 1)
                                        ->where('NumeroCuota', '>', 0)
                                        ->where('Estado', '!=', \App\Models\Cuota::ESTADO_PAGADA)
                                        ->orderBy('NumeroCuota')
                                        ->first();
                                    
                                    if ($primeraCuota) {
                                        $set('CuotaID', $primeraCuota->CuotaID);
                                    }
                                }
                            }),
                    ]),

                Forms\Components\Section::make('Detalles del Pago')
                    ->schema([
                        Forms\Components\TextInput::make('MontoPagado')
                            ->label('Monto Pagado')
                            ->numeric()
                            ->required()
                            ->placeholder('Ingrese el monto del pago'),

                        Forms\Components\DatePicker::make('FechaPago')
                            ->label('Fecha de Pago')
                            ->required()
                            ->default(now())
                            ->disabled()
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                    ]),

                Forms\Components\Section::make('Flags de Pago')
                    ->schema([
                        Forms\Components\Checkbox::make('EsMora')
                            ->label('Es Mora')
                            ->default(false),

                        Forms\Components\Checkbox::make('EsPagoAMayor')
                            ->label('Es A Mayor')
                            ->default(false),
      
                    ])->columns(3),

                Forms\Components\Section::make('Comentarios')
                    ->schema([
                        Forms\Components\Textarea::make('Comentario')
                            ->label('Comentario')
                            ->rows(3)
                            ->placeholder('Notas adicionales sobre el pago'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(null)
            ->columns([
                Tables\Columns\TextColumn::make('cuota.credito.proposicion.cliente.NombresApellidos')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('cuota.credito.proposicion.CodigoCredito')
                    ->label('Código Crédito')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('cuota.NumeroCuota')
                    ->label('Cuota #')
                    ->sortable(),

                Tables\Columns\TextColumn::make('MontoPagado')
                    ->label('Monto Pagado')
                    ->money('PEN')
                    ->sortable(),

                Tables\Columns\BooleanColumn::make('EsMora')
                    ->label('Es Mora')
                    ->sortable(),

                Tables\Columns\TextColumn::make('FechaPago')
                    ->label('Fecha de Pago')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('UsuarioRegistro')
                    ->label('Usuario Registro')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible(fn () => !auth()->user()?->hasRole('Promotor Cobrador')),

                Tables\Columns\IconColumn::make('Activo')
                    ->boolean()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible(fn () => !auth()->user()?->hasRole('Promotor Cobrador')),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('EsMora')
                    ->label('Es Mora'),

                Tables\Filters\TernaryFilter::make('EsPagoForzado')
                    ->label('Es Pago Forzado'),
            ])
            ->actions([
                    Tables\Actions\ViewAction::make()
                        ->visible(fn ($record) => !auth()->user()?->hasRole('Promotor Cobrador')),
                    Tables\Actions\EditAction::make()
                        ->visible(fn ($record) => !auth()->user()?->hasRole('Promotor Cobrador')),
                    Tables\Actions\DeleteAction::make()
                        ->visible(fn ($record) => !auth()->user()?->hasRole('Promotor Cobrador')),
            ]);
           
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPagos::route('/'),
            'create' => Pages\CreatePago::route('/create'),
            'view' => Pages\ViewPago::route('/{record}'),
            'edit' => Pages\EditPago::route('/{record}/edit'),
        ];
    }
}
