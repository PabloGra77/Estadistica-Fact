<?php
/**
 * Configuración central — editar directamente en el servidor.
 * Este archivo NO se sube a GitHub (está en .gitignore).
 * ─────────────────────────────────────────────────────────
 * 1. Copia config.example.php como config.php en el servidor
 * 2. Rellena los datos de DB con los de hPanel → Bases de datos
 * 3. Guarda y listo
 */

define('BASE_PATH', dirname(__DIR__));
define('APP_NAME',  'PPL · Tablero de Atenciones');

// ── Base de datos ─────────────────────────────────────────────────────────────
// Rellenar con los datos de hPanel → Bases de datos MySQL
define('DB_HOST',    'localhost');
define('DB_PORT',    '3306');
define('DB_NAME',    '');   // ← nombre de la base de datos
define('DB_USER',    '');   // ← usuario de la base de datos
define('DB_PASS',    '');   // ← contraseña
define('DB_CHARSET', 'utf8mb4');

// ── URL del sitio ─────────────────────────────────────────────────────────────
define('APP_URL', 'https://tudominio.com');   // ← tu dominio real

// ── Sesión ────────────────────────────────────────────────────────────────────
define('SESSION_LIFETIME', 8 * 3600);
define('SESSION_NAME',     'PPL_SID');

// ── Seguridad ─────────────────────────────────────────────────────────────────
define('BCRYPT_COST',        12);
define('MAX_LOGIN_ATTEMPTS',  5);
define('LOCKOUT_MINUTES',    15);

// ── Almacenamiento de soportes (fuera del webroot) ────────────────────────────
define('STORAGE_PATH', BASE_PATH . '/storage/soportes');
define('INBOX_PATH',   BASE_PATH . '/storage/inbox');
define('MAX_UPLOAD_MB', 200);
define('ALLOWED_MIME', ['application/pdf', 'image/jpeg', 'image/png', 'image/tiff']);
define('ALLOWED_EXT',  ['pdf', 'jpg', 'jpeg', 'png', 'tif', 'tiff']);

// ── Dominio de correo corporativo ─────────────────────────────────────────────
define('EMAIL_DOMAIN', '@ipsgoleman.com');

// ── Roles ─────────────────────────────────────────────────────────────────────
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

// ── Catálogos ─────────────────────────────────────────────────────────────────
const TIPOS_SERVICIO = ['VALPS','VALPQ','PS','PQ','TF','TS','TZ','PYP','MED','REG'];

const ESTADOS_SOPORTE = [
    0 => 'Pendiente',
    1 => 'Aprobado Automático',
    2 => 'Aprobado Manual',
    3 => 'Rechazado',
    4 => 'Inconsistente',
];

// ── Mostrar errores solo en desarrollo local ──────────────────────────────────
// Cambiar a false en producción (Hostinger)
define('APP_DEBUG', false);

if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}


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
define('INBOX_PATH',   BASE_PATH . '/storage/inbox');
define('MAX_UPLOAD_MB', 200);
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
const TIPOS_SERVICIO = ['VALPS','VALPQ','PS','PQ','TF','TS','TZ','PYP','MED','REG'];

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
