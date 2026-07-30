<?php

namespace App\Filament\Resources\CuentaTesoreriaResource\RelationManagers;

use App\Filament\Resources\MovimientoTesoreriaResource;
use App\Models\MovimientoTesoreria;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MovimientosRelationManager extends RelationManager
{
    protected static string $relationship = 'movimientosOrigen';

    protected static ?string $title = 'Movimientos de la cuenta';

    public function table(Table $table): Table
    {
        $cuentaId = $this->getOwnerRecord()->CuentaTesoreriaID;

        return $table
            ->query(
                MovimientoTesoreria::query()
                    ->with('usuario')
                    ->where(function (Builder $query) use ($cuentaId): void {
                        $query->where('CuentaOrigenID', $cuentaId)
                            ->orWhere('CuentaDestinoID', $cuentaId);
                    })
            )
            ->defaultSort('FechaMovimiento', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('FechaContable')
                    ->label('Fecha contable')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('FechaMovimiento')
                    ->label('Registrado')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                Tables\Columns\TextColumn::make('direccion')
                    ->label('Movimiento')
                    ->badge()
                    ->getStateUsing(fn (MovimientoTesoreria $record) => (int) $record->CuentaOrigenID === (int) $cuentaId
                        ? 'Salida'
                        : 'Entrada')
                    ->color(fn (string $state) => $state === 'Salida' ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('Tipo')->label('Tipo')->badge(),
                Tables\Columns\TextColumn::make('contraparte')
                    ->label('Contraparte')
                    ->getStateUsing(fn (MovimientoTesoreria $record) => (int) $record->CuentaOrigenID === (int) $cuentaId
                        ? $record->CuentaDestinoNombre
                        : $record->CuentaOrigenNombre)
                    ->wrap(),
                Tables\Columns\TextColumn::make('Monto')->money('PEN')->weight('bold'),
                Tables\Columns\TextColumn::make('saldo_resultante')
                    ->label('Saldo resultante')
                    ->getStateUsing(fn (MovimientoTesoreria $record) => (int) $record->CuentaOrigenID === (int) $cuentaId
                        ? $record->SaldoNuevoOrigen
                        : $record->SaldoNuevoDestino)
                    ->money('PEN')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('Concepto')->wrap()->searchable(),
                Tables\Columns\TextColumn::make('usuario.name')->label('Usuario'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('Tipo')
                    ->options(fn () => MovimientoTesoreria::query()
                        ->where(function (Builder $query) use ($cuentaId): void {
                            $query->where('CuentaOrigenID', $cuentaId)
                                ->orWhere('CuentaDestinoID', $cuentaId);
                        })
                        ->distinct()
                        ->orderBy('Tipo')
                        ->pluck('Tipo', 'Tipo')),
                Tables\Filters\Filter::make('fecha')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('desde')->label('Desde'),
                        \Filament\Forms\Components\DatePicker::make('hasta')->label('Hasta'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['desde'] ?? null, fn (Builder $q, $fecha) => $q->whereDate('FechaContable', '>=', $fecha))
                        ->when($data['hasta'] ?? null, fn (Builder $q, $fecha) => $q->whereDate('FechaContable', '<=', $fecha))),
            ])
            ->actions([
                Tables\Actions\Action::make('ver')
                    ->label('Ver')
                    ->icon('heroicon-o-eye')
                    ->url(fn (MovimientoTesoreria $record) => MovimientoTesoreriaResource::getUrl('view', ['record' => $record])),
            ])
            ->headerActions([])
            ->bulkActions([]);
    }
}
