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

    $where  = $busqueda !== '' ? "AND (p.documento LIKE ? OR p.nombre LIKE ?)" : '';
    $params = $busqueda !== '' ? ["%$busqueda%", "%$busqueda%"] : [];

    $total = Database::fetchOne(
        "SELECT COUNT(*) AS total FROM Pacientes p WHERE p.activo=1 $where",
        $params
    )['total'] ?? 0;

    $pacientes = Database::fetchAll(
        "SELECT p.id, p.documento, p.nombre, p.paquete, p.fecha_creacion,
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
    require BASE_PATH . '/app/Views/pacientes/index.php';
    exit;
}

// ── GET/POST /pacientes/crear ─────────────────────────────────────────────────
if ($uri === '/pacientes/crear') {
    Auth::requireRole(ROL_ADMINISTRADOR, ROL_FACTURADOR, ROL_EQUIPO_PPL);

    $errors = [];
    $values = ['documento' => '', 'nombre' => '', 'paquete' => 1];

    if ($method === 'POST') {
        Security::verifyCsrf();

        $doc    = Security::sanitizeString($_POST['documento'] ?? '', 20);
        $nombre = Security::sanitizeString($_POST['nombre'] ?? '', 200);
        $paquete = Security::validateInt($_POST['paquete'] ?? '', 1, 2);

        if ($doc === '') $errors[] = 'El documento es obligatorio.';
        if (!preg_match('/^[0-9A-Za-z\-]{3,20}$/', $doc)) $errors[] = 'Documento inválido.';
        if ($nombre === '') $errors[] = 'El nombre es obligatorio.';
        if ($paquete === null) $errors[] = 'Paquete inválido.';

        if (empty($errors)) {
            // Verificar duplicado
            $existe = Database::fetchOne("SELECT id FROM Pacientes WHERE documento=?", [$doc]);
            if ($existe) {
                $errors[] = "Ya existe un paciente con el documento $doc.";
            } else {
                Database::insert(
                    "INSERT INTO Pacientes (documento, nombre, paquete) VALUES (?,?,?)",
                    [$doc, $nombre, $paquete]
                );
                Auth::audit(Auth::username(), 'PACIENTE_CREADO', "Documento: $doc");
                header('Location: /pacientes?ok=1');
                exit;
            }
        }
        $values = ['documento' => $doc, 'nombre' => $nombre, 'paquete' => $paquete ?? 1];
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
        $activo  = isset($_POST['activo']) ? 1 : 0;

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
                    "UPDATE Pacientes SET documento=?, nombre=?, paquete=?, activo=? WHERE id=?",
                    [$doc, $nombre, $paquete, $activo, $pacienteId]
                );
                Auth::audit(Auth::username(), 'PACIENTE_EDITADO', "ID: $pacienteId");
                header('Location: /pacientes?ok=2');
                exit;
            }
        }
        $paciente = array_merge($paciente, ['documento'=>$doc,'nombre'=>$nombre,'paquete'=>$paquete,'activo'=>$activo]);
    }

    $values = $paciente;
    $modoEditar = true;
    require BASE_PATH . '/app/Views/pacientes/form.php';
    exit;
}
