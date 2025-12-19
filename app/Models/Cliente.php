<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    protected $table = 'Cliente';
    protected $primaryKey = 'ClienteID';
    public $timestamps = false;

    protected $fillable = [
        'DNI',
        'NombresApellidos',
        'Sexo',
        'FechaNacimiento',
        'Estado',
        'ConyugeDNI',
        'ConyugeNombresApellidos',
        'CiudadID',
        'ZonaID',
        'Domicilio',
        'TasaID',
        'MontoMaxRecomendado',
        'GaranteID',
        'Observaciones',
        'PromotorCobradorID',
        'UsuarioRegistro',
        'UsuarioModificacion',
        'Activo',
    ];

    protected $casts = [
        'FechaNacimiento' => 'date',
        'FechaRegistro' => 'datetime',
        'FechaModificacion' => 'datetime',
        'Activo' => 'boolean',
        'MontoMaxRecomendado' => 'decimal:2',
    ];

    // Relaciones
    public function ciudad(): BelongsTo
    {
        return $this->belongsTo(Ciudad::class, 'CiudadID', 'CiudadID');
    }

    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class, 'ZonaID', 'ZonaID');
    }

    public function tasa(): BelongsTo
    {
        return $this->belongsTo(Tasa::class, 'TasaID', 'TasaID');
    }

    public function promotorCobrador(): BelongsTo
    {
        return $this->belongsTo(PromotorCobrador::class, 'PromotorCobradorID', 'PromotorCobradorID');
    }

    public function negocio(): HasOne
    {
        return $this->hasOne(Negocio::class, 'ClienteID', 'ClienteID');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(DocumentoCliente::class, 'ClienteID', 'ClienteID');
    }

    public function evaluacionRiesgo()
    {
        return $this->hasOne(EvaluacionRiesgo::class, 'ClienteID', 'ClienteID');
    }

    // Helpers para documentos específicos
    public function getDocumentoDNI()
    {
        return $this->documentos()->where('TipoDocumento', 'DNI')->where('Activo', 1)->first();
    }

    public function getDocumentoReciboServicio()
    {
        return $this->documentos()->where('TipoDocumento', 'RECIBO_SERVICIO')->where('Activo', 1)->first();
    }

    public function analisisEconomico()
    {
        return $this->hasOne(AnalisisEconomico::class, 'ClienteID', 'ClienteID')
            ->where('Activo', 1)
            ->latest('FechaAnalisis');
    }

    public function analisisEconomicos()
    {
        return $this->hasMany(AnalisisEconomico::class, 'ClienteID', 'ClienteID');
    }

    // Relación con el garante (autorreferencia)
    public function garante(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'GaranteID', 'ClienteID');
    }

    // Clientes que garantiza este cliente
    public function clientesGarantizados(): HasMany
    {
        return $this->hasMany(Cliente::class, 'GaranteID', 'ClienteID');
    }

    public function proposiciones()
    {
        return $this->hasMany(ProposicionCredito::class, 'ClienteID', 'ClienteID');
    }

    public function evaluacionesCredito()
    {
        return $this->hasMany(EvaluacionCredito::class, 'ClienteID', 'ClienteID')
            ->orderBy('FechaRegistro', 'desc');
    }
}