<?php
/**
 * Configuración central de la aplicación.
 * Las credenciales se cargan desde el archivo .env (nunca commitear .env).
 */

// ── Cargar .env ───────────────────────────────────────────────────────────────
(static function (): void {
    $envFile = dirname(__DIR__) . '/.env';
    if (!file_exists($envFile)) return;
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val, " \t\"'");
        if ($key !== '' && !array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $val;
            putenv("$key=$val");
        }
    }
})();

// ── Entorno ──────────────────────────────────────────────────────────────────
define('APP_ENV',  getenv('APP_ENV')  ?: 'production'); // 'development' | 'production'
define('APP_NAME', 'PPL · Tablero de Atenciones');
define('APP_URL',  getenv('APP_URL')  ?: 'https://tudominio.com');
define('BASE_PATH', dirname(__DIR__));

// ── Base de datos (MySQL/MariaDB – Hostinger) ─────────────────────────────────
define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
define('DB_PORT',    getenv('DB_PORT')    ?: '3306');
define('DB_NAME',    getenv('DB_NAME')    ?: 'tablero_ppl');
define('DB_USER',    getenv('DB_USER')    ?: 'tablero_user');
define('DB_PASS',    getenv('DB_PASS')    ?: 'CambiarEstaContrasena2026!');
define('DB_CHARSET', 'utf8mb4');

// ── Sesión ────────────────────────────────────────────────────────────────────
define('SESSION_LIFETIME', 8 * 3600);   // 8 horas
define('SESSION_NAME',     'PPL_SID');

// ── Seguridad ─────────────────────────────────────────────────────────────────
define('BCRYPT_COST',       12);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_MINUTES',   15);

// ── Almacenamiento de soportes ────────────────────────────────────────────────
// Fuera del webroot para evitar acceso directo a archivos subidos
define('STORAGE_PATH', BASE_PATH . '/storage/soportes');
define('MAX_UPLOAD_MB', 20);
define('ALLOWED_MIME', ['application/pdf', 'image/jpeg', 'image/png', 'image/tiff']);
define('ALLOWED_EXT',  ['pdf', 'jpg', 'jpeg', 'png', 'tif', 'tiff']);

// ── Dominio de correo corporativo ─────────────────────────────────────────────
define('EMAIL_DOMAIN', '@ipsgoleman.com');

// ── Roles (deben coincidir con columna `rol` en BD) ───────────────────────────
define('ROL_ADMINISTRADOR', 0);
define('ROL_FACTURADOR',    1);
define('ROL_EQUIPO_PPL',    2);
define('ROL_ESTADISTICO',   3);

const ROL_NOMBRES = [
    ROL_ADMINISTRADOR => 'Administrador',
    ROL_FACTURADOR    => 'Facturador',
    ROL_EQUIPO_PPL    => 'Equipo PPL',
    ROL_ESTADISTICO   => 'Estadístico',
];

// ── Servicios y paquetes ──────────────────────────────────────────────────────
const TIPOS_SERVICIO = ['VALPS', 'VALPQ', 'PS', 'PQ', 'TF', 'TS'];

const ESTADOS_SOPORTE = [
    0 => 'Pendiente',
    1 => 'Aprobado Automático',
    2 => 'Aprobado Manual',
    3 => 'Rechazado',
    4 => 'Inconsistente',
];

// ── Mostrar errores solo en desarrollo ────────────────────────────────────────
if (APP_ENV === 'development') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}
