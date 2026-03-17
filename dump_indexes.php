<?php

$tables = [
    'TasaMora', 'Ciudad', 'Giro', 'SubGiro', 'PromotorCobrador', 
    'Tasa', 'TipoCredito', 'TipoPago', 'NivelAprobacion', 
    'TipoExoneracion', 'TipoComprobanteGasto', 'Zona', 
    'TipoComprobante', 'Motivo'
];

$output = [];
foreach ($tables as $table) {
    try {
        $indexes = DB::select("SHOW INDEXES FROM `$table` ");
        $output[$table] = $indexes;
    } catch (Exception $e) {
        $output[$table] = ['error' => $e->getMessage()];
    }
}

file_put_contents('c:\xampp\htdocs\jalud-prestamos\db_indexes.json', json_encode($output, JSON_PRETTY_PRINT));
echo "DONE\n";
