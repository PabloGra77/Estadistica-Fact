<?php
/**
 * Front Controller — enruta todas las peticiones.
 * Compatible con el .htaccess de Hostinger.
 */

// Detectar si index.php está en public/ (dev) o en la raíz (producción Hostinger)
$_configPath = file_exists(__DIR__ . '/../config/config.php')
    ? __DIR__ . '/../config/config.php'
    : __DIR__ . '/config/config.php';
require_once $_configPath;
require_once BASE_PATH . '/app/Core/Database.php';
require_once BASE_PATH . '/app/Core/Security.php';
require_once BASE_PATH . '/app/Core/Auth.php';

// ── Cabeceras de seguridad ────────────────────────────────────────────────────
Security::secureHeaders();
header('Content-Type: text/html; charset=UTF-8');

// ── Sesión ────────────────────────────────────────────────────────────────────
Auth::startSession();

// ── Enrutador simple ──────────────────────────────────────────────────────────
$uri    = strtok($_SERVER['REQUEST_URI'], '?');
$uri    = '/' . trim(parse_url($uri, PHP_URL_PATH), '/');
$method = $_SERVER['REQUEST_METHOD'];

// Normalizar path base si la app está en un subdirectorio
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if ($basePath !== '' && str_starts_with($uri, $basePath)) {
    $uri = substr($uri, strlen($basePath)) ?: '/';
}

// ── Rutas ─────────────────────────────────────────────────────────────────────
match (true) {

    // Auth
    $uri === '/' && $method === 'GET'                       => require BASE_PATH . '/app/Controllers/HomeController.php',
    $uri === '/login' && $method === 'GET'                  => require BASE_PATH . '/app/Controllers/AuthController.php',
    $uri === '/login' && $method === 'POST'                 => require BASE_PATH . '/app/Controllers/AuthController.php',
    $uri === '/logout' && $method === 'POST'                => require BASE_PATH . '/app/Controllers/AuthController.php',

    // Pacientes
    $uri === '/pacientes' && $method === 'GET'              => require BASE_PATH . '/app/Controllers/PacientesController.php',
    $uri === '/pacientes/exportar' && $method === 'GET'     => require BASE_PATH . '/app/Controllers/PacientesController.php',
    $uri === '/pacientes/importar' && $method === 'GET'     => require BASE_PATH . '/app/Controllers/PacientesController.php',
    $uri === '/pacientes/importar' && $method === 'POST'    => require BASE_PATH . '/app/Controllers/PacientesController.php',
    $uri === '/pacientes/importar/plantilla' && $method === 'GET' => require BASE_PATH . '/app/Controllers/PacientesController.php',
    $uri === '/pacientes/crear' && $method === 'GET'        => require BASE_PATH . '/app/Controllers/PacientesController.php',
    $uri === '/pacientes/crear' && $method === 'POST'       => require BASE_PATH . '/app/Controllers/PacientesController.php',
    preg_match('#^/pacientes/(\d+)/editar$#', $uri, $m) === 1 && $method === 'GET'  => require BASE_PATH . '/app/Controllers/PacientesController.php',
    preg_match('#^/pacientes/(\d+)/editar$#', $uri, $m) === 1 && $method === 'POST' => require BASE_PATH . '/app/Controllers/PacientesController.php',

    // Atenciones
    $uri === '/atenciones' && $method === 'GET'                  => require BASE_PATH . '/app/Controllers/AtencionesController.php',
    $uri === '/atenciones/exportar-excel' && $method === 'GET'   => require BASE_PATH . '/app/Controllers/AtencionesController.php',
    $uri === '/atenciones/crear' && $method === 'GET'            => require BASE_PATH . '/app/Controllers/AtencionesController.php',
    $uri === '/atenciones/crear' && $method === 'POST'           => require BASE_PATH . '/app/Controllers/AtencionesController.php',

    // Soportes
    $uri === '/soportes' && $method === 'GET'                       => require BASE_PATH . '/app/Controllers/SoportesController.php',
    $uri === '/soportes/subir' && $method === 'GET'                 => require BASE_PATH . '/app/Controllers/SoportesController.php',
    $uri === '/soportes/subir' && $method === 'POST'                => require BASE_PATH . '/app/Controllers/SoportesController.php',
    $uri === '/soportes/importar-zip' && $method === 'GET'          => require BASE_PATH . '/app/Controllers/SoportesController.php',
    $uri === '/soportes/importar-zip' && $method === 'POST'         => require BASE_PATH . '/app/Controllers/SoportesController.php',
    preg_match('#^/soportes/(\d+)/descargar$#', $uri, $m) === 1 => require BASE_PATH . '/app/Controllers/SoportesController.php',
    preg_match('#^/soportes/(\d+)/auditar$#',   $uri, $m) === 1 && $method === 'POST' => require BASE_PATH . '/app/Controllers/SoportesController.php',

    // Admin - usuarios
    $uri === '/admin/usuarios' && $method === 'GET'         => require BASE_PATH . '/app/Controllers/AdminController.php',
    $uri === '/admin/usuarios/crear' && $method === 'GET'   => require BASE_PATH . '/app/Controllers/AdminController.php',
    $uri === '/admin/usuarios/crear' && $method === 'POST'  => require BASE_PATH . '/app/Controllers/AdminController.php',
    preg_match('#^/admin/usuarios/(\d+)/editar$#', $uri, $m) === 1 && $method === 'POST' => require BASE_PATH . '/app/Controllers/AdminController.php',
    preg_match('#^/admin/usuarios/(\d+)/toggle$#', $uri, $m) === 1 && $method === 'POST' => require BASE_PATH . '/app/Controllers/AdminController.php',

    // Admin - períodos
    $uri === '/admin/periodos' && $method === 'GET'                                             => require BASE_PATH . '/app/Controllers/AdminController.php',
    $uri === '/admin/periodos/crear' && $method === 'POST'                                      => require BASE_PATH . '/app/Controllers/AdminController.php',
    preg_match('#^/admin/periodos/(\d+)/activar$#',   $uri, $m) === 1 && $method === 'POST'    => require BASE_PATH . '/app/Controllers/AdminController.php',
    preg_match('#^/admin/periodos/(\d+)/cerrar$#',    $uri, $m) === 1 && $method === 'POST'    => require BASE_PATH . '/app/Controllers/AdminController.php',
    preg_match('#^/admin/periodos/(\d+)/pacientes$#', $uri, $m) === 1 && $method === 'GET'     => require BASE_PATH . '/app/Controllers/AdminController.php',
    preg_match('#^/admin/periodos/(\d+)/pacientes$#', $uri, $m) === 1 && $method === 'POST'    => require BASE_PATH . '/app/Controllers/AdminController.php',

    // 404
    default => (function () {
        http_response_code(404);
        require BASE_PATH . '/app/Views/errors/404.php';
    })()
};
