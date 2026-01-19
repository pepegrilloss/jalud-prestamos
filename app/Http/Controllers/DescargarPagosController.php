<?php

namespace App\Http\Controllers;

use App\Models\Credito;
use App\Exports\DescargarPagosCredito;

class DescargarPagosController extends Controller
{
    public function descargar(Credito $credito)
    {
        $descargar = new DescargarPagosCredito();
        return $descargar->descargar($credito);
    }
}
