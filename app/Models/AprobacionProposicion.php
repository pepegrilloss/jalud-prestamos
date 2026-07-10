<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToSede;

class AprobacionProposicion extends Model
{
    use BelongsToSede;
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
        'SedeID',
    ];

    protected $casts = [
        'FechaAprobacion' => 'datetime',
        'FechaCreacion' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (AprobacionProposicion $aprobacion) {
            if (!$aprobacion->ProposicionCreditoID) {
                return;
            }

            $proposicionSedeId = ProposicionCredito::withoutGlobalScope('sede')
                ->where('ProposicionCreditoID', $aprobacion->ProposicionCreditoID)
                ->value('SedeID');

            if (!$proposicionSedeId) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'ProposicionCreditoID' => 'No se encontro la proposicion de la aprobacion.',
                ]);
            }

            if (empty($aprobacion->SedeID)) {
                $aprobacion->SedeID = $proposicionSedeId;
            }

            if ((int) $aprobacion->SedeID !== (int) $proposicionSedeId) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'SedeID' => 'No se puede guardar una aprobacion en una sede distinta a su proposicion.',
                ]);
            }
        });
    }

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

        $fechaAbierta = \App\Services\DateFieldResolver::getFechaAbierta();
        $fechaAprobacion = $fechaAbierta ? $fechaAbierta->copy()->setTime(now()->hour, now()->minute, now()->second) : now();

        $this->update([
            'Estado' => 'APROBADO',
            'UserAprobadorID' => $usuario->id,
            'FechaAprobacion' => $fechaAprobacion,
            'Comentario' => $comentario,
        ]);

        return true;
    }

    public function rechazar(User $usuario, string $comentario): bool
    {
        if ($this->Estado !== 'PENDIENTE') {
            return false;
        }

        $fechaAbierta = \App\Services\DateFieldResolver::getFechaAbierta();
        $fechaAprobacion = $fechaAbierta ? $fechaAbierta->copy()->setTime(now()->hour, now()->minute, now()->second) : now();

        $this->update([
            'Estado' => 'RECHAZADO',
            'UserAprobadorID' => $usuario->id,
            'FechaAprobacion' => $fechaAprobacion,
            'Comentario' => $comentario,
        ]);

        return true;
    }
}
