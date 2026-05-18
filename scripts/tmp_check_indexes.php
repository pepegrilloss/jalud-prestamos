<?php

$tables = [
    'TasaMora', 'Ciudad', 'Giro', 'SubGiro', 'PromotorCobrador', 
    'Tasa', 'TipoCredito', 'TipoPago', 'NivelAprobacion', 
    'TipoExoneracion', 'TipoComprobanteGasto', 'Zona', 
    'TipoComprobante', 'Motivo'
];

foreach ($tables as $table) {
    echo "Table: $table\n";
    try {
        $indexes = DB::select("SHOW INDEXES FROM $table");
        foreach ($indexes as $index) {
            echo "  Index: {$index->Key_name} (Column: {$index->Column_name}, Non_unique: {$index->Non_unique})\n";
        }
    } catch (Exception $e) {
        echo "  Error: " . $e->getMessage() . "\n";
    }
}
