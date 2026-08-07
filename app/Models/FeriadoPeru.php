<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeriadoPeru extends Model
{
    protected $table = 'feriados_peru';

    protected $fillable = [
        'fecha',
        'nombre',
        'anio',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];
}
