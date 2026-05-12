<?php
/**
 * HomeController — dashboard principal.
 */
Auth::requireAuth();

// Estadísticas rápidas
$totalPacientes   = Database::fetchOne("SELECT COUNT(*) AS total FROM Pacientes WHERE activo=1")['total'] ?? 0;
$totalAtenciones  = Database::fetchOne("SELECT COUNT(*) AS total FROM Atenciones")['total'] ?? 0;
$soportesPendientes = Database::fetchOne("SELECT COUNT(*) AS total FROM Soportes WHERE estado=0")['total'] ?? 0;
$soportesAprobados  = Database::fetchOne(
    "SELECT COUNT(*) AS total FROM Soportes WHERE estado IN (1,2)"
)['total'] ?? 0;

// Últimas atenciones del mes actual
$mesActual  = (int)date('m');
$anioActual = (int)date('Y');
$ultimasAtenciones = Database::fetchAll(
    "SELECT a.id, p.nombre, p.documento, a.fecha_carga, a.cargado_por,
            a.mes_atencion, a.anio_atencion, a.servicio
     FROM Atenciones a
     JOIN Pacientes p ON p.id = a.paciente_id
     WHERE a.mes_atencion = ? AND a.anio_atencion = ?
     ORDER BY a.fecha_carga DESC
     LIMIT 10",
    [$mesActual, $anioActual]
);

require BASE_PATH . '/app/Views/home/index.php';
