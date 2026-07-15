<?php

namespace App\Filament\Resources\PrestamoBancarioResource\Pages;

use App\Filament\Resources\PrestamoBancarioResource;
use App\Services\PrestamoBancarioService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePrestamoBancario extends CreateRecord
{
    protected static string $resource = PrestamoBancarioResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(PrestamoBancarioService::class)->crearPrestamo($data);
    }
}
