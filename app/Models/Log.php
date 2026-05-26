<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToSede;

class Log extends Model
{
    use BelongsToSede;
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
        'created_at',
        'SedeID',
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
        // SEGURIDAD: Sanitizar datos sensibles antes de guardar en logs
        $oldValues = self::sanitizarValoresSensibles($oldValues);
        $newValues = self::sanitizarValoresSensibles($newValues);

        return self::create([
            'user_id' => auth()->id() ?? 0,
            'accion' => $accion,
            'modelo' => $modelo,
            'modelo_id' => $modeloId,
            'old_values' => $oldValues ? json_encode($oldValues) : null,
            'new_values' => $newValues ? json_encode($newValues) : null,
            'ip_address' => self::normalizarIp(request()->ip() ?? request()->header('X-Forwarded-For') ?? '127.0.0.1'),
            'user_agent' => request()->userAgent(),
            'machine_name' => php_uname('n') ?: gethostname() ?: null,
            'platform' => PHP_OS_FAMILY,
            'created_at' => now()
        ]);
    }

    /**
     * Sanitizar campos sensibles antes de guardar en auditoria
     * SEGURIDAD: No guardar datos personales sin encriptación
     */
    private static function sanitizarValoresSensibles($values)
    {
        if (!is_array($values)) {
            return $values;
        }

        $camposSensibles = [
            'password',
            'token',
            'secret',
            'DNI',
            'dni',
            'Dni',
            'numero_documento',
            'NumeroDocumento',
            'phone',
            'Telefono',
            'telefono',
            'email',
            'correo',
            'Correo',
            'tarjeta',
            'Tarjeta',
            'cuenta_banco',
            'CuentaBanco',
            'numero_cuenta',
            'NumeroCuenta'
        ];

        foreach ($camposSensibles as $campo) {
            if (isset($values[$campo])) {
                $values[$campo] = '[REDACTED]'; // Marcar como datos sensibles
            }
        }

        return $values;
    }

    private static function normalizarIp(?string $ip): ?string
    {
        if ($ip === '::1' || $ip === '::ffff:127.0.0.1') {
            return '127.0.0.1';
        }
        return $ip;
    }
}
