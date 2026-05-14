<?php
/**
 * HomeController — dashboard principal.
 */
Auth::requireAuth();

// Período activo
$periodActivo = Database::fetchOne("SELECT * FROM Periodos WHERE activo=1 AND estado='abierto' LIMIT 1");
$periodoMes   = $periodActivo ? (int)$periodActivo['mes']  : (int)date('n');
$periodoAnio  = $periodActivo ? (int)$periodActivo['anio'] : (int)date('Y');

// Estadísticas del período activo (o mes actual si no hay período)
$totalPacientes   = Database::fetchOne("SELECT COUNT(*) AS total FROM Pacientes WHERE activo=1")['total'] ?? 0;
$totalAtenciones  = Database::fetchOne(
    "SELECT COUNT(*) AS total FROM Atenciones WHERE mes_atencion=? AND anio_atencion=?",
    [$periodoMes, $periodoAnio]
)['total'] ?? 0;
$soportesPendientes = Database::fetchOne(
    "SELECT COUNT(*) AS total FROM Soportes s
     JOIN Atenciones a ON a.id = s.atencion_id
     WHERE s.estado=0 AND a.mes_atencion=? AND a.anio_atencion=?",
    [$periodoMes, $periodoAnio]
)['total'] ?? 0;
$soportesAprobados = Database::fetchOne(
    "SELECT COUNT(*) AS total FROM Soportes s
     JOIN Atenciones a ON a.id = s.atencion_id
     WHERE s.estado IN (1,2) AND a.mes_atencion=? AND a.anio_atencion=?",
    [$periodoMes, $periodoAnio]
)['total'] ?? 0;

// Últimas atenciones del período activo
$ultimasAtenciones = Database::fetchAll(
    "SELECT a.id,
            CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre,
            p.documento, a.fecha_carga, a.cargado_por,
            a.mes_atencion, a.anio_atencion, a.servicio
     FROM Atenciones a
     JOIN Pacientes p ON p.id = a.paciente_id
     WHERE a.mes_atencion = ? AND a.anio_atencion = ?
     ORDER BY a.fecha_carga DESC
     LIMIT 10",
    [$periodoMes, $periodoAnio]
);

require BASE_PATH . '/app/Views/home/index.php';
