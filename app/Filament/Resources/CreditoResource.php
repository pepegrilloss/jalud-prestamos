<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CreditoResource\Pages;
use App\Models\Credito;
use App\Models\ProposicionCredito;
use App\Models\TipoPago;
use App\Models\Zona;
use App\Models\TipoCredito;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CreditoResource extends Resource
{
    protected static ?string $model = Credito::class;

    protected static ?string $navigationGroup = 'Créditos';
    protected static ?string $navigationIcon = 'heroicon-o-check-circle';
    protected static ?int $navigationSort = 8;
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

                Tables\Columns\TextColumn::make('proposicion.tipoCredito.Descripcion')
                    ->label('Tipo de Crédito')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('proposicion.MontoTotal')
                    ->label('Monto')
                    ->money('PEN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('MontoConInteres')
                    ->label('Monto + Interés')
                    ->getStateUsing(function ($record) {
                        $montoTotal = $record->proposicion->MontoTotal ?? 0;
                        $interes = $record->proposicion->MontoInteres ?? 0;
                        return $montoTotal + $interes;
                    })
                    ->formatStateUsing(fn($state) => 'S/ ' . number_format($state, 2, '.', ','))
                    ->sortable(),

                Tables\Columns\TextColumn::make('SaldoPendiente')
                    ->label('Saldo Pendiente')
                    ->getStateUsing(function ($record) {
                        $totalPagado = $record->cuotas()->sum('MontoPagado');
                        $saldo = ($record->proposicion->MontoTotal ?? 0) - $totalPagado;
                        return max(0, $saldo);
                    })
                    ->formatStateUsing(fn($state) => 'S/ ' . number_format($state, 2, '.', ','))
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
                Tables\Filters\SelectFilter::make('zona')
                    ->label('Zona')
                    ->options(Zona::where('Activo', true)->pluck('Nombre', 'ZonaID')->toArray())
                    ->query(function (Builder $query, array $data) {
                        return $query->when(
                            $data['value'] ?? null,
                            fn(Builder $q) => $q->whereHas('proposicion.cliente.negocio', fn(Builder $subQ) => $subQ->where('ZonaID', $data['value']))
                        );
                    })
                    ->native(false),
                Tables\Filters\SelectFilter::make('tipoCredito')
                    ->label('Tipo de Crédito')
                    ->options(TipoCredito::where('Activo', true)->pluck('Descripcion', 'TipoCreditoID')->toArray())
                    ->query(function (Builder $query, array $data) {
                        return $query->when(
                            $data['value'] ?? null,
                            fn(Builder $q) => $q->whereHas('proposicion', fn(Builder $subQ) => $subQ->where('TipoCreditoID', $data['value']))
                        );
                    })
                    ->native(false),
            ])
            ->modifyQueryUsing(function ($query) {
                return $query->with(['proposicion', 'tipoPago'])
                    // Excluir créditos de proposiciones refinanciadas
                    ->whereHas('proposicion', function (Builder $q) {
                        $q->where('FueRefinanciada', 0);
                    });
            })
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('descargar_libreta')
                    ->label('Excel')
                    ->tooltip('Descargar Libreta de Pagos (Excel)')
                    ->icon('heroicon-o-document-arrow-down')
                    ->url(fn($record) => route('libreta-pagos.descargar', $record->CreditoID))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('descargar_libreta_html')
                    ->label('Imprimir')
                    ->tooltip('Ver Libreta de Pagos para Imprimir')
                    ->icon('heroicon-o-printer')
                    ->color('success')
                    ->url(fn($record) => route('libreta-pagos.html', $record->CreditoID))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('descargar_ticket')
                    ->label('Descargar Ticket')
                    ->icon('heroicon-o-ticket')
                    ->color('danger')
                    ->url(fn($record) => route('ticket.descargar', $record->CreditoID))
                    ->openUrlInNewTab(),
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
