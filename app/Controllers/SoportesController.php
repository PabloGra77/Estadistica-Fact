<?php
/**
 * SoportesController — carga, descarga y auditoría de soportes.
 */
Auth::requireAuth();

preg_match('#^/soportes/(\d+)/(descargar|auditar)$#', $uri, $sMatch);
$soporteId = isset($sMatch[1]) ? (int)$sMatch[1] : null;
$sAction   = $sMatch[2] ?? null;

// ── GET /soportes ─────────────────────────────────────────────────────────────
if ($uri === '/soportes' && $method === 'GET') {
    $busqueda = Security::sanitizeString($_GET['q'] ?? '', 100);
    $estado   = Security::validateInt($_GET['estado'] ?? '', 0, 4);
    $pagina   = max(1, (int)($_GET['p'] ?? 1));
    $porPagina = 20;
    $offset   = ($pagina - 1) * $porPagina;

    $conds  = ['1=1'];
    $params = [];

    if ($busqueda !== '') {
        $conds[]  = '(p.nombre LIKE ? OR p.documento LIKE ? OR s.nombre_original LIKE ?)';
        $params[] = "%$busqueda%";
        $params[] = "%$busqueda%";
        $params[] = "%$busqueda%";
    }
    if ($estado !== null) { $conds[] = 's.estado=?'; $params[] = $estado; }

    $where = implode(' AND ', $conds);
    $total = Database::fetchOne(
        "SELECT COUNT(*) AS total FROM Soportes s
         JOIN Atenciones a ON a.id=s.atencion_id
         JOIN Pacientes p ON p.id=a.paciente_id
         WHERE $where",
        $params
    )['total'] ?? 0;

    $soportes = Database::fetchAll(
        "SELECT s.id, s.nombre_original, s.estado, s.fecha_carga, s.cargado_por,
                s.auditado_por, s.fecha_auditoria,
                p.nombre AS paciente_nombre, p.documento,
                a.servicio, a.mes_atencion, a.anio_atencion
         FROM Soportes s
         JOIN Atenciones a ON a.id=s.atencion_id
         JOIN Pacientes p ON p.id=a.paciente_id
         WHERE $where
         ORDER BY s.fecha_carga DESC
         LIMIT $porPagina OFFSET $offset",
        $params
    );

    $totalPaginas = (int)ceil($total / $porPagina);
    require BASE_PATH . '/app/Views/soportes/index.php';
    exit;
}

// ── GET/POST /soportes/subir ──────────────────────────────────────────────────
if ($uri === '/soportes/subir') {
    Auth::requireRole(ROL_ADMINISTRADOR, ROL_FACTURADOR, ROL_EQUIPO_PPL);

    $errors = [];
    $values = ['atencion_id' => ''];
    $atenciones = Database::fetchAll(
        "SELECT a.id, p.nombre, p.documento, a.servicio, a.mes_atencion, a.anio_atencion
         FROM Atenciones a JOIN Pacientes p ON p.id=a.paciente_id
         ORDER BY a.fecha_carga DESC LIMIT 200"
    );

    if ($method === 'POST') {
        Security::verifyCsrf();

        $atencionId = Security::validateInt($_POST['atencion_id'] ?? '', 1);
        if ($atencionId === null) $errors[] = 'Seleccione una atención.';

        $file = $_FILES['soporte'] ?? null;
        if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Seleccione un archivo.';
        } elseif ($file) {
            $valid = Security::validateUpload($file);
            if (!$valid['ok']) $errors[] = $valid['error'];
        }

        if (empty($errors)) {
            // Verificar que la atención existe
            $atencion = Database::fetchOne("SELECT id FROM Atenciones WHERE id=?", [$atencionId]);
            if (!$atencion) { $errors[] = 'Atención no encontrada.'; }
        }

        if (empty($errors)) {
            $hash        = hash_file('sha256', $file['tmp_name']);
            $ext         = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $nombreFisico = $hash . '_' . time() . '.' . $ext;
            $destino     = STORAGE_PATH . '/' . $nombreFisico;

            // Verificar duplicado por hash
            $dup = Database::fetchOne("SELECT id FROM Soportes WHERE hash_sha256=?", [$hash]);
            if ($dup) {
                $errors[] = 'Este archivo ya fue cargado anteriormente (hash duplicado).';
            } else {
                if (!move_uploaded_file($file['tmp_name'], $destino)) {
                    $errors[] = 'Error al guardar el archivo. Contacte al administrador.';
                } else {
                    Database::insert(
                        "INSERT INTO Soportes
                         (atencion_id, nombre_original, nombre_fisico, hash_sha256, cargado_por)
                         VALUES (?,?,?,?,?)",
                        [
                            $atencionId,
                            mb_substr($file['name'], 0, 500),
                            $nombreFisico,
                            $hash,
                            Auth::username(),
                        ]
                    );
                    Auth::audit(Auth::username(), 'SOPORTE_SUBIDO',
                        "Atencion: $atencionId | Archivo: " . $file['name']);
                    header('Location: /soportes?ok=1');
                    exit;
                }
            }
        }
        $values['atencion_id'] = $_POST['atencion_id'] ?? '';
    }

    require BASE_PATH . '/app/Views/soportes/form.php';
    exit;
}

// ── GET /soportes/{id}/descargar ──────────────────────────────────────────────
if ($soporteId !== null && $sAction === 'descargar' && $method === 'GET') {
    $soporte = Database::fetchOne(
        "SELECT s.*, a.paciente_id FROM Soportes s JOIN Atenciones a ON a.id=s.atencion_id WHERE s.id=?",
        [$soporteId]
    );
    if (!$soporte) { http_response_code(404); require BASE_PATH . '/app/Views/errors/404.php'; exit; }

    $ruta = STORAGE_PATH . '/' . $soporte['nombre_fisico'];
    if (!file_exists($ruta)) { http_response_code(404); require BASE_PATH . '/app/Views/errors/404.php'; exit; }

    Auth::audit(Auth::username(), 'SOPORTE_DESCARGADO', "ID: $soporteId");

    // Headers seguros para descarga
    $mimeInfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime     = finfo_file($mimeInfo, $ruta);
    finfo_close($mimeInfo);

    header('Content-Type: ' . $mime);
    // Content-Disposition: attachment evita ejecución inline en el browser
    header('Content-Disposition: attachment; filename="' . rawurlencode($soporte['nombre_original']) . '"');
    header('Content-Length: ' . filesize($ruta));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    readfile($ruta);
    exit;
}

// ── POST /soportes/{id}/auditar ───────────────────────────────────────────────
if ($soporteId !== null && $sAction === 'auditar' && $method === 'POST') {
    Auth::requireRole(ROL_ADMINISTRADOR, ROL_FACTURADOR);
    Security::verifyCsrf();

    $estado = Security::validateInt($_POST['estado'] ?? '', 2, 3); // 2=Aprobado, 3=Rechazado
    $obs    = Security::sanitizeString($_POST['observacion'] ?? '', 500);

    if ($estado === null) { http_response_code(400); echo 'Estado inválido'; exit; }

    $soporte = Database::fetchOne("SELECT id FROM Soportes WHERE id=?", [$soporteId]);
    if (!$soporte) { http_response_code(404); require BASE_PATH . '/app/Views/errors/404.php'; exit; }

    Database::execute(
        "UPDATE Soportes SET estado=?, auditado_por=?, fecha_auditoria=NOW(), observacion_auditoria=? WHERE id=?",
        [$estado, Auth::username(), $obs, $soporteId]
    );
    Auth::audit(Auth::username(), 'SOPORTE_AUDITADO', "ID: $soporteId | Estado: $estado");
    header('Location: /soportes?ok=2');
    exit;
}
