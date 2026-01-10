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
        'Domicilio',
        'TasaID',
        'GaranteID',
        'Observaciones',
        'PromotorCobradorID',
        'UsuarioRegistro',
        'FechaRegistro',
        'UsuarioModificacion',
        'FechaModificacion',
        'Activo',
    ];

    protected $casts = [
        'FechaNacimiento' => 'date',
        'FechaRegistro' => 'datetime',
        'FechaModificacion' => 'datetime',
        'Activo' => 'boolean',
    ];

    // Relaciones
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

    /**
     * Obtener créditos activos con saldo pendiente
     */
    public function creditosConSaldo()
    {
        return $this->proposiciones()
            ->whereHas('credito', function ($query) {
                $query->where('Activo', true);
            })
            ->with(['credito.cuotas' => function ($query) {
                $query->where('Activo', true);
            }])
            ->get()
            ->filter(function ($proposicion) {
                if (!$proposicion->credito) {
                    return false;
                }
                // Verificar si hay cuotas pendientes
                return $proposicion->credito->cuotas()
                    ->where('Activo', true)
                    ->where('Estado', '!=', 'PAGADA')
                    ->exists();
            });
    }

    /**
     * Verificar si el cliente tiene un crédito corriendo (con saldo pendiente)
     */
    public function tieneCreditoCorriendo(): bool
    {
        return $this->creditosConSaldo()->count() > 0;
    }

    /**
     * Obtener el crédito corriendo del cliente (el primero con saldo)
     */
    public function obtenerCreditoCorriendo()
    {
        return $this->creditosConSaldo()->first();
    }
}