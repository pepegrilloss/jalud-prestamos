<?php

namespace App\Filament\Resources\CuentaTesoreriaResource\Pages;

use App\Filament\Resources\CuentaTesoreriaResource;
use App\Services\TesoreriaGerenciaService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditCuentaTesoreria extends EditRecord
{
    protected static string $resource = CuentaTesoreriaResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(TesoreriaGerenciaService::class)->actualizarCuenta($record, $data);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
