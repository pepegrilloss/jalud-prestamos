<?php

namespace App\Models;

use App\Traits\BelongsToSede;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RosAuditoria extends Model
{
    use BelongsToSede;

    protected $table = 'ros_auditorias';
    protected $primaryKey = 'RosAuditoriaID';
    public $timestamps = false;
    protected $fillable = ['RosCasoID', 'SedeID', 'UserID', 'Accion', 'Modelo', 'ModeloID', 'IpAddress', 'created_at'];
    protected $casts = ['created_at' => 'datetime'];

    public function caso(): BelongsTo { return $this->belongsTo(RosCaso::class, 'RosCasoID', 'RosCasoID'); }
    public function usuario(): BelongsTo { return $this->belongsTo(User::class, 'UserID'); }
}
