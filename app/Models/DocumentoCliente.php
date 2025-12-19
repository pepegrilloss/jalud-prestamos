<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DocumentoCliente extends Model
{
    protected $table = 'DocumentoCliente';
    protected $primaryKey = 'DocumentoClienteID';
    public $timestamps = false;

    protected $fillable = [
        'ClienteID',
        'TipoDocumento',
        'RutaArchivo',
        'NombreOriginal',
        'TamanioArchivo',
        'Extension',
        'Observaciones',
        'Activo',
        'UsuarioRegistro',
    ];

    protected $casts = [
        'FechaCreacion' => 'datetime',
        'Activo' => 'boolean',
        'TamanioArchivo' => 'integer',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'ClienteID', 'ClienteID');
    }

    // Obtener URL pública del archivo
    public function getUrlAttribute(): string
    {
        return Storage::url($this->RutaArchivo);
    }

    // Obtener tamaño formateado
    public function getTamanioFormateadoAttribute(): string
    {
        $bytes = $this->TamanioArchivo;
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
}