<?php
/**
 * Gestión de autenticación y sesión.
 */
class Auth
{
    // ── Sesión ────────────────────────────────────────────────────────────────

    public static function startSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_name(SESSION_NAME);
            session_set_cookie_params([
                'lifetime' => 0,                   // Cookie de sesión (se borra al cerrar browser)
                'path'     => '/',
                'secure'   => (APP_ENV === 'production'), // Solo HTTPS en producción
                'httponly' => true,                // Inaccesible a JavaScript
                'samesite' => 'Strict',            // Protección CSRF adicional
            ]);
            session_start();

            // Regenerar ID si lleva más de 30 min sin rotación
            if (isset($_SESSION['_last_regen'])) {
                if (time() - $_SESSION['_last_regen'] > 1800) {
                    session_regenerate_id(true);
                    $_SESSION['_last_regen'] = time();
                }
            } else {
                $_SESSION['_last_regen'] = time();
            }
        }
    }

    // ── Login / logout ────────────────────────────────────────────────────────

    /**
     * Intenta autenticar al usuario.
     * Devuelve ['ok' => bool, 'error' => string].
     */
    public static function login(string $usuario, string $password): array
    {
        $usuario = mb_strtolower(trim($usuario));

        // Evitar enumeración — siempre mismo tiempo de respuesta
        $row = Database::fetchOne(
            "SELECT id, nombre_usuario, password_hash, rol, activo,
                    intentos_fallidos, bloqueo_hasta
             FROM Usuarios WHERE nombre_usuario = ?",
            [$usuario]
        );

        // Si el usuario no existe, hacer un hash vacío igual para timing constante
        $hashFake = '$2y$12$fakehashfakehashfakehashe';
        $hashCheck = $row['password_hash'] ?? $hashFake;
        $passwordOk = Security::verifyPassword($password, $hashCheck);

        if (!$row) {
            return ['ok' => false, 'error' => '1'];
        }

        if (!(bool)$row['activo']) {
            return ['ok' => false, 'error' => '1'];
        }

        // Verificar bloqueo por intentos fallidos
        if ($row['bloqueo_hasta'] !== null) {
            $bloqueoTs = strtotime($row['bloqueo_hasta']);
            if ($bloqueoTs > time()) {
                return ['ok' => false, 'error' => '2'];
            }
            // Bloqueo expirado — resetear
            Database::execute(
                "UPDATE Usuarios SET intentos_fallidos=0, bloqueo_hasta=NULL WHERE id=?",
                [$row['id']]
            );
        }

        if (!$passwordOk) {
            $intentos = (int)$row['intentos_fallidos'] + 1;
            if ($intentos >= MAX_LOGIN_ATTEMPTS) {
                $hasta = date('Y-m-d H:i:s', time() + LOCKOUT_MINUTES * 60);
                Database::execute(
                    "UPDATE Usuarios SET intentos_fallidos=?, bloqueo_hasta=? WHERE id=?",
                    [$intentos, $hasta, $row['id']]
                );
            } else {
                Database::execute(
                    "UPDATE Usuarios SET intentos_fallidos=? WHERE id=?",
                    [$intentos, $row['id']]
                );
            }
            // Registrar auditoría
            self::audit($usuario, 'LOGIN_FALLIDO', 'Intento ' . $intentos . '/' . MAX_LOGIN_ATTEMPTS);
            return ['ok' => false, 'error' => '1'];
        }

        // Autenticación exitosa — limpiar intentos, regenerar sesión
        Database::execute(
            "UPDATE Usuarios SET intentos_fallidos=0, bloqueo_hasta=NULL WHERE id=?",
            [$row['id']]
        );

        session_regenerate_id(true);
        $_SESSION['_last_regen']  = time();
        $_SESSION['user_id']      = $row['id'];
        $_SESSION['username']     = $row['nombre_usuario'];
        $_SESSION['rol']          = (int)$row['rol'];
        $_SESSION['login_time']   = time();

        self::audit($usuario, 'LOGIN_OK', 'Acceso desde ' . Security::clientIp());
        return ['ok' => true, 'error' => ''];
    }

    /** Cierra la sesión de forma segura. */
    public static function logout(): void
    {
        if (self::check()) {
            self::audit(self::username(), 'LOGOUT', '');
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
    }

    // ── Verificaciones ────────────────────────────────────────────────────────

    /** Devuelve true si hay un usuario autenticado. */
    public static function check(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    /** Redirige al login si no está autenticado. */
    public static function requireAuth(): void
    {
        if (!self::check()) {
            header('Location: /login');
            exit;
        }
        // Expirar sesión por inactividad
        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > SESSION_LIFETIME) {
            self::logout();
            header('Location: /login?timeout=1');
            exit;
        }
        // Renovar timestamp de actividad
        $_SESSION['login_time'] = time();
    }

    /** Redirige a /403 si el usuario no tiene el rol requerido. */
    public static function requireRole(int ...$roles): void
    {
        self::requireAuth();
        if (!in_array(self::rol(), $roles, true)) {
            http_response_code(403);
            require BASE_PATH . '/views/errors/403.php';
            exit;
        }
    }

    // ── Getters ───────────────────────────────────────────────────────────────

    public static function userId(): ?int    { return $_SESSION['user_id'] ?? null; }
    public static function username(): string { return $_SESSION['username'] ?? ''; }
    public static function rol(): int        { return $_SESSION['rol'] ?? -1; }
    public static function rolNombre(): string { return ROL_NOMBRES[self::rol()] ?? 'Desconocido'; }

    public static function isAdmin(): bool     { return self::rol() === ROL_ADMINISTRADOR; }
    public static function isFacturador(): bool { return self::rol() === ROL_FACTURADOR; }
    public static function isEquipoPPL(): bool  { return self::rol() === ROL_EQUIPO_PPL; }
    public static function isEstadistico(): bool { return self::rol() === ROL_ESTADISTICO; }

    // ── Auditoría ─────────────────────────────────────────────────────────────

    public static function audit(string $usuario, string $accion, string $detalle): void
    {
        try {
            Database::execute(
                "INSERT INTO AuditoriasAccion (nombre_usuario, accion, detalle, fecha, ip)
                 VALUES (?, ?, ?, NOW(), ?)",
                [
                    mb_substr($usuario, 0, 100),
                    mb_substr($accion,  0, 200),
                    mb_substr($detalle, 0, 1000),
                    Security::clientIp(),
                ]
            );
        } catch (Throwable) {
            // No interrumpir el flujo si falla la auditoría
        }
    }
}
