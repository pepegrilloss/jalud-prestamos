<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CreditoResource\Pages;
use App\Models\Credito;
use App\Models\ProposicionCredito;
use App\Models\TipoPago;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CreditoResource extends Resource
{
    protected static ?string $model = Credito::class;

    protected static ?string $navigationGroup = 'Créditos';
    protected static ?string $navigationIcon = 'heroicon-o-check-circle';
    protected static ?int $navigationSort = 12;
    protected static ?string $label = 'Créditos Generados';
    protected static ?string $pluralLabel = 'Créditos Generados';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información de la Proposición')
                    ->schema([
                        Forms\Components\TextInput::make('proposicion_codigocredito')
                            ->label('Código de Crédito')
                            ->disabled(),

                        Forms\Components\TextInput::make('proposicion_cliente_nombre')
                            ->label('Cliente')
                            ->disabled(),

                        Forms\Components\TextInput::make('proposicion_cliente_dni')
                            ->label('DNI')
                            ->disabled(),

                        Forms\Components\TextInput::make('proposicion_monto')
                            ->label('Monto Total')
                            ->disabled(),

                        Forms\Components\TextInput::make('proposicion_tasa')
                            ->label('Tasa (%)')
                            ->disabled(),

                        Forms\Components\TextInput::make('proposicion_plazo')
                            ->label('Plazo (días)')
                            ->disabled(),

                        Forms\Components\TextInput::make('proposicion_cuotas')
                            ->label('Número de Cuotas')
                            ->disabled(),

                        Forms\Components\TextInput::make('proposicion_monto_cuota')
                            ->label('Monto por Cuota')
                            ->disabled(),

                        Forms\Components\TextInput::make('proposicion_interes')
                            ->label('Monto Total de Interés')
                            ->disabled(),

                        Forms\Components\TextInput::make('proposicion_mora')
                            ->label('Tasa de Mora (%)')
                            ->disabled(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Información del Crédito Generado')
                    ->schema([
                        Forms\Components\TextInput::make('FechaGeneracion')
                            ->label('Fecha de Generación')
                            ->disabled(),

                        Forms\Components\Select::make('TipoPagoID')
                            ->label('Tipo de Pago')
                            ->relationship('tipoPago', 'Nombre')
                            ->disabled(),

                        Forms\Components\Textarea::make('ComentarioGeneracion')
                            ->label('Comentario de Generación')
                            ->disabled(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(null)
            ->columns([
                Tables\Columns\TextColumn::make('proposicion.CodigoCredito')
                    ->label('Código Crédito')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('proposicion.cliente.NombresApellidos')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('proposicion.cliente.DNI')
                    ->label('DNI')
                    ->searchable(),

                Tables\Columns\TextColumn::make('proposicion.MontoTotal')
                    ->label('Monto')
                    ->money('PEN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('tipoPago.Nombre')
                    ->label('Tipo de Pago')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('FechaGeneracion')
                    ->label('Fecha Generación')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('TipoPagoID')
                    ->label('Tipo de Pago')
                    ->relationship('tipoPago', 'Nombre'),
            ])
            ->modifyQueryUsing(function ($query) {
                return $query->with(['proposicion', 'tipoPago']);
            })
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('FechaGeneracion', 'desc')
            ->paginationPageOptions([10, 25, 50]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCreditos::route('/'),
            'view' => Pages\ViewCredito::route('/{record}'),
        ];
    }
}
