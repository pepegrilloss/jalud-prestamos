<?php

namespace App\Http\Controllers;

use App\Models\Credito;
use Barryvdh\DomPDF\Facade\Pdf;

class TicketDescargarController extends Controller
{
    public function descargar($creditoID)
    {
        $credito = Credito::findOrFail($creditoID);
        $proposicion = $credito->proposicion;
        $cliente = $proposicion->cliente;
        $promotorCobrador = $cliente->promotorCobrador;
        $zona = $cliente->negocio->zona->Nombre ?? 'N/A';

        $fecha = $credito->FechaGeneracion->format('d/m/Y');
        $monto = number_format($proposicion->MontoTotal, 2, '.', ',');
        $plazo = $proposicion->Plazo;
        $nombreCliente = $cliente->NombresApellidos;
        $dni = $cliente->DNI;
        $telefono = $promotorCobrador->Telefono ?? '';

        // Dividir nombres en apellidos y nombres
        $partes = explode(' ', $nombreCliente);
        $apellidos = isset($partes[0]) ? $partes[0] : '';
        $nombres = isset($partes[1]) ? implode(' ', array_slice($partes, 1)) : '';

        $pdf = Pdf::loadView('ticket.recibo', [
            'fecha' => $fecha,
            'apellidos' => $apellidos,
            'nombres' => $nombres,
            'monto' => $monto,
            'plazo' => $plazo,
            'telefono' => $telefono,
            'firma' => '',
            'zona' => $zona,
            'dni' => $dni,
            'observacion' => '',
        ]);

        $pdf->setPaper('A6', 'portrait');

        return $pdf->stream('ticket_' . $creditoID . '.pdf');
    }
}
