<?php

namespace App\Filament\Resources\PrestamoBancarioResource\RelationManagers;

use App\Models\PagoPrestamoBancario;
use App\Services\PrestamoBancarioService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PagosRelationManager extends RelationManager
{
    protected static string $relationship = 'pagos';
    protected static ?string $title = 'Pagos y extornos';

    public function table(Table $table): Table
    {
        return $table->defaultSort('FechaRegistro', 'desc')->columns([
            Tables\Columns\TextColumn::make('PagoPrestamoBancarioID')->label('#')->sortable(),
            Tables\Columns\TextColumn::make('cuota.Numero')->label('Cuota')->sortable(),
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
                ->visible(fn (PagoPrestamoBancario $record) => !$record->PagoOriginalID && !$record->extorno()->exists())
                ->requiresConfirmation()
                ->modalHeading('Extornar pago de préstamo')
                ->modalDescription('El importe regresará a Caja Abierta - Gerencia y la cuota volverá a estar pendiente.')
                ->form([
                    Forms\Components\DatePicker::make('FechaContable')->label('Fecha contable')->default(now())->maxDate(now())->required(),
                    Forms\Components\TextInput::make('Concepto')->required()->maxLength(255),
                    Forms\Components\Textarea::make('Observaciones')->maxLength(1000),
                ])
                ->action(function (PagoPrestamoBancario $record, array $data): void {
                    app(PrestamoBancarioService::class)->extornarPago($record, $data, auth()->id());
                    Notification::make()->success()->title('Extorno registrado correctamente')->send();
                }),
        ])->headerActions([])->bulkActions([]);
    }
}
