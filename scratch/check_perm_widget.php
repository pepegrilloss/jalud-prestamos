<?php
$pdo = new PDO('mysql:host=localhost;dbname=jvcso1ub_jalud_prestamos', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== PERMISSION ===\n";
$r = $pdo->query("SELECT id, name FROM permissions WHERE name LIKE '%MontoPropuesto%'");
while ($row = $r->fetch()) {
    echo 'ID=' . $row['id'] . ' Name=' . $row['name'] . "\n";
}
if ($r->rowCount() == 0) echo "NOT FOUND\n";

echo "\n=== ROLE HAS PERMISSION? ===\n";
$r = $pdo->query("SELECT r.id, r.name, p.name as perm FROM role_has_permissions rp JOIN roles r ON rp.role_id = r.id JOIN permissions p ON rp.permission_id = p.id WHERE p.name LIKE '%MontoPropuesto%'");
while ($row = $r->fetch()) {
    echo 'Role=' . $row['name'] . ' Perm=' . $row['perm'] . "\n";
}
if ($r->rowCount() == 0) echo "NOT ASSIGNED TO ANY ROLE\n";

echo "\n=== USER 1 ROLES ===\n";
$r = $pdo->query("SELECT r.name FROM roles r JOIN model_has_roles mr ON r.id = mr.role_id WHERE mr.model_id = 1");
while ($row = $r->fetch()) {
    echo $row['name'] . "\n";
}
