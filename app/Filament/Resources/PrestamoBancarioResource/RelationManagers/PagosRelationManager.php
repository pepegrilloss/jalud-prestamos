<?php

namespace App\Filament\Resources\PrestamoBancarioResource\RelationManagers;

use App\Models\PagoPrestamoBancario;
use App\Services\PrestamoBancarioService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PagosRelationManager extends RelationManager
{
    protected static string $relationship = 'pagos';

    protected static ?string $title = 'Pagos, cancelaciones y extornos';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['cuota', 'movimiento', 'usuario']))
            ->defaultSort('FechaRegistro', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('PagoPrestamoBancarioID')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('Tipo')
                    ->label('Operación')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        PagoPrestamoBancario::TIPO_PAGO_CUOTA => 'Pago de cuota',
                        PagoPrestamoBancario::TIPO_EXTORNO_CUOTA => 'Extorno de cuota',
                        PagoPrestamoBancario::TIPO_CANCELACION_ANTICIPADA => 'Cancelación anticipada',
                        PagoPrestamoBancario::TIPO_EXTORNO_CANCELACION => 'Extorno de cancelación',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('cuota.Numero')->label('Cuota')->sortable()->placeholder('-'),
                Tables\Columns\TextColumn::make('movimiento.CuentaOrigenNombre')->label('Origen')->placeholder('-'),
                Tables\Columns\TextColumn::make('Monto')->money('PEN')->weight('bold'),
                Tables\Columns\TextColumn::make('FechaContable')->label('Fecha contable')->date('d/m/Y'),
                Tables\Columns\TextColumn::make('FechaRegistro')->label('Registrado')->dateTime('d/m/Y H:i:s'),
                Tables\Columns\TextColumn::make('Concepto')->wrap(),
                Tables\Columns\TextColumn::make('usuario.name')->label('Usuario'),
                Tables\Columns\TextColumn::make('PagoOriginalID')->label('Extorno de')->placeholder('-'),
            ])->actions([
                Tables\Actions\Action::make('extornar')
                    ->label('Extornar')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn (PagoPrestamoBancario $record) => in_array($record->Tipo, [
                        PagoPrestamoBancario::TIPO_PAGO_CUOTA,
                        PagoPrestamoBancario::TIPO_CANCELACION_ANTICIPADA,
                    ], true) && ! $record->PagoOriginalID && ! $record->extorno()->exists())
                    ->requiresConfirmation()
                    ->modalHeading('Extornar operación de préstamo')
                    ->modalDescription(fn (PagoPrestamoBancario $record) => 'El importe regresará a '
                        .($record->movimiento?->CuentaOrigenNombre ?? 'la cuenta de origen')
                        .' y la operación quedará revertida.')
                    ->form([
                        Forms\Components\DatePicker::make('FechaContable')
                            ->label('Fecha contable')
                            ->default(now())
                            ->maxDate(now())
                            ->required(),
                        Forms\Components\TextInput::make('Concepto')->required()->maxLength(255),
                        Forms\Components\Textarea::make('Observaciones')->maxLength(1000),
                    ])
                    ->action(function (PagoPrestamoBancario $record, array $data): void {
                        $service = app(PrestamoBancarioService::class);
                        if ($record->Tipo === PagoPrestamoBancario::TIPO_CANCELACION_ANTICIPADA) {
                            $service->extornarCancelacionAnticipada($record, $data, auth()->id());
                        } else {
                            $service->extornarPago($record, $data, auth()->id());
                        }
                        Notification::make()->success()->title('Extorno registrado correctamente')->send();
                    }),
            ])->headerActions([])->bulkActions([]);
    }
}
