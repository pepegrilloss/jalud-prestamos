<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PagoBloqueoPromotor extends Model
{
    protected $table = 'pago_bloqueo_promotor';

    protected $fillable = [
        'SedeID',
        'ZonaID',
        'PromotorCobradorID',
        'Activo',
        'UsuarioBloqueoID',
        'UsuarioDesbloqueoID',
    ];

    protected $casts = [
        'Activo' => 'boolean',
    ];

    public static function estaBloqueado(?int $sedeId = null, ?int $zonaId = null, ?int $promotorId = null): bool
    {
        if (!$sedeId) {
            return false;
        }

        // 1. Verificar bloqueo global de promotores en apertura_cierre_dia
        $global = DB::table('apertura_cierre_dia')
            ->where('pagos_promotor_bloqueados', 1)
            ->where('SedeID', $sedeId)
            ->exists();

        if ($global) {
            return true;
        }

        // 2. Verificar bloqueos específicos por zona o promotor
        if (!$zonaId && !$promotorId) {
            return false;
        }

        $query = self::where('SedeID', $sedeId)->where('Activo', true);

        $query->where(function ($q) use ($zonaId, $promotorId) {
            if ($zonaId) {
                $q->orWhere('ZonaID', $zonaId);
            }
            if ($promotorId) {
                $q->orWhere('PromotorCobradorID', $promotorId);
            }
        });

        return $query->exists();
    }

    public static function bloqueosActivos(?int $sedeId = null): array
    {
        if (!$sedeId) {
            return [];
        }

        $bloqueos = self::where('SedeID', $sedeId)
            ->where('Activo', true)
            ->get();

        $resultado = [];
        foreach ($bloqueos as $b) {
            $label = 'Desconocido';
            if ($b->ZonaID) {
                $zona = Zona::find($b->ZonaID);
                $label = 'Zona: ' . ($zona?->Nombre ?? 'ID ' . $b->ZonaID);
            } elseif ($b->PromotorCobradorID) {
                $promotor = PromotorCobrador::find($b->PromotorCobradorID);
                $label = 'Promotor: ' . ($promotor?->Descripcion ?? 'ID ' . $b->PromotorCobradorID);
            }
            $resultado[$b->id] = $label;
        }

        return $resultado;
    }
}
