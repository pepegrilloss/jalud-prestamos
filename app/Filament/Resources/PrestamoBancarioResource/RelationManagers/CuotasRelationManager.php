<?php

namespace App\Filament\Resources\PrestamoBancarioResource\RelationManagers;

use App\Models\CuotaPrestamoBancario;
use App\Services\PrestamoBancarioService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class CuotasRelationManager extends RelationManager
{
    protected static string $relationship = 'cuotas';

    protected static ?string $title = 'Cronograma de pagos';

    public function table(Table $table): Table
    {
        return $table->defaultSort('Numero')->columns([
            Tables\Columns\TextColumn::make('Numero')->label('N°')->sortable(),
            Tables\Columns\TextColumn::make('FechaVencimiento')->label('F. vcto')->date('d/m/Y')->sortable(),
            Tables\Columns\TextColumn::make('Capital')->money('PEN'),
            Tables\Columns\TextColumn::make('Interes')->label('Interés')->money('PEN'),
            Tables\Columns\TextColumn::make('Comision')->label('Comisión')->money('PEN'),
            Tables\Columns\TextColumn::make('Seguros')->money('PEN'),
            Tables\Columns\TextColumn::make('MontoCuota')->label('Cuota')->money('PEN')->weight('bold'),
            Tables\Columns\TextColumn::make('SaldoDeuda')->label('Saldo deuda')->money('PEN'),
            Tables\Columns\BadgeColumn::make('Estado')->colors([
                'success' => CuotaPrestamoBancario::ESTADO_CANCELADA,
                'warning' => CuotaPrestamoBancario::ESTADO_PENDIENTE,
                'gray' => CuotaPrestamoBancario::ESTADO_ANULADA_ANTICIPADA,
            ])->formatStateUsing(fn (string $state) => match ($state) {
                CuotaPrestamoBancario::ESTADO_CANCELADA => 'Cancelada',
                CuotaPrestamoBancario::ESTADO_PENDIENTE => 'Por pagar',
                CuotaPrestamoBancario::ESTADO_ANULADA_ANTICIPADA => 'Anulada por cancelación anticipada',
                default => $state,
            }),
            Tables\Columns\TextColumn::make('FechaPago')->label('Pagada el')->date('d/m/Y')->placeholder('-'),
        ])->actions([
            Tables\Actions\Action::make('pagar')
                ->label('Pagar cuota')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn (CuotaPrestamoBancario $record) => $record->Estado === CuotaPrestamoBancario::ESTADO_PENDIENTE)
                ->requiresConfirmation()
                ->modalHeading('Confirmar pago de cuota')
                ->modalDescription(fn (CuotaPrestamoBancario $record) => $record->prestamo->EsPrestamoTercero
                    ? 'Ingrese el monto real de esta cuota. Se descontará de Caja Abierta - Gerencia.'
                    : 'Se descontará S/ '.number_format((float) $record->MontoCuota, 2)
                        .' de '.$record->prestamo->FuentePago.' y se registrará como pago completo.')
                ->form([
                    Forms\Components\TextInput::make('Monto')
                        ->label('Monto pagado')
                        ->prefix('S/')
                        ->numeric()
                        ->minValue(0.01)
                        ->default(fn (CuotaPrestamoBancario $record): float => (float) $record->MontoCuota)
                        ->visible(fn (CuotaPrestamoBancario $record): bool => $record->prestamo->EsPrestamoTercero)
                        ->required(fn (CuotaPrestamoBancario $record): bool => $record->prestamo->EsPrestamoTercero),
                    Forms\Components\DatePicker::make('FechaContable')
                        ->label('Fecha contable')
                        ->default(now())
                        ->maxDate(now())
                        ->required(),
                    Forms\Components\TextInput::make('Concepto')->maxLength(255),
                    Forms\Components\Textarea::make('Observaciones')->maxLength(1000),
                ])
                ->action(function (CuotaPrestamoBancario $record, array $data): void {
                    app(PrestamoBancarioService::class)->pagarCuota($record, $data, auth()->id());
                    Notification::make()->success()->title('Pago de cuota registrado correctamente')->send();
                }),
        ])->headerActions([])->bulkActions([]);
    }
}
