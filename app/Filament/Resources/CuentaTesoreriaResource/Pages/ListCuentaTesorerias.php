<?php

namespace App\Filament\Resources\CuentaTesoreriaResource\Pages;

use App\Filament\Resources\CuentaTesoreriaResource;
use App\Services\TesoreriaGerenciaService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListCuentaTesorerias extends ListRecords
{
    protected static string $resource = CuentaTesoreriaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nueva cuenta bancaria')
                ->form([
                    Forms\Components\TextInput::make('Banco')->required()->maxLength(100),
                    Forms\Components\TextInput::make('NumeroCuenta')->label('Numero de cuenta')->required()->maxLength(100)->unique('tesoreria_cuentas', 'NumeroCuenta'),
                    Forms\Components\TextInput::make('SaldoInicial')->label('Saldo inicial')->numeric()->minValue(0)->default(0)->prefix('S/')->required(),
                    Forms\Components\DatePicker::make('FechaContable')->label('Fecha contable de apertura')->default(now())->maxDate(now())->required(),
                    Forms\Components\Textarea::make('Observaciones')->label('Observaciones')->maxLength(1000),
                ])
                ->using(fn (array $data, TesoreriaGerenciaService $service) => $service->crearCuentaBancaria($data, auth()->id()))
                ->successNotification(Notification::make()->success()->title('Cuenta bancaria creada')),
        ];
    }
}
