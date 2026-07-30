<?php

namespace App\Filament\Resources\PrestamoBancarioResource\Pages;

use App\Filament\Resources\PrestamoBancarioResource;
use App\Models\CuentaTesoreria;
use App\Models\PrestamoBancario;
use App\Services\PrestamoBancarioService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewPrestamoBancario extends ViewRecord
{
    protected static string $resource = PrestamoBancarioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('configurarCuentaPago')
                ->label('Configurar origen de pago')
                ->icon('heroicon-o-credit-card')
                ->visible(fn () => $this->record->Estado === PrestamoBancario::ESTADO_VIGENTE)
                ->fillForm(fn () => ['CuentaTesoreriaID' => $this->record->CuentaTesoreriaID])
                ->form([
                    Forms\Components\Select::make('CuentaTesoreriaID')
                        ->label('Cuenta bancaria de débito')
                        ->options(fn () => CuentaTesoreria::query()
                            ->where('Banco', $this->record->NombreBanco)
                            ->where('Estado', CuentaTesoreria::ESTADO_ACTIVA)
                            ->orderBy('NumeroCuenta')
                            ->pluck('NumeroCuenta', 'CuentaTesoreriaID'))
                        ->searchable()
                        ->placeholder('Caja Abierta - Gerencia')
                        ->helperText('Deje el campo vacío para pagar desde Caja Abierta - Gerencia.'),
                ])
                ->action(function (array $data): void {
                    app(PrestamoBancarioService::class)->configurarCuentaPago(
                        $this->record,
                        filled($data['CuentaTesoreriaID'] ?? null) ? (int) $data['CuentaTesoreriaID'] : null
                    );
                    $this->record->refresh();
                    Notification::make()->success()->title('Origen de pago actualizado')->send();
                }),
            Actions\Action::make('cancelarAnticipadamente')
                ->label('Cancelar anticipadamente')
                ->icon('heroicon-o-forward')
                ->color('warning')
                ->visible(fn () => $this->record->Estado === PrestamoBancario::ESTADO_VIGENTE
                    && $this->record->CapitalPendiente > 0)
                ->requiresConfirmation()
                ->modalHeading('Cancelar préstamo anticipadamente')
                ->modalDescription(fn () => 'Se amortizará únicamente el capital pendiente de S/ '
                    .number_format($this->record->CapitalPendiente, 2)
                    .' desde '.$this->record->FuentePago
                    .'. Las cuotas pendientes quedarán anuladas, no pagadas.')
                ->form([
                    Forms\Components\DatePicker::make('FechaContable')
                        ->label('Fecha contable')
                        ->default(now())
                        ->maxDate(now())
                        ->required(),
                    Forms\Components\TextInput::make('Concepto')
                        ->default('Cancelación anticipada de capital')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Textarea::make('Observaciones')->maxLength(1000),
                ])
                ->action(function (array $data): void {
                    app(PrestamoBancarioService::class)->cancelarAnticipadamente(
                        $this->record,
                        $data,
                        auth()->id()
                    );
                    $this->record->refresh();
                    Notification::make()->success()->title('Préstamo cancelado anticipadamente')->send();
                }),
        ];
    }
}
