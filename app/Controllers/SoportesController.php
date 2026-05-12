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

// ── GET/POST /soportes/importar-zip ──────────────────────────────────────────
if ($uri === '/soportes/importar-zip') {
    Auth::requireRole(ROL_ADMINISTRADOR, ROL_FACTURADOR, ROL_EQUIPO_PPL);

    $resultados = [];
    $procesados = 0;
    $errores    = 0;
    $completado = false;
    $anio = (int)date('Y');
    $mes  = (int)date('n');

    /** Borra un directorio y todo su contenido de forma recursiva */
    $borrarDir = function (string $dir) use (&$borrarDir): void {
        foreach (glob($dir . '/{,.}*', GLOB_BRACE) as $item) {
            if (in_array(basename($item), ['.', '..'], true)) continue;
            is_dir($item) ? $borrarDir($item) : @unlink($item);
        }
        @rmdir($dir);
    };

    if ($method === 'POST') {
        Security::verifyCsrf();

        $file      = $_FILES['zip_soportes'] ?? null;
        $zipErrors = [];

        if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
            $zipErrors[] = 'Seleccione un archivo ZIP.';
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $codigos = [
                UPLOAD_ERR_INI_SIZE   => 'El archivo supera upload_max_filesize del servidor.',
                UPLOAD_ERR_FORM_SIZE  => 'El archivo supera MAX_FILE_SIZE del formulario.',
                UPLOAD_ERR_PARTIAL    => 'El archivo se subió parcialmente.',
            ];
            $zipErrors[] = $codigos[$file['error']] ?? 'Error al subir el archivo (código ' . (int)$file['error'] . ').';
        } else {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($ext !== 'zip') {
                $zipErrors[] = 'Solo se aceptan archivos .zip.';
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime  = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                $mimesPermitidos = ['application/zip', 'application/x-zip-compressed',
                                    'application/x-zip', 'application/octet-stream'];
                if (!in_array($mime, $mimesPermitidos, true)) {
                    $zipErrors[] = 'El archivo no tiene un formato ZIP válido (MIME: ' . htmlspecialchars($mime) . ').';
                }
            }
        }

        if (empty($zipErrors)) {
            $zip = new ZipArchive();
            $res = $zip->open($file['tmp_name']);
            if ($res !== true) {
                $zipErrors[] = 'No se pudo abrir el ZIP (código ' . $res . '). Verifique que no esté dañado.';
            } else {
                // Extraer a carpeta temporal segura
                $tmpDir = sys_get_temp_dir() . '/ppl_zip_' . bin2hex(random_bytes(8));
                mkdir($tmpDir, 0700, true);
                $zip->extractTo($tmpDir);
                $zip->close();

                // Mapeo código → índice numérico de servicio
                $mapaServicios = array_flip(TIPOS_SERVICIO); // ['VALPS'=>0,'VALPQ'=>1,...]

                // Detectar directorio base: ZIP puede incluir carpeta raíz extra
                $dirs = glob($tmpDir . '/*', GLOB_ONLYDIR);
                $baseDir = $tmpDir;
                if (count($dirs) === 1) {
                    $innerDirs  = glob($dirs[0] . '/*', GLOB_ONLYDIR);
                    $innerPdfs  = array_merge(
                        glob($dirs[0] . '/*.pdf') ?: [],
                        glob($dirs[0] . '/*.PDF') ?: []
                    );
                    if (!empty($innerDirs) && empty($innerPdfs)) {
                        // La raíz del ZIP es una carpeta contenedora → entrar un nivel
                        $baseDir = $dirs[0];
                        $dirs    = $innerDirs;
                    }
                }

                $username = Auth::username();

                foreach ($dirs as $pacienteDir) {
                    $docPaciente = basename($pacienteDir);

                    // Ignorar artefactos de macOS
                    if ($docPaciente === '__MACOSX' || str_starts_with($docPaciente, '.')) continue;

                    $paciente = Database::fetchOne(
                        "SELECT id, nombre FROM Pacientes WHERE documento=? AND activo=1",
                        [$docPaciente]
                    );

                    $pdfs = array_merge(
                        glob($pacienteDir . '/*.pdf') ?: [],
                        glob($pacienteDir . '/*.PDF') ?: []
                    );

                    if (empty($pdfs)) {
                        $resultados[] = ['tipo' => 'warning',
                            'msg' => "Carpeta «{$docPaciente}»: no contiene archivos PDF — omitida."];
                        $errores++;
                        continue;
                    }

                    foreach ($pdfs as $pdfPath) {
                        $base    = pathinfo($pdfPath, PATHINFO_FILENAME);  // sin extensión
                        $ext     = strtolower(pathinfo($pdfPath, PATHINFO_EXTENSION));
                        $original = basename($pdfPath);

                        // Código de servicio: todo lo que hay tras el último '_'
                        $partes  = explode('_', $base);
                        $codigo  = strtoupper(end($partes));

                        if (!isset($mapaServicios[$codigo])) {
                            $resultados[] = ['tipo' => 'danger',
                                'msg' => "«{$original}»: código de servicio «{$codigo}» no reconocido — omitido."];
                            $errores++;
                            continue;
                        }

                        if (!$paciente) {
                            $resultados[] = ['tipo' => 'danger',
                                'msg' => "«{$original}»: paciente con documento «{$docPaciente}» no encontrado — omitido."];
                            $errores++;
                            continue;
                        }

                        $servicioInt = $mapaServicios[$codigo];
                        $pacienteId  = (int)$paciente['id'];

                        // Obtener o crear Atencion para este paciente+servicio+mes+año
                        $atencion = Database::fetchOne(
                            "SELECT id FROM Atenciones
                             WHERE paciente_id=? AND servicio=? AND anio_atencion=? AND mes_atencion=?",
                            [$pacienteId, $servicioInt, $anio, $mes]
                        );
                        if ($atencion) {
                            $atencionId = (int)$atencion['id'];
                        } else {
                            $atencionId = (int)Database::insert(
                                "INSERT INTO Atenciones (paciente_id, servicio, anio_atencion, mes_atencion)
                                 VALUES (?,?,?,?)",
                                [$pacienteId, $servicioInt, $anio, $mes]
                            );
                        }

                        // Duplicado por hash
                        $hash = hash_file('sha256', $pdfPath);
                        if (Database::fetchOne("SELECT id FROM Soportes WHERE hash_sha256=?", [$hash])) {
                            $resultados[] = ['tipo' => 'warning',
                                'msg' => "«{$original}»: ya cargado anteriormente — omitido."];
                            continue;
                        }

                        // Guardar en STORAGE_PATH
                        $nombreFisico = $hash . '_' . time() . '.' . $ext;
                        $destino      = STORAGE_PATH . '/' . $nombreFisico;

                        if (!copy($pdfPath, $destino)) {
                            $resultados[] = ['tipo' => 'danger',
                                'msg' => "«{$original}»: error al guardar el archivo — omitido."];
                            $errores++;
                            continue;
                        }

                        Database::insert(
                            "INSERT INTO Soportes
                             (atencion_id, nombre_original, nombre_fisico, hash_sha256, cargado_por)
                             VALUES (?,?,?,?,?)",
                            [$atencionId, mb_substr($original, 0, 500), $nombreFisico, $hash, $username]
                        );

                        $resultados[] = ['tipo' => 'success',
                            'msg' => "«{$original}» → {$paciente['nombre']} | "
                                   . TIPOS_SERVICIO[$servicioInt] . " — {$mes}/{$anio}"];
                        $procesados++;
                    }
                }

                // Limpiar temp
                $borrarDir($tmpDir);

                $completado = true;
                if ($procesados > 0) {
                    Auth::audit($username, 'ZIP_IMPORTADO',
                        "Procesados: {$procesados} | Errores: {$errores}");
                }
            }
        }

        // Agregar errores de validación del ZIP al inicio
        foreach (array_reverse($zipErrors) as $ze) {
            array_unshift($resultados, ['tipo' => 'danger', 'msg' => $ze]);
        }
        $errores += count($zipErrors);
    }

    require BASE_PATH . '/app/Views/soportes/importar_zip.php';
    exit;
}
