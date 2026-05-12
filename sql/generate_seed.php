<?php
/**
 * Genera e imprime el SQL de seed con hashes bcrypt reales.
 * Ejecutar UNA VEZ desde CLI:  php sql/generate_seed.php > sql/seed.sql
 * Luego importar seed.sql en MySQL de Hostinger.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/Security.php';

$usuarios = [
    ['admin',       'Admin2026!',       0],
    ['facturador',  'Facturador2026!',  1],
    ['equipoppl',   'EquipoPPL2026!',   2],
    ['estadistico', 'Estadistico2026!', 3],
];

echo "-- Seed de usuarios iniciales - generado el " . date('Y-m-d H:i:s') . "\n";
echo "-- ¡ELIMINAR ESTE ARCHIVO del servidor después de importarlo!\n\n";

foreach ($usuarios as [$user, $pass, $rol]) {
    $hash = Security::hashPassword($pass);
    echo "INSERT IGNORE INTO Usuarios (nombre_usuario, password_hash, rol, activo, cambio_password_req)\n";
    echo "  VALUES ('$user', '$hash', $rol, 1, 0);\n";
}
