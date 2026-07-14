<?php

namespace App\Observers;

use App\Models\Log;

class AuditObserver
{
    public function created($model)
    {
        if ($this->shouldAudit($model)) {
            Log::registrar(
                'CREAR',
                class_basename($model),
                $model->getKey(),
                null,
                $model->toArray(),
                $this->resolveSedeId($model)
            );
        }
    }

    public function updated($model)
    {
        if ($this->shouldAudit($model)) {
            $changes = $model->getChanges();
            $original = $model->getOriginal();
            
            $oldValues = [];
            foreach ($changes as $key => $value) {
                $oldValues[$key] = $original[$key] ?? null;
            }

            Log::registrar(
                'ACTUALIZAR',
                class_basename($model),
                $model->getKey(),
                $oldValues,
                $changes,
                $this->resolveSedeId($model)
            );
        }
    }

    public function deleted($model)
    {
        if ($this->shouldAudit($model)) {
            Log::registrar(
                'ELIMINAR',
                class_basename($model),
                $model->getKey(),
                $model->toArray(),
                null,
                $this->resolveSedeId($model)
            );
        }
    }

    private function shouldAudit($model)
    {
        // No auditar los logs mismos
        if (class_basename($model) === 'Log') {
            return false;
        }
        return true;
    }

    private function resolveSedeId($model): ?int
    {
        return isset($model->SedeID) ? (int) $model->SedeID : null;
    }
}
