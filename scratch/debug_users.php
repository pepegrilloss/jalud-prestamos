<?php
$conn = new mysqli('localhost', 'root', '', 'jalud_prestamos');
$result = $conn->query("
    SELECT u.id, u.name, u.SedeID, u.PromotorCobradorID, 
           s.Nombre as SedeNombre,
           GROUP_CONCAT(r.name SEPARATOR ', ') as roles
    FROM users u 
    LEFT JOIN Sede s ON u.SedeID = s.SedeID 
    LEFT JOIN model_has_roles mhr ON u.id = mhr.model_id 
    LEFT JOIN roles r ON mhr.role_id = r.id 
    GROUP BY u.id 
    ORDER BY u.id
");
echo str_pad("ID", 4) . str_pad("Nombre", 25) . str_pad("SedeID", 8) . str_pad("SedeNombre", 20) . str_pad("PromotorID", 12) . "Roles\n";
echo str_repeat("-", 100) . "\n";
while ($row = $result->fetch_assoc()) {
    echo str_pad($row['id'], 4) . str_pad(substr($row['name']??'',0,24), 25) . str_pad($row['SedeID']??'-', 8) . str_pad(substr($row['SedeNombre']??'-',0,19), 20) . str_pad($row['PromotorCobradorID']??'-', 12) . ($row['roles']??'N/A') . "\n";
}
$conn->close();
