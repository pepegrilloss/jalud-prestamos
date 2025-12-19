<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TipoCreditoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->TipoCreditoID,
            'codigo' => $this->Codigo,
            'descripcion' => $this->Descripcion,
            'activo' => $this->Activo,
            'fechaCreacion' => $this->FechaCreacion,
            'fechaModificacion' => $this->FechaModificacion
        ];
    }
}
