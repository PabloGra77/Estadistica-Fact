<?php
/**
 * Seguridad centralizada: CSRF, XSS, Headers, validación.
 */
class Security
{
    // ── CSRF ──────────────────────────────────────────────────────────────────

    /** Genera (o recupera) el token CSRF de la sesión. */
    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /** Renderiza el campo oculto CSRF. */
    public static function csrfField(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . self::csrfToken() . '">';
    }

    /** Valida el token CSRF enviado en el formulario. */
    public static function verifyCsrf(): void
    {
        $token = $_POST['csrf_token'] ?? '';
        if (!hash_equals(self::csrfToken(), $token)) {
            http_response_code(403);
            die('Token de seguridad inválido. Recarga la página.');
        }
    }

    // ── Escape XSS ────────────────────────────────────────────────────────────

    /** Escapa salida HTML para prevenir XSS. Usar en toda salida de datos. */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    // ── Cabeceras de seguridad ────────────────────────────────────────────────

    /** Envía cabeceras HTTP de seguridad recomendadas. */
    public static function secureHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header(
            "Content-Security-Policy: " .
            "default-src 'self'; " .
            "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; " .
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; " .
            "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; " .
            "img-src 'self' data:; " .
            "connect-src 'self';"
        );
        // HSTS solo en producción (HTTPS)
        if (APP_ENV === 'production') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    // ── Validación de entrada ─────────────────────────────────────────────────

    /** Limpia y valida un string de entrada. */
    public static function sanitizeString(string $input, int $maxLen = 255): string
    {
        return mb_substr(trim(strip_tags($input)), 0, $maxLen);
    }

    /** Valida que un valor esté en una lista permitida (whitelist). */
    public static function inWhitelist(mixed $value, array $allowed): bool
    {
        return in_array($value, $allowed, true);
    }

    /** Valida un entero dentro de un rango. */
    public static function validateInt(mixed $value, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): ?int
    {
        $filtered = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => $min, 'max_range' => $max]
        ]);
        return $filtered !== false ? (int)$filtered : null;
    }

    // ── Contraseñas ───────────────────────────────────────────────────────────

    public static function hashPassword(string $plain): string
    {
        return password_hash($plain, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
    }

    public static function verifyPassword(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    // ── Archivos subidos ──────────────────────────────────────────────────────

    /**
     * Valida un archivo subido: MIME, extensión, tamaño.
     * Devuelve array con ['ok' => bool, 'error' => string].
     */
    public static function validateUpload(array $file): array
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Error en la subida del archivo.'];
        }

        $maxBytes = MAX_UPLOAD_MB * 1024 * 1024;
        if ($file['size'] > $maxBytes) {
            return ['ok' => false, 'error' => "Tamaño máximo permitido: " . MAX_UPLOAD_MB . " MB."];
        }

        // Validar extensión
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_EXT, true)) {
            return ['ok' => false, 'error' => 'Tipo de archivo no permitido.'];
        }

        // Validar MIME real (no confiar en el MIME del cliente)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, ALLOWED_MIME, true)) {
            return ['ok' => false, 'error' => 'Contenido del archivo no válido.'];
        }

        return ['ok' => true, 'error' => ''];
    }

    // ── IP del cliente ────────────────────────────────────────────────────────

    public static function clientIp(): string
    {
        // Solo confiar en REMOTE_ADDR — los encabezados X-Forwarded-For pueden ser falsificados
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
