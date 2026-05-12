<?php
/**
 * PacientesController
 */
Auth::requireAuth();

// Extraer ID de la URL si aplica
preg_match('#^/pacientes/(\d+)#', $uri, $idMatch);
$pacienteId = isset($idMatch[1]) ? (int)$idMatch[1] : null;

// ── GET /pacientes ────────────────────────────────────────────────────────────
if ($uri === '/pacientes' && $method === 'GET') {
    $busqueda  = Security::sanitizeString($_GET['q'] ?? '', 100);
    $pagina    = max(1, (int)($_GET['p'] ?? 1));
    $porPagina = 20;
    $offset    = ($pagina - 1) * $porPagina;

    $pacientes    = [];
    $total        = 0;
    $totalPaginas = 0;

    if ($busqueda !== '') {
        $params = ["%$busqueda%", "%$busqueda%"];
        $where  = "AND (p.documento LIKE ? OR p.nombre LIKE ?)";

        $total = Database::fetchOne(
            "SELECT COUNT(*) AS total FROM Pacientes p WHERE p.activo=1 $where",
            $params
        )['total'] ?? 0;

        $pacientes = Database::fetchAll(
            "SELECT p.id, p.documento, p.nombre, p.paquete, p.nui, p.fecha_creacion,
                    COUNT(a.id) AS num_atenciones
             FROM Pacientes p
             LEFT JOIN Atenciones a ON a.paciente_id = p.id
             WHERE p.activo=1 $where
             GROUP BY p.id
             ORDER BY p.nombre ASC
             LIMIT $porPagina OFFSET $offset",
            $params
        );

        $totalPaginas = (int)ceil($total / $porPagina);
    }
    require BASE_PATH . '/app/Views/pacientes/index.php';
    exit;
}

// ── GET/POST /pacientes/crear ─────────────────────────────────────────────────
if ($uri === '/pacientes/crear') {
    Auth::requireRole(ROL_ADMINISTRADOR, ROL_FACTURADOR, ROL_EQUIPO_PPL);

    $errors = [];
    $values = ['documento' => '', 'nombre' => '', 'paquete' => 1, 'nui' => '', 'fecha_nacimiento' => ''];

    if ($method === 'POST') {
        Security::verifyCsrf();

        $doc    = Security::sanitizeString($_POST['documento'] ?? '', 20);
        $nombre = Security::sanitizeString($_POST['nombre'] ?? '', 200);
        $paquete = Security::validateInt($_POST['paquete'] ?? '', 1, 2);
        $nui    = Security::sanitizeString($_POST['nui'] ?? '', 30);
        $fnac   = trim($_POST['fecha_nacimiento'] ?? '');
        $fnac   = ($fnac !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fnac)) ? $fnac : null;

        if ($doc === '') $errors[] = 'El documento es obligatorio.';
        if (!preg_match('/^[0-9A-Za-z\-]{1,20}$/', $doc)) $errors[] = 'Documento inválido.';
        if ($nombre === '') $errors[] = 'El nombre es obligatorio.';
        if ($paquete === null) $errors[] = 'Paquete inválido.';

        if (empty($errors)) {
            $existe = Database::fetchOne("SELECT id FROM Pacientes WHERE documento=?", [$doc]);
            if ($existe) {
                $errors[] = "Ya existe un paciente con el documento $doc.";
            } else {
                Database::insert(
                    "INSERT INTO Pacientes (documento, nombre, paquete, nui, fecha_nacimiento) VALUES (?,?,?,?,?)",
                    [$doc, $nombre, $paquete, $nui !== '' ? $nui : null, $fnac]
                );
                Auth::audit(Auth::username(), 'PACIENTE_CREADO', "Documento: $doc");
                header('Location: /pacientes?ok=1');
                exit;
            }
        }
        $values = ['documento' => $doc, 'nombre' => $nombre, 'paquete' => $paquete ?? 1, 'nui' => $nui, 'fecha_nacimiento' => $fnac ?? ''];
    }

    require BASE_PATH . '/app/Views/pacientes/form.php';
    exit;
}

// ── GET/POST /pacientes/{id}/editar ───────────────────────────────────────────
if ($pacienteId !== null && str_ends_with($uri, '/editar')) {
    Auth::requireRole(ROL_ADMINISTRADOR, ROL_FACTURADOR);

    $paciente = Database::fetchOne("SELECT * FROM Pacientes WHERE id=?", [$pacienteId]);
    if (!$paciente) { http_response_code(404); require BASE_PATH . '/app/Views/errors/404.php'; exit; }

    $errors = [];

    if ($method === 'POST') {
        Security::verifyCsrf();

        $doc    = Security::sanitizeString($_POST['documento'] ?? '', 20);
        $nombre = Security::sanitizeString($_POST['nombre'] ?? '', 200);
        $paquete = Security::validateInt($_POST['paquete'] ?? '', 1, 2);
        $nui    = Security::sanitizeString($_POST['nui'] ?? '', 30);
        $activo  = isset($_POST['activo']) ? 1 : 0;
        $fnac   = trim($_POST['fecha_nacimiento'] ?? '');
        $fnac   = ($fnac !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fnac)) ? $fnac : null;

        if ($doc === '' || $nombre === '' || $paquete === null) {
            $errors[] = 'Todos los campos son obligatorios.';
        }

        if (empty($errors)) {
            $dup = Database::fetchOne(
                "SELECT id FROM Pacientes WHERE documento=? AND id!=?", [$doc, $pacienteId]
            );
            if ($dup) {
                $errors[] = "El documento $doc ya está registrado en otro paciente.";
            } else {
                Database::execute(
                    "UPDATE Pacientes SET documento=?, nombre=?, paquete=?, nui=?, fecha_nacimiento=?, activo=? WHERE id=?",
                    [$doc, $nombre, $paquete, $nui !== '' ? $nui : null, $fnac, $activo, $pacienteId]
                );
                Auth::audit(Auth::username(), 'PACIENTE_EDITADO', "ID: $pacienteId");
                header('Location: /pacientes?ok=2');
                exit;
            }
        }
        $paciente = array_merge($paciente, ['documento'=>$doc,'nombre'=>$nombre,'paquete'=>$paquete,'activo'=>$activo,'fecha_nacimiento'=>$fnac ?? '']);
    }

    $values = $paciente;
    $modoEditar = true;
    require BASE_PATH . '/app/Views/pacientes/form.php';
    exit;
}

// ── GET /pacientes/importar/plantilla ────────────────────────────────────────
if ($uri === '/pacientes/importar/plantilla' && $method === 'GET') {
    Auth::requireRole(ROL_ADMINISTRADOR, ROL_FACTURADOR, ROL_EQUIPO_PPL);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="plantilla_pacientes.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Documento (CC)', 'Nombre completo', 'Paquete', 'NUI', 'Fecha Nacimiento (dd/mm/yyyy)'], ';');
    fputcsv($out, ['12345678', 'PEREZ GARCIA JUAN PABLO', '1', 'NUI-0001', '15/06/1985'], ';');
    fputcsv($out, ['87654321', 'MARTINEZ LOPEZ MARIA', '2', '', '20/11/1990'], ';');
    fputcsv($out, ['AB12CD', 'GOMEZ TORRES PEDRO', '1', '', ''], ';');
    fclose($out);
    exit;
}

// ── GET /pacientes/exportar ───────────────────────────────────────────────────
if ($uri === '/pacientes/exportar' && $method === 'GET') {
    Auth::requireRole(ROL_ADMINISTRADOR, ROL_FACTURADOR, ROL_EQUIPO_PPL, ROL_ESTADISTICO);

    $pacientes = Database::fetchAll(
        "SELECT documento, nombre, paquete, nui, fecha_nacimiento, fecha_creacion FROM Pacientes WHERE activo=1 ORDER BY nombre ASC",
        []
    );

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="pacientes_' . date('Ymd_His') . '.csv"');
    header('Pragma: no-cache');
    echo "\xEF\xBB\xBF"; // BOM UTF-8 para Excel

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Documento (CC)', 'Nombre completo', 'Paquete', 'NUI', 'Fecha Nacimiento'], ';');
    foreach ($pacientes as $p) {
        fputcsv($out, [
            $p['documento'],
            $p['nombre'],
            $p['paquete'],
            $p['nui'] ?? '',
            $p['fecha_nacimiento'] ? date('d/m/Y', strtotime($p['fecha_nacimiento'])) : '',
        ], ';');
    }
    fclose($out);
    Auth::audit(Auth::username(), 'PACIENTES_EXPORTADOS', 'Exportación CSV ' . count($pacientes) . ' registros');
    exit;
}

// ── GET /pacientes/importar ───────────────────────────────────────────────────
if ($uri === '/pacientes/importar' && $method === 'GET') {
    Auth::requireRole(ROL_ADMINISTRADOR, ROL_FACTURADOR, ROL_EQUIPO_PPL);
    require BASE_PATH . '/app/Views/pacientes/importar.php';
    exit;
}

// ── POST /pacientes/importar ──────────────────────────────────────────────────
if ($uri === '/pacientes/importar' && $method === 'POST') {
    Auth::requireRole(ROL_ADMINISTRADOR, ROL_FACTURADOR, ROL_EQUIPO_PPL);
    Security::verifyCsrf();

    $importErrors  = [];
    $insertados    = 0;
    $actualizados  = 0;

    if (empty($_FILES['csv']['tmp_name'])) {
        $importErrors[] = 'No se recibió ningún archivo.';
    } else {
        $tmp = $_FILES['csv']['tmp_name'];
        $handle = fopen($tmp, 'r');
        if (!$handle) {
            $importErrors[] = 'No se pudo leer el archivo.';
        } else {
            // Detectar delimitador (coma o punto y coma)
            $firstLine = fgets($handle);
            rewind($handle);
            $delim = (substr_count($firstLine, ';') >= substr_count($firstLine, ',')) ? ';' : ',';

            $fila = 0;
            while (($row = fgetcsv($handle, 1000, $delim)) !== false) {
                $fila++;
                // Saltar encabezado
                if ($fila === 1) {
                    $firstCell = mb_strtolower(trim($row[0] ?? ''));
                    if (str_contains($firstCell, 'doc') || str_contains($firstCell, 'cc')) continue;
                }
                if (count($row) < 2) { $importErrors[] = "Fila $fila: formato incorrecto."; continue; }

                $doc    = Security::sanitizeString(trim($row[0] ?? ''), 20);
                $nombre = Security::sanitizeString(trim($row[1] ?? ''), 200);
                $paquete = (int)trim($row[2] ?? 1);
                $nui    = Security::sanitizeString(trim($row[3] ?? ''), 30);
                $fnacRaw = trim($row[4] ?? '');
                // Acepta formatos dd/mm/yyyy, dd-mm-yyyy o yyyy-mm-dd
                $fnac = null;
                if ($fnacRaw !== '') {
                    if (preg_match('/^(\d{2})[\/-](\d{2})[\/-](\d{4})$/', $fnacRaw, $m)) {
                        $fnac = "{$m[3]}-{$m[2]}-{$m[1]}";
                    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fnacRaw)) {
                        $fnac = $fnacRaw;
                    }
                }

                if ($doc === '')    { $importErrors[] = "Fila $fila: documento vacío."; continue; }
                if (!preg_match('/^[0-9A-Za-z\-]{1,20}$/', $doc)) { $importErrors[] = "Fila $fila: documento inválido ($doc)."; continue; }
                if ($nombre === '') { $importErrors[] = "Fila $fila: nombre vacío."; continue; }
                if (!in_array($paquete, [1, 2])) $paquete = 1;

                $existe = Database::fetchOne("SELECT id FROM Pacientes WHERE documento=?", [$doc]);
                if ($existe) {
                    Database::execute(
                        "UPDATE Pacientes SET nombre=?, paquete=?, nui=?, fecha_nacimiento=? WHERE documento=?",
                        [$nombre, $paquete, $nui !== '' ? $nui : null, $fnac, $doc]
                    );
                    $actualizados++;
                } else {
                    Database::insert(
                        "INSERT INTO Pacientes (documento, nombre, paquete, nui, fecha_nacimiento) VALUES (?,?,?,?,?)",
                        [$doc, $nombre, $paquete, $nui !== '' ? $nui : null, $fnac]
                    );
                    $insertados++;
                }
            }
            fclose($handle);
        }
    }

    Auth::audit(Auth::username(), 'PACIENTES_IMPORTADOS', "Insertados: $insertados, Actualizados: $actualizados");
    $importResult = compact('insertados', 'actualizados', 'importErrors');
    require BASE_PATH . '/app/Views/pacientes/importar.php';
    exit;
}
