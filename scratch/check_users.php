<?php
$pdo = new PDO('mysql:host=localhost;dbname=jvcso1ub_jalud_prestamos', 'root', '');
echo "=== USERS ===" . PHP_EOL;
$q = $pdo->query("SELECT u.id, u.name, u.SedeID, u.PromotorCobradorID, u.email FROM users u WHERE u.name LIKE '%vilcherrez%' OR u.name LIKE '%vasquez%'");
foreach ($q as $row) {
    echo json_encode($row) . PHP_EOL;
}
echo PHP_EOL . "=== ALL USERS (solo SedeID) ===" . PHP_EOL;
$q2 = $pdo->query("SELECT id, name, SedeID FROM users ORDER BY id");
foreach ($q2 as $row) {
    echo "ID=" . $row['id'] . " Name=" . $row['name'] . " SedeID=" . ($row['SedeID'] ?? 'NULL') . PHP_EOL;
}
echo PHP_EOL . "=== PROMOTOR COBRADOR ===" . PHP_EOL;
$q3 = $pdo->query("SELECT * FROM PromotorCobrador");
foreach ($q3 as $row) {
    echo json_encode($row) . PHP_EOL;
}
echo PHP_EOL . "=== ROLES ===" . PHP_EOL;
$q4 = $pdo->query("SELECT u.id, u.name, r.name as role FROM users u JOIN model_has_roles mhr ON u.id = mhr.model_id JOIN roles r ON mhr.role_id = r.id WHERE u.name LIKE '%vilcherrez%' OR u.name LIKE '%vasquez%'");
foreach ($q4 as $row) {
    echo json_encode($row) . PHP_EOL;
}
echo PHP_EOL . "=== ALL ROLES ===" . PHP_EOL;
$q5 = $pdo->query("SELECT id, name, CONCAT('\"', name, '\"') as exact, LENGTH(name) as len FROM roles ORDER BY id");
foreach ($q5 as $row) {
    echo "ID=" . $row['id'] . " Exact=" . $row['exact'] . " Len=" . $row['len'] . PHP_EOL;
}
