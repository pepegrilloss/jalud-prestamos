<?php

namespace App\Traits;

use App\Services\DateFieldResolver;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait para inyectar automáticamente la fecha del día abierto
 * en los formularios de Filament
 */
trait AutoFechaAbierta
{
    /**
     * Inyecta la fecha abierta antes de crear un registro
     * 
     * @param array $data
     * @return array
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return DateFieldResolver::injectFechaAbierta($data, $this->getModel());
    }

    /**
     * Inyecta la fecha abierta en la creación desde CreateAction
     * Si el Resource define una clase Page personalizada, este método
     * puede no ser necesario. Este es para fallback.
     * 
     * @param array $data
     * @return Model
     */
    protected function mutateBeforeCreate(array $data): array
    {
        return DateFieldResolver::injectFechaAbierta($data, $this->getModel());
    }
}
