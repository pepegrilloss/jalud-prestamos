<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Log extends Model
{
    protected $table = 'logs';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'accion',
        'modelo',
        'modelo_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'machine_name',
        'platform',
        'created_at'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'old_values' => 'array',
        'new_values' => 'array'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public static function registrar($accion, $modelo, $modeloId = null, $oldValues = null, $newValues = null)
    {
        return self::create([
            'user_id' => auth()->id() ?? 0,
            'accion' => $accion,
            'modelo' => $modelo,
            'modelo_id' => $modeloId,
            'old_values' => $oldValues ? json_encode($oldValues) : null,
            'new_values' => $newValues ? json_encode($newValues) : null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'machine_name' => gethostname(),
            'platform' => PHP_OS_FAMILY,
            'created_at' => now()
        ]);
    }
}
