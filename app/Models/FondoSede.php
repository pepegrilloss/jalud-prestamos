<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FondoSede extends Model
{
    use HasFactory;

    protected $table = 'fondo_sedes';
    protected $primaryKey = 'FondoSedeID';

    protected $fillable = [
        'SedeID',
        'Saldo',
        'SaldoCajaChica',
    ];

    public function sede()
    {
        return $this->belongsTo(Sede::class, 'SedeID', 'SedeID');
    }
}
