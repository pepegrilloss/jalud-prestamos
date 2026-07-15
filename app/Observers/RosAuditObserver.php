<?php

namespace App\Observers;

use App\Models\RosAuditoria;
use App\Models\RosCaso;
use Illuminate\Database\Eloquent\Model;

class RosAuditObserver
{
    public function created(Model $model): void { $this->registrar('CREAR', $model); }
    public function updated(Model $model): void { $this->registrar('ACTUALIZAR', $model); }
    public function deleted(Model $model): void { $this->registrar('ELIMINAR', $model); }

    private function registrar(string $accion, Model $model): void
    {
        $casoId = $model instanceof RosCaso ? $model->getKey() : $model->RosCasoID;

        if (!$casoId || !$model->SedeID) {
            return;
        }

        RosAuditoria::withoutEvents(function () use ($accion, $model, $casoId): void {
            RosAuditoria::create([
                'RosCasoID' => $casoId,
                'SedeID' => $model->SedeID,
                'UserID' => auth()->id(),
                'Accion' => $accion,
                'Modelo' => class_basename($model),
                'ModeloID' => $model->getKey(),
                'IpAddress' => request()?->ip(),
                'created_at' => now(),
            ]);
        });
    }
}
