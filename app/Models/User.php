<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use App\Traits\AprobacionMultiNivel;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, HasRoles, AprobacionMultiNivel;

    protected $fillable = [
        'name',
        'email',
        'password',
        'username',
        'PromotorCobradorID',
        'SedeID',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // MÉTODO NUEVO - REQUERIDO POR FILAMENT
    public function canAccessPanel(Panel $panel): bool
    {
        // Permitir acceso si tiene cualquier rol
        return $this->roles()->exists();

        // O si prefieres ser más específico:
        // return $this->hasAnyRole([\BezhanSalleh\FilamentShield\Support\Utils::getSuperAdminName(), 'admin']);

        // O simplemente permitir a todos los usuarios autenticados:
        // return true;
    }

    public function userNivelAprobacion()
    {
        return $this->hasOne(UserNivelAprobacion::class, 'UserID')->withoutGlobalScope('sede');
    }

    public function nivelAprobacionActivo()
    {
        return $this->hasOne(UserNivelAprobacion::class, 'UserID')
            ->withoutGlobalScope('sede')
            ->where('Activo', true);
    }

    public function getNivelAprobacionActivo()
    {
        return $this->userNivelesAprobacion()
            ->where('Activo', true)
            ->with('nivelAprobacion')
            ->first();
    }

    public function tieneNivelAprobacion($nivelAprobacionID)
    {
        return $this->userNivelesAprobacion()
            ->where('NivelAprobacionID', $nivelAprobacionID)
            ->where('Activo', true)
            ->exists();
    }

    public function tieneNivelAprobacionSuperiorOIgual($nivelAprobacionRequerido)
    {
        $nivelActual = $this->getNivelAprobacionActivo();
        if (!$nivelActual) {
            return false;
        }

        $nivelActualDB = $nivelActual->nivelAprobacion;
        $nivelRequeridoDB = NivelAprobacion::find($nivelAprobacionRequerido);

        if (!$nivelRequeridoDB) {
            return false;
        }

        // Comparar por Orden (mayor orden = mayor jerarquía)
        return $nivelActualDB->Orden >= $nivelRequeridoDB->Orden;
    }

    public function userNivelesAprobacion()
    {
        return $this->hasMany(UserNivelAprobacion::class, 'UserID', 'id')->withoutGlobalScope('sede');
    }

    public function promotorCobrador()
    {
        return $this->belongsTo(\App\Models\PromotorCobrador::class, 'PromotorCobradorID', 'PromotorCobradorID');
    }

    public function sede()
    {
        return $this->belongsTo(\App\Models\Sede::class, 'SedeID', 'SedeID');
    }

    /**
     * Verifica si el usuario es super administrador (acceso a todas las sedes)
     */
    public function esAdmin(): bool
    {
        return $this->hasRole(\BezhanSalleh\FilamentShield\Support\Utils::getSuperAdminName());
    }
}