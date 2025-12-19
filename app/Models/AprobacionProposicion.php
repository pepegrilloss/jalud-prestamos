<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AprobacionProposicion extends Model
{
    protected $table = 'AprobacionProposicion';
    protected $primaryKey = 'AprobacionProposicionID';
    public $timestamps = false;

    protected $fillable = [
        'ProposicionCreditoID',
        'NivelAprobacionID',
        'UserAprobadorID',
        'Estado',
        'Comentario',
        'FechaAprobacion',
        'FechaCreacion',
    ];

    protected $casts = [
        'FechaAprobacion' => 'datetime',
        'FechaCreacion' => 'datetime',
    ];

    // Relaciones
    public function proposicion(): BelongsTo
    {
        return $this->belongsTo(ProposicionCredito::class, 'ProposicionCreditoID', 'ProposicionCreditoID');
    }

    public function nivel(): BelongsTo
    {
        return $this->belongsTo(NivelAprobacion::class, 'NivelAprobacionID', 'NivelAprobacionID');
    }

    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'UserAprobadorID', 'id');
    }

    // Métodos
    public function aprobar(User $usuario, ?string $comentario = null): bool
    {
        if ($this->Estado !== 'PENDIENTE') {
            return false;
        }

        $this->update([
            'Estado' => 'APROBADO',
            'UserAprobadorID' => $usuario->id,
            'FechaAprobacion' => now(),
            'Comentario' => $comentario,
        ]);

        return true;
    }

    public function rechazar(User $usuario, string $comentario): bool
    {
        if ($this->Estado !== 'PENDIENTE') {
            return false;
        }

        $this->update([
            'Estado' => 'RECHAZADO',
            'UserAprobadorID' => $usuario->id,
            'FechaAprobacion' => now(),
            'Comentario' => $comentario,
        ]);

        return true;
    }
}
