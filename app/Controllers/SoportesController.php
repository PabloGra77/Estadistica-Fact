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

    // Período activo
    $periodActivo = Database::fetchOne("SELECT * FROM Periodos WHERE activo=1 AND estado='abierto' LIMIT 1");
    $periodoMes   = $periodActivo ? (int)$periodActivo['mes']  : (int)date('n');
    $periodoAnio  = $periodActivo ? (int)$periodActivo['anio'] : (int)date('Y');

    $conds  = ['a.mes_atencion=?', 'a.anio_atencion=?'];
    $params = [$periodoMes, $periodoAnio];

    if ($busqueda !== '') {
        $conds[]  = '(CONCAT_WS(" ", p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) LIKE ? OR p.documento LIKE ? OR s.nombre_original LIKE ?)';
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
                CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS paciente_nombre, p.documento,
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
    header('Location: /soportes/importar-zip', true, 301);
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

    $resultados  = [];
    $procesados  = 0;
    $errores     = 0;
    $completado  = false;
    $anio        = (int)date('Y');
    $mes         = (int)date('n');

    // ZIPs disponibles en la bandeja de entrada del servidor
    if (!is_dir(INBOX_PATH)) @mkdir(INBOX_PATH, 0750, true);
    $inboxZips = array_values(array_filter(
        glob(INBOX_PATH . '/*.zip') ?: [],
        'is_file'
    ));

    /**
     * Procesa un ZIP directamente desde su ruta en el filesystem.
     * Itera entradas con ZipArchive::getStream() — nunca extrae todo a disco,
     * por lo que funciona con ZIPs de cualquier tamaño (incluidos >10 GB).
     */
    $procesarZipDesdeRuta = function (string $zipPath) use (
        &$resultados, &$procesados, &$errores, $anio, $mes
    ): void {
        $zip = new ZipArchive();
        $res = $zip->open($zipPath, ZipArchive::RDONLY);
        if ($res !== true) {
            $resultados[] = ['tipo' => 'danger',
                'msg' => 'No se pudo abrir el ZIP (código ' . $res . '). Verifique que no esté dañado.'];
            $errores++;
            return;
        }

        $mapaServicios = array_flip(TIPOS_SERVICIO);
        $username      = Auth::username();

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);
            if ($entryName === false) continue;

            // Ignorar artefactos de macOS y archivos ocultos
            if (str_starts_with($entryName, '__MACOSX/') ||
                str_starts_with(basename(rtrim($entryName, '/')), '.')) continue;

            // Ignorar entradas de directorio
            if (str_ends_with($entryName, '/')) continue;

            // Descomponer ruta en partes significativas
            $partes = array_values(array_filter(
                explode('/', $entryName),
                fn($p) => $p !== '' && $p !== '.' && $p !== '..'
            ));

            // Soportar profundidad 2 (cc/file.pdf) y 3 (raiz/cc/file.pdf)
            if (count($partes) === 2) {
                [$docPaciente, $filename] = $partes;
            } elseif (count($partes) === 3) {
                [, $docPaciente, $filename] = $partes;
            } else {
                continue; // demasiado anidado o en raíz — omitir
            }

            // Solo procesar PDFs
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if ($ext !== 'pdf') continue;

            // Código de servicio: sufijo tras el último '_' en el nombre base
            $base   = pathinfo($filename, PATHINFO_FILENAME);
            $partesCodigo = explode('_', $base);
            $codigo = strtoupper(end($partesCodigo));

            if (!isset($mapaServicios[$codigo])) {
                $resultados[] = ['tipo' => 'danger',
                    'msg' => "«{$filename}»: código de servicio «{$codigo}» no reconocido — omitido."];
                $errores++;
                continue;
            }

            $paciente = Database::fetchOne(
                "SELECT id, nombre FROM Pacientes WHERE documento=? AND activo=1",
                [$docPaciente]
            );
            if (!$paciente) {
                $resultados[] = ['tipo' => 'danger',
                    'msg' => "«{$filename}»: paciente con documento «{$docPaciente}» no encontrado — omitido."];
                $errores++;
                continue;
            }

            $servicioInt = $mapaServicios[$codigo];
            $pacienteId  = (int)$paciente['id'];

            // Obtener o crear Atencion
            $atencion = Database::fetchOne(
                "SELECT id FROM Atenciones
                 WHERE paciente_id=? AND servicio=? AND anio_atencion=? AND mes_atencion=?",
                [$pacienteId, $servicioInt, $anio, $mes]
            );
            $atencionId = $atencion
                ? (int)$atencion['id']
                : (int)Database::insert(
                    "INSERT INTO Atenciones (paciente_id, servicio, anio_atencion, mes_atencion) VALUES (?,?,?,?)",
                    [$pacienteId, $servicioInt, $anio, $mes]
                );

            // Leer entrada en streaming: calcular hash y escribir a temp en una sola pasada
            $stream = $zip->getStream($entryName);
            if (!$stream) {
                $resultados[] = ['tipo' => 'danger',
                    'msg' => "«{$filename}»: no se pudo leer la entrada del ZIP — omitido."];
                $errores++;
                continue;
            }

            $tmpFile = tempnam(sys_get_temp_dir(), 'ppl_');
            $fpTmp   = fopen($tmpFile, 'wb');
            $hashCtx = hash_init('sha256');
            while (!feof($stream)) {
                $chunk = fread($stream, 65536);
                if ($chunk === false || $chunk === '') break;
                hash_update($hashCtx, $chunk);
                fwrite($fpTmp, $chunk);
            }
            fclose($stream);
            fclose($fpTmp);
            $hash = hash_final($hashCtx);

            // Duplicado
            if (Database::fetchOne("SELECT id FROM Soportes WHERE hash_sha256=?", [$hash])) {
                @unlink($tmpFile);
                $resultados[] = ['tipo' => 'warning',
                    'msg' => "«{$filename}»: ya cargado anteriormente — omitido."];
                continue;
            }

            // Mover a storage permanente
            $nombreFisico = $hash . '_' . time() . '.pdf';
            $destino      = STORAGE_PATH . '/' . $nombreFisico;
            if (!rename($tmpFile, $destino)) {
                // rename falla entre particiones; intentar copy+unlink
                if (!copy($tmpFile, $destino)) {
                    @unlink($tmpFile);
                    $resultados[] = ['tipo' => 'danger',
                        'msg' => "«{$filename}»: error al guardar en storage — omitido."];
                    $errores++;
                    continue;
                }
                @unlink($tmpFile);
            }

            Database::insert(
                "INSERT INTO Soportes
                 (atencion_id, nombre_original, nombre_fisico, hash_sha256, cargado_por)
                 VALUES (?,?,?,?,?)",
                [$atencionId, mb_substr($filename, 0, 500), $nombreFisico, $hash, $username]
            );

            $resultados[] = ['tipo' => 'success',
                'msg' => "«{$filename}» → {$paciente['nombre']} | "
                       . TIPOS_SERVICIO[$servicioInt] . " — {$mes}/{$anio}"];
            $procesados++;
        }

        $zip->close();
        $completado = true;
        if ($procesados > 0) {
            Auth::audit($username, 'ZIP_IMPORTADO',
                "Procesados: {$procesados} | Errores: {$errores}");
        }
    };

    // ── Necesitamos $completado accesible fuera del closure ──────────────────
    // Lo capturamos por referencia al redefinir:
    $procesarZipDesdeRuta = function (string $zipPath) use (
        &$resultados, &$procesados, &$errores, &$completado, $anio, $mes
    ): void {
        $zip = new ZipArchive();
        $res = $zip->open($zipPath, ZipArchive::RDONLY);
        if ($res !== true) {
            $resultados[] = ['tipo' => 'danger',
                'msg' => 'No se pudo abrir el ZIP (código ' . $res . '). Verifique que no esté dañado.'];
            $errores++;
            return;
        }

        $mapaServicios = array_flip(TIPOS_SERVICIO);
        $username      = Auth::username();

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);
            if ($entryName === false) continue;

            if (str_starts_with($entryName, '__MACOSX/') ||
                str_starts_with(basename(rtrim($entryName, '/')), '.')) continue;

            if (str_ends_with($entryName, '/')) continue;

            $pts = array_values(array_filter(
                explode('/', $entryName),
                fn($p) => $p !== '' && $p !== '.' && $p !== '..'
            ));

            if (count($pts) === 2)      [$docPaciente, $filename] = $pts;
            elseif (count($pts) === 3)  [, $docPaciente, $filename] = $pts;
            else                        continue;

            if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'pdf') continue;

            $base   = pathinfo($filename, PATHINFO_FILENAME);
            $codigo = strtoupper((fn($a) => end($a))(explode('_', $base)));

            if (!isset($mapaServicios[$codigo])) {
                $resultados[] = ['tipo' => 'danger',
                    'msg' => "«{$filename}»: código «{$codigo}» no reconocido — omitido."];
                $errores++; continue;
            }

            $paciente = Database::fetchOne(
                "SELECT id, nombre FROM Pacientes WHERE documento=? AND activo=1",
                [$docPaciente]
            );
            if (!$paciente) {
                $resultados[] = ['tipo' => 'danger',
                    'msg' => "«{$filename}»: paciente «{$docPaciente}» no encontrado — omitido."];
                $errores++; continue;
            }

            $servicioInt = $mapaServicios[$codigo];
            $pacienteId  = (int)$paciente['id'];

            $atencion = Database::fetchOne(
                "SELECT id FROM Atenciones WHERE paciente_id=? AND servicio=? AND anio_atencion=? AND mes_atencion=?",
                [$pacienteId, $servicioInt, $anio, $mes]
            );
            $atencionId = $atencion
                ? (int)$atencion['id']
                : (int)Database::insert(
                    "INSERT INTO Atenciones (paciente_id, servicio, anio_atencion, mes_atencion) VALUES (?,?,?,?)",
                    [$pacienteId, $servicioInt, $anio, $mes]
                );

            // Stream → temp file + hash simultáneo
            $stream = $zip->getStream($entryName);
            if (!$stream) {
                $resultados[] = ['tipo' => 'danger',
                    'msg' => "«{$filename}»: error al leer entrada del ZIP — omitido."];
                $errores++; continue;
            }
            $tmpFile = tempnam(sys_get_temp_dir(), 'ppl_');
            $fpTmp   = fopen($tmpFile, 'wb');
            $hashCtx = hash_init('sha256');
            while (!feof($stream)) {
                $chunk = fread($stream, 65536);
                if ($chunk === false || $chunk === '') break;
                hash_update($hashCtx, $chunk);
                fwrite($fpTmp, $chunk);
            }
            fclose($stream);
            fclose($fpTmp);
            $hash = hash_final($hashCtx);

            if (Database::fetchOne("SELECT id FROM Soportes WHERE hash_sha256=?", [$hash])) {
                @unlink($tmpFile);
                $resultados[] = ['tipo' => 'warning',
                    'msg' => "«{$filename}»: duplicado, ya existe — omitido."];
                continue;
            }

            $nombreFisico = $hash . '_' . time() . '.pdf';
            $destino      = STORAGE_PATH . '/' . $nombreFisico;
            if (!rename($tmpFile, $destino)) {
                copy($tmpFile, $destino);
                @unlink($tmpFile);
            }

            Database::insert(
                "INSERT INTO Soportes (atencion_id, nombre_original, nombre_fisico, hash_sha256, cargado_por) VALUES (?,?,?,?,?)",
                [$atencionId, mb_substr($filename, 0, 500), $nombreFisico, $hash, $username]
            );
            $resultados[] = ['tipo' => 'success',
                'msg' => "«{$filename}» → {$paciente['nombre']} | " . TIPOS_SERVICIO[$servicioInt] . " — {$mes}/{$anio}"];
            $procesados++;
        }

        $zip->close();
        $completado = true;
        if ($procesados > 0) {
            Auth::audit(Auth::username(), 'ZIP_IMPORTADO',
                "Procesados: {$procesados} | Errores: {$errores}");
        }
    };

    if ($method === 'POST') {
        Security::verifyCsrf();

        $modo = $_POST['modo'] ?? 'upload'; // 'upload' | 'inbox'

        // ── Modo bandeja: procesar ZIP desde storage/inbox ────────────────────
        if ($modo === 'inbox') {
            $zipFilename = basename($_POST['zip_inbox'] ?? '');
            if ($zipFilename === '' || pathinfo($zipFilename, PATHINFO_EXTENSION) !== 'zip') {
                $resultados[] = ['tipo' => 'danger', 'msg' => 'Seleccione un archivo de la bandeja.'];
                $errores++;
            } else {
                $zipPath = INBOX_PATH . '/' . $zipFilename;
                if (!is_file($zipPath)) {
                    $resultados[] = ['tipo' => 'danger',
                        'msg' => "El archivo «{$zipFilename}» no existe en la bandeja del servidor."];
                    $errores++;
                } else {
                    $procesarZipDesdeRuta($zipPath);
                    // Refrescar lista de inbox después de procesar
                    $inboxZips = array_values(array_filter(glob(INBOX_PATH . '/*.zip') ?: [], 'is_file'));
                }
            }

        // ── Modo upload: subir ZIP por HTTP (archivos pequeños) ───────────────
        } else {
            $file      = $_FILES['zip_soportes'] ?? null;
            $zipErrors = [];

            if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
                $zipErrors[] = 'Seleccione un archivo ZIP.';
            } elseif ($file['error'] !== UPLOAD_ERR_OK) {
                $codigos = [
                    UPLOAD_ERR_INI_SIZE  => 'El archivo supera el límite del servidor (' . ini_get('upload_max_filesize') . ').',
                    UPLOAD_ERR_FORM_SIZE => 'El archivo supera MAX_FILE_SIZE del formulario.',
                    UPLOAD_ERR_PARTIAL   => 'El archivo se subió parcialmente. Vuelva a intentarlo.',
                ];
                $zipErrors[] = $codigos[$file['error']]
                    ?? 'Error al subir el archivo (código ' . (int)$file['error'] . ').';
            } else {
                if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'zip') {
                    $zipErrors[] = 'Solo se aceptan archivos .zip.';
                }
            }

            if (empty($zipErrors)) {
                $procesarZipDesdeRuta($file['tmp_name']);
            } else {
                foreach ($zipErrors as $ze) {
                    $resultados[] = ['tipo' => 'danger', 'msg' => $ze];
                }
                $errores += count($zipErrors);
            }
        }
    }

    require BASE_PATH . '/app/Views/soportes/importar_zip.php';
    exit;
}
