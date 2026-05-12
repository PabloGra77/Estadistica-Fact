<?php
/**
 * AdminController — gestión de usuarios (solo Administrador).
 */
Auth::requireRole(ROL_ADMINISTRADOR);

preg_match('#^/admin/usuarios/(\d+)/(editar|toggle)$#', $uri, $uMatch);
$userId  = isset($uMatch[1]) ? (int)$uMatch[1] : null;
$uAction = $uMatch[2] ?? null;

// ── GET /admin/usuarios ───────────────────────────────────────────────────────
if ($uri === '/admin/usuarios' && $method === 'GET') {
    $usuarios = Database::fetchAll(
        "SELECT id, nombre_usuario, rol, activo, intentos_fallidos, bloqueo_hasta, fecha_creacion
         FROM Usuarios ORDER BY nombre_usuario"
    );
    require BASE_PATH . '/app/Views/admin/usuarios.php';
    exit;
}

// ── GET/POST /admin/usuarios/crear ────────────────────────────────────────────
if ($uri === '/admin/usuarios/crear') {
    $errors = [];
    $values = ['nombre_usuario' => '', 'rol' => ROL_ESTADISTICO, 'activo' => 1];

    if ($method === 'POST') {
        Security::verifyCsrf();

        $nomUsr  = Security::sanitizeString($_POST['nombre_usuario'] ?? '', 100);
        $rol     = Security::validateInt($_POST['rol'] ?? '', 0, 3);
        $pwd     = $_POST['password'] ?? '';
        $pwdConf = $_POST['password_confirm'] ?? '';

        if ($nomUsr === '') $errors[] = 'El nombre de usuario es obligatorio.';
        if (!preg_match('/^[a-z0-9_\.]{3,50}$/i', $nomUsr)) $errors[] = 'Usuario: solo letras, números, _ y . (3-50 caracteres).';
        if ($rol === null) $errors[] = 'Seleccione un rol.';
        if (strlen($pwd) < 8) $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
        if ($pwd !== $pwdConf) $errors[] = 'Las contraseñas no coinciden.';

        if (empty($errors)) {
            $existe = Database::fetchOne("SELECT id FROM Usuarios WHERE nombre_usuario=?", [$nomUsr]);
            if ($existe) {
                $errors[] = "El usuario '$nomUsr' ya existe.";
            } else {
                Database::insert(
                    "INSERT INTO Usuarios (nombre_usuario, password_hash, rol, activo, cambio_password_req) VALUES (?,?,?,1,0)",
                    [$nomUsr, Security::hashPassword($pwd), $rol]
                );
                Auth::audit(Auth::username(), 'USUARIO_CREADO', "Usuario: $nomUsr");
                header('Location: /admin/usuarios?ok=1');
                exit;
            }
        }
        $values = ['nombre_usuario' => $nomUsr, 'rol' => $rol, 'activo' => 1];
    }

    require BASE_PATH . '/app/Views/admin/usuario_form.php';
    exit;
}

// ── POST /admin/usuarios/{id}/editar ─────────────────────────────────────────
if ($userId !== null && $uAction === 'editar' && $method === 'POST') {
    Security::verifyCsrf();

    $rol    = Security::validateInt($_POST['rol'] ?? '', 0, 3);
    $activo = isset($_POST['activo']) ? 1 : 0;
    $pwd    = $_POST['password'] ?? '';

    if ($rol === null) { header('Location: /admin/usuarios?err=1'); exit; }

    // Evitar que el admin se bloquee a sí mismo
    if ($userId === Auth::userId() && $activo === 0) {
        header('Location: /admin/usuarios?err=2');
        exit;
    }

    if ($pwd !== '') {
        if (strlen($pwd) < 8) { header('Location: /admin/usuarios?err=3'); exit; }
        Database::execute(
            "UPDATE Usuarios SET rol=?, activo=?, password_hash=? WHERE id=?",
            [$rol, $activo, Security::hashPassword($pwd), $userId]
        );
    } else {
        Database::execute(
            "UPDATE Usuarios SET rol=?, activo=? WHERE id=?",
            [$rol, $activo, $userId]
        );
    }

    Auth::audit(Auth::username(), 'USUARIO_EDITADO', "ID: $userId");
    header('Location: /admin/usuarios?ok=2');
    exit;
}

// ── POST /admin/usuarios/{id}/toggle ─────────────────────────────────────────
if ($userId !== null && $uAction === 'toggle' && $method === 'POST') {
    Security::verifyCsrf();

    if ($userId === Auth::userId()) { header('Location: /admin/usuarios?err=2'); exit; }

    $u = Database::fetchOne("SELECT activo FROM Usuarios WHERE id=?", [$userId]);
    if (!$u) { http_response_code(404); exit; }

    $nuevoEstado = $u['activo'] ? 0 : 1;
    Database::execute("UPDATE Usuarios SET activo=?, intentos_fallidos=0, bloqueo_hasta=NULL WHERE id=?",
        [$nuevoEstado, $userId]);
    Auth::audit(Auth::username(), 'USUARIO_TOGGLE', "ID: $userId | Activo: $nuevoEstado");
    header('Location: /admin/usuarios?ok=3');
    exit;
}
