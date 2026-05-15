<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\BelongsToSede;

class Cliente extends Model
{
    use BelongsToSede;
    protected $table = 'Cliente';
    protected $primaryKey = 'ClienteID';
    public $timestamps = false;

    protected $fillable = [
        'DNI',
        'NombresApellidos',
        'ApellidoPaterno',
        'ApellidoMaterno',
        'Nombres',
        'Sexo',
        'FechaNacimiento',
        'Estado',
        'ConyugeDNI',
        'ConyugeNombresApellidos',
        'Domicilio',
        'TasaID',
        'TasaMoraID',
        'GaranteID',
        'Observaciones',
        'PromotorCobradorID',
        'UsuarioRegistro',
        'FechaRegistro',
        'UsuarioModificacion',
        'FechaModificacion',
        'FechaCierre',
        'Activo',
        'SedeID',
    ];

    protected $casts = [
        'FechaNacimiento' => 'date',
        'FechaRegistro' => 'datetime',
        'FechaModificacion' => 'datetime',
        'FechaCierre' => 'datetime',
        'Activo' => 'boolean',
    ];

    /**
     * Auto-sincronizar NombresApellidos desde los campos separados al guardar.
     * Formato: "APELLIDO_PATERNO APELLIDO_MATERNO NOMBRES"
     */
    protected static function booted(): void
    {
        static::saving(function (Cliente $cliente) {
            // Solo sincronizar si los campos separados tienen datos
            if ($cliente->ApellidoPaterno || $cliente->ApellidoMaterno || $cliente->Nombres) {
                $cliente->NombresApellidos = strtoupper(trim(
                    trim($cliente->ApellidoPaterno . ' ' . $cliente->ApellidoMaterno) . ' ' . $cliente->Nombres
                ));
            }
        });
    }

    // Relaciones
    public function tasa(): BelongsTo
    {
        return $this->belongsTo(Tasa::class, 'TasaID', 'TasaID');
    }

    public function tasaMora(): BelongsTo
    {
        return $this->belongsTo(TasaMora::class, 'TasaMoraID', 'TasaMoraID');
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
     * Obtener créditos activos con saldo pendiente REAL (mayor a 0)
     * OPTIMIZADO: Filtra por columna SaldoPendiente en SQL (sin N+1)
     */
    public function creditosConSaldo()
    {
        return $this->proposiciones()
            ->where('Activo', true)
            ->where('FueRefinanciada', 0)
            ->where('SaldoPendiente', '>', 0)
            ->whereHas('credito', function ($query) {
                $query->where('Activo', true);
            })
            ->get();
    }

    /**
     * Verificar si el cliente tiene un crédito corriendo (con saldo pendiente REAL mayor a 0)
     */
    public function tieneCreditoCorriendo(): bool
    {
        return $this->creditosConSaldo()->count() > 0;
    }

    /**
     * Obtener el crédito corriendo del cliente (el primero con saldo REAL mayor a 0)
     */
    public function obtenerCreditoCorriendo()
    {
        return $this->creditosConSaldo()->first();
    }
}