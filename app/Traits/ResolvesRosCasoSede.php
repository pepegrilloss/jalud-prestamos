<?php

namespace App\Traits;

use App\Models\RosCaso;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

trait ResolvesRosCasoSede
{
    public static function bootResolvesRosCasoSede(): void
    {
        static::saving(function (Model $model): void {
            if (empty($model->RosCasoID)) {
                return;
            }

            $sedeId = RosCaso::withoutGlobalScope('sede')
                ->whereKey($model->RosCasoID)
                ->value('SedeID');

            if (!$sedeId) {
                throw ValidationException::withMessages([
                    'RosCasoID' => 'El caso ROS relacionado no existe.',
                ]);
            }

            $model->SedeID = $sedeId;
        });
    }
}
