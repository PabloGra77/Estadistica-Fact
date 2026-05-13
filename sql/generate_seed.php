<?php
/**
 * Genera el SQL de seed con hashes bcrypt.
 *
 * USO:  php sql/generate_seed.php > sql/seed.sql
 *       mysql -u usuario -p tablero_ppl < sql/seed.sql
 *
 * Las contrasenas se pasan como argumentos o se piden por pantalla:
 *   php sql/generate_seed.php adminPass factPass pplPass statsPass
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/app/Core/Security.php';

$args = array_slice($argv ?? [], 1);

$usuarios = [
    ['soporte.360', 0, 'Contrasena del administrador (soporte.360)'],
    ['facturador',  1, 'Contrasena del facturador'],
    ['equipoppl',   2, 'Contrasena del equipo PPL'],
    ['estadistico', 3, 'Contrasena del estadistico'],
];

$passwords = [];
foreach ($usuarios as $i => [$user, $rol, $prompt]) {
    if (isset($args[$i]) && $args[$i] !== '') {
        $passwords[$i] = $args[$i];
    } else {
        fwrite(STDOUT, "{$prompt}: ");
        $pw = trim(fgets(STDIN));
        if ($pw === '') {
            fwrite(STDERR, "Error: contrasena vacia para {$user}.\n");
            exit(1);
        }
        $passwords[$i] = $pw;
    }
}

echo "-- Seed generado el " . date('Y-m-d H:i:s') . "\n";
echo "-- ELIMINAR este archivo del servidor despues de importarlo\n\n";

foreach ($usuarios as $i => [$user, $rol, $_]) {
    $hash = Security::hashPassword($passwords[$i]);
    echo "INSERT IGNORE INTO Usuarios (nombre_usuario, password_hash, rol, activo, cambio_password_req)\n";
    echo "  VALUES ('{$user}', '{$hash}', {$rol}, 1, 1);\n";
}
