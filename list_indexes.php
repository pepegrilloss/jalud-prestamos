<?php

$tables = [
    'TasaMora', 'Ciudad', 'Giro', 'SubGiro', 'PromotorCobrador', 
    'Tasa', 'TipoCredito', 'TipoPago', 'NivelAprobacion', 
    'TipoExoneracion', 'TipoComprobanteGasto', 'Zona', 
    'TipoComprobante', 'Motivo'
];

foreach ($tables as $table) {
    try {
        $indexes = DB::select("SHOW INDEXES FROM `$table` ");
        foreach ($indexes as $index) {
            echo "TABLE|$table|INDEX|{$index->Key_name}|COLUMN|{$index->Column_name}|UNIQUE|" . ($index->Non_unique ? "0" : "1") . "\n";
        }
    } catch (Exception $e) {
        echo "ERROR|$table|" . $e->getMessage() . "\n";
    }
}
