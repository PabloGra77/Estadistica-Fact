<?php
/**
 * AtencionesController
 */
Auth::requireAuth();

// ── GET /atenciones ───────────────────────────────────────────────────────────
if ($uri === '/atenciones' && $method === 'GET') {
    $busqueda  = Security::sanitizeString($_GET['q'] ?? '', 100);
    $filtroMes  = Security::validateInt($_GET['mes'] ?? '', 1, 12);
    $filtroAnio = Security::validateInt($_GET['anio'] ?? '', 2020, 2099);
    $pagina    = max(1, (int)($_GET['p'] ?? 1));
    $porPagina = 20;
    $offset    = ($pagina - 1) * $porPagina;

    $conds  = ['1=1'];
    $params = [];

    if ($busqueda !== '') {
        $conds[]  = '(p.documento LIKE ? OR p.nombre LIKE ?)';
        $params[] = "%$busqueda%";
        $params[] = "%$busqueda%";
    }
    if ($filtroMes !== null)  { $conds[] = 'a.mes_atencion=?';  $params[] = $filtroMes; }
    if ($filtroAnio !== null) { $conds[] = 'a.anio_atencion=?'; $params[] = $filtroAnio; }

    $where = implode(' AND ', $conds);

    $total = Database::fetchOne(
        "SELECT COUNT(*) AS total FROM Atenciones a JOIN Pacientes p ON p.id=a.paciente_id WHERE $where",
        $params
    )['total'] ?? 0;

    $atenciones = Database::fetchAll(
        "SELECT a.id, a.servicio, a.mes_atencion, a.anio_atencion, a.fecha_carga, a.cargado_por,
                p.nombre AS paciente_nombre, p.documento,
                COUNT(s.id) AS num_soportes
         FROM Atenciones a
         JOIN Pacientes p ON p.id=a.paciente_id
         LEFT JOIN Soportes s ON s.atencion_id=a.id
         WHERE $where
         GROUP BY a.id
         ORDER BY a.fecha_carga DESC
         LIMIT $porPagina OFFSET $offset",
        $params
    );

    $totalPaginas = (int)ceil($total / $porPagina);
    require BASE_PATH . '/app/Views/atenciones/index.php';
    exit;
}

// ── GET/POST /atenciones/crear ────────────────────────────────────────────────
if ($uri === '/atenciones/crear') {
    Auth::requireRole(ROL_ADMINISTRADOR, ROL_FACTURADOR, ROL_EQUIPO_PPL);

    $errors = [];
    $values = ['paciente_id' => '', 'servicio' => '', 'anio_atencion' => date('Y'), 'mes_atencion' => date('n')];
    $pacientes = Database::fetchAll("SELECT id, documento, nombre FROM Pacientes WHERE activo=1 ORDER BY nombre");

    if ($method === 'POST') {
        Security::verifyCsrf();

        $pacienteId   = Security::validateInt($_POST['paciente_id'] ?? '', 1);
        $servicio     = Security::validateInt($_POST['servicio'] ?? '', 0, 5);
        $anioAtencion = Security::validateInt($_POST['anio_atencion'] ?? '', 2020, 2099);
        $mesAtencion  = Security::validateInt($_POST['mes_atencion'] ?? '', 1, 12);

        if ($pacienteId === null) $errors[] = 'Seleccione un paciente.';
        if ($servicio === null)   $errors[] = 'Seleccione un servicio.';
        if ($anioAtencion === null || $mesAtencion === null) $errors[] = 'Año y mes inválidos.';

        if (empty($errors)) {
            // Verificar duplicado
            $existe = Database::fetchOne(
                "SELECT id FROM Atenciones WHERE paciente_id=? AND servicio=? AND anio_atencion=? AND mes_atencion=?",
                [$pacienteId, $servicio, $anioAtencion, $mesAtencion]
            );
            if ($existe) {
                $errors[] = 'Ya existe una atención registrada para ese paciente, servicio y período.';
            } else {
                Database::insert(
                    "INSERT INTO Atenciones (paciente_id, servicio, anio_atencion, mes_atencion, cargado_por)
                     VALUES (?,?,?,?,?)",
                    [$pacienteId, $servicio, $anioAtencion, $mesAtencion, Auth::username()]
                );
                Auth::audit(Auth::username(), 'ATENCION_CREADA',
                    "Paciente: $pacienteId | Servicio: $servicio | $mesAtencion/$anioAtencion");
                header('Location: /atenciones?ok=1');
                exit;
            }
        }
        $values = [
            'paciente_id'  => $pacienteId,
            'servicio'     => $servicio,
            'anio_atencion'=> $anioAtencion,
            'mes_atencion' => $mesAtencion,
        ];
    }

    require BASE_PATH . '/app/Views/atenciones/form.php';
    exit;
}
