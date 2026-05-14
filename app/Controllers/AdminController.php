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

// ── GET /admin/periodos ───────────────────────────────────────────────────────
if ($uri === '/admin/periodos' && $method === 'GET') {
    $periodos = Database::fetchAll(
        "SELECT p.*, COUNT(a.id) AS total_atenciones
         FROM Periodos p
         LEFT JOIN Atenciones a ON a.mes_atencion=p.mes AND a.anio_atencion=p.anio
         GROUP BY p.id
         ORDER BY p.anio DESC, p.mes DESC"
    );
    require BASE_PATH . '/app/Views/admin/periodos.php';
    exit;
}

// ── POST /admin/periodos/crear ────────────────────────────────────────────────
if ($uri === '/admin/periodos/crear' && $method === 'POST') {
    Security::verifyCsrf();
    $mes    = Security::validateInt($_POST['mes'] ?? '', 1, 12);
    $anio   = Security::validateInt($_POST['anio'] ?? '', 2020, 2099);
    $nombre = Security::sanitizeString($_POST['nombre'] ?? '', 100);

    if (!$mes || !$anio || $nombre === '') {
        header('Location: /admin/periodos?err=datos'); exit;
    }
    $existe = Database::fetchOne("SELECT id FROM Periodos WHERE mes=? AND anio=?", [$mes, $anio]);
    if ($existe) {
        header('Location: /admin/periodos?err=duplicado'); exit;
    }
    Database::insert(
        "INSERT INTO Periodos (nombre, mes, anio, activo, estado, creado_por) VALUES (?,?,?,0,'abierto',?)",
        [$nombre, $mes, $anio, Auth::username()]
    );
    $nuevoPeriodoId = Database::fetchOne("SELECT id FROM Periodos WHERE mes=? AND anio=?", [$mes, $anio])['id'];
    Auth::audit(Auth::username(), 'PERIODO_CREADO', $nombre);
    header("Location: /admin/periodos/{$nuevoPeriodoId}/pacientes");
    exit;
}

// ── POST /admin/periodos/{id}/activar ─────────────────────────────────────────
preg_match('#^/admin/periodos/(\d+)/activar$#', $uri, $pMatch);
if (!empty($pMatch) && $method === 'POST') {
    Security::verifyCsrf();
    $pid = (int)$pMatch[1];
    $periodo = Database::fetchOne("SELECT * FROM Periodos WHERE id=?", [$pid]);
    if (!$periodo || $periodo['estado'] === 'cerrado') {
        header('Location: /admin/periodos?err=noactiv'); exit;
    }
    Database::execute("UPDATE Periodos SET activo=0", []);
    Database::execute("UPDATE Periodos SET activo=1 WHERE id=?", [$pid]);
    Auth::audit(Auth::username(), 'PERIODO_ACTIVADO', "ID: $pid | {$periodo['nombre']}");
    header('Location: /admin/periodos?ok=2');
    exit;
}

// ── POST /admin/periodos/{id}/cerrar ──────────────────────────────────────────
preg_match('#^/admin/periodos/(\d+)/cerrar$#', $uri, $cMatch);
if (!empty($cMatch) && $method === 'POST') {
    Security::verifyCsrf();
    $pid = (int)$cMatch[1];
    Database::execute(
        "UPDATE Periodos SET estado='cerrado', activo=0, fecha_cierre=NOW() WHERE id=?",
        [$pid]
    );
    Auth::audit(Auth::username(), 'PERIODO_CERRADO', "ID: $pid");
    header('Location: /admin/periodos?ok=3');
    exit;
}

// ── GET /admin/periodos/{id}/pacientes ────────────────────────────────────────
preg_match('#^/admin/periodos/(\d+)/pacientes$#', $uri, $ppMatch);
if (!empty($ppMatch) && $method === 'GET') {
    $pid = (int)$ppMatch[1];
    $periodo = Database::fetchOne("SELECT * FROM Periodos WHERE id=?", [$pid]);
    if (!$periodo) { http_response_code(404); require BASE_PATH . '/app/Views/errors/404.php'; exit; }
    $pacientes = Database::fetchAll(
        "SELECT id, documento,
                CONCAT_WS(' ', primer_nombre, segundo_nombre, primer_apellido, segundo_apellido) AS nombre_completo,
                primer_nombre, segundo_nombre, primer_apellido, segundo_apellido,
                paquete, nui, fecha_nacimiento
         FROM Pacientes
         WHERE activo = 1
         ORDER BY primer_apellido, primer_nombre"
    );
    require BASE_PATH . '/app/Views/admin/periodo_pacientes.php';
    exit;
}

// ── POST /admin/periodos/{id}/pacientes ───────────────────────────────────────
if (!empty($ppMatch) && $method === 'POST') {
    Security::verifyCsrf();
    $pid = (int)$ppMatch[1];
    $periodo = Database::fetchOne("SELECT id FROM Periodos WHERE id=?", [$pid]);
    if (!$periodo) { header('Location: /admin/periodos'); exit; }

    $paquetes = $_POST['paquete'] ?? [];
    foreach ($paquetes as $pacienteId => $nuevoPaquete) {
        $pid2 = (int)$pacienteId;
        $paq  = Security::validateInt($nuevoPaquete, 1, 3);
        if ($pid2 > 0 && $paq) {
            Database::execute("UPDATE Pacientes SET paquete=? WHERE id=?", [$paq, $pid2]);
        }
    }
    Auth::audit(Auth::username(), 'PAQUETES_ACTUALIZADOS', "Período ID: $pid");
    header('Location: /admin/periodos?ok=1');
    exit;
}
