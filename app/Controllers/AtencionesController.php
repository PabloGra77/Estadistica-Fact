<?php
/**
 * AtencionesController
 */
Auth::requireAuth();

$periodActivo = Database::fetchOne("SELECT * FROM Periodos WHERE activo=1 AND estado='abierto' LIMIT 1");

// ── GET /atenciones/exportar-excel ───────────────────────────────────────────
if ($uri === '/atenciones/exportar-excel' && $method === 'GET') {
    Auth::requireRole(ROL_ADMINISTRADOR, ROL_FACTURADOR, ROL_ESTADISTICO, ROL_EQUIPO_PPL);

    $chartMes  = $periodActivo ? (int)$periodActivo['mes']  : (int)date('n');
    $chartAnio = $periodActivo ? (int)$periodActivo['anio'] : (int)date('Y');
    $mesNombres = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    $nombrePeriodo = $periodActivo ? $periodActivo['nombre'] : ($mesNombres[$chartMes].' '.$chartAnio);

    // ── Misma lógica de datos que el tablero ──────────────────────────────────
    $coberturaRows = Database::fetchAll(
        "SELECT p.id AS pid, p.documento,
                CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre,
                p.paquete, a.servicio, COUNT(s.id) AS con_soporte
         FROM Atenciones a
         JOIN Pacientes p ON p.id = a.paciente_id
         LEFT JOIN Soportes s ON s.atencion_id = a.id
         WHERE a.mes_atencion = ? AND a.anio_atencion = ? AND p.activo = 1
         GROUP BY p.id, p.documento, p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido, p.paquete, a.servicio
         ORDER BY p.primer_apellido, p.primer_nombre, a.servicio",
        [$chartMes, $chartAnio]
    );
    $matrizCobertura = [];
    foreach ($coberturaRows as $r) {
        $pid = $r['pid'];
        if (!isset($matrizCobertura[$pid])) {
            $matrizCobertura[$pid] = ['nombre'=>$r['nombre'],'documento'=>$r['documento'],'paquete'=>(int)$r['paquete'],'servicios'=>[]];
        }
        $matrizCobertura[$pid]['servicios'][(int)$r['servicio']] = (int)$r['con_soporte'];
    }
    $todosPacientes = Database::fetchAll(
        "SELECT id, documento, paquete, CONCAT_WS(' ', primer_nombre, segundo_nombre, primer_apellido, segundo_apellido) AS nombre
         FROM Pacientes WHERE activo=1 ORDER BY primer_apellido, primer_nombre"
    );
    foreach ($todosPacientes as $tp) {
        if (!isset($matrizCobertura[(int)$tp['id']])) {
            $matrizCobertura[(int)$tp['id']] = ['nombre'=>$tp['nombre'],'documento'=>$tp['documento'],'paquete'=>(int)$tp['paquete'],'servicios'=>[]];
        }
    }
    // REG automático para todos
    $regIdx = array_search('REG', TIPOS_SERVICIO);
    if ($regIdx !== false) {
        foreach ($matrizCobertura as &$px) { $px['servicios'][$regIdx] = max(1, $px['servicios'][$regIdx] ?? 1); }
        unset($px);
    }
    // Calcular stats
    $totalP1=0; $totalP2=0; $totalEV=0;
    $completosP1P2=0; $facturablesEV=0; $sinSoportes=0;
    $totalFactEst=0; $totalFactMax=0;
    $servicioStats = array_fill(0, count(TIPOS_SERVICIO), ['con'=>0,'sin'=>0]);
    foreach ($matrizCobertura as &$px) {
        $pctPond = 0; $esEV = ($px['paquete']===3);
        foreach ($px['servicios'] as $si => $cnt) { if ($cnt>0) $pctPond += (PESOS_SERVICIO[$si]??0); }
        foreach (TIPOS_SERVICIO as $si => $sc) {
            if (isset($px['servicios'][$si]) && $px['servicios'][$si]>0) $servicioStats[$si]['con']++;
            else $servicioStats[$si]['sin']++;
        }
        if ($esEV) $totalEV++; elseif ($px['paquete']===1) $totalP1++; else $totalP2++;
        $precioBase = PRECIO_PAQUETE[$px['paquete']] ?? PRECIO_PAQUETE[1];
        $px['pct']      = $pctPond;
        $px['es_evento']= $esEV;
        $px['completo'] = $esEV ? ($pctPond>0) : ($pctPond>=PCT_CUMPLIMIENTO);
        $px['valor']    = (int)round($precioBase * $pctPond / 100);
        $px['precio_base'] = $precioBase;
        $totalFactEst += $px['valor'];
        $totalFactMax += $precioBase;
        $tieneSop = false;
        foreach ($px['servicios'] as $c) if ($c>0) { $tieneSop=true; break; }
        if (!$tieneSop) $sinSoportes++;
        if ($px['completo']) { if ($esEV) $facturablesEV++; else $completosP1P2++; }
    }
    unset($px);
    $totalPacientes = count($matrizCobertura);
    $pctFact = $totalFactMax>0 ? round($totalFactEst/$totalFactMax*100) : 0;

    // ── Generar .xlsx real (Open XML / OOXML) con ZipArchive ──────────────────
    require_once BASE_PATH . '/app/Helpers/XlsxWriter.php';
    $W = new XlsxWriter();

    // ── HOJA 1: RESUMEN (4 columnas) ──────────────────────────────────────────
    $s0 = $W->addSheet('Resumen');
    $W->colWidth($s0, 0, 42); $W->colWidth($s0, 1, 18);
    $W->colWidth($s0, 2, 22); $W->colWidth($s0, 3, 18);

    $W->row($s0, [['INFORME DE COBERTURA DE SOPORTES — PPL · IPS Goleman', XlsxWriter::S_TITLE, 4]], 32);
    $W->emptyRow($s0);
    $W->row($s0, [
        ['Período:',        XlsxWriter::S_INFO_LBL], [$nombrePeriodo,     XlsxWriter::S_INFO_VAL],
        ['Generado:',       XlsxWriter::S_INFO_LBL], [date('d/m/Y H:i'), XlsxWriter::S_INFO_VAL],
    ]);
    $W->emptyRow($s0);
    $W->row($s0, [['COMPOSICIÓN DE PACIENTES', XlsxWriter::S_SEC_HDR, 4]], 20);
    $W->row($s0, [['Total pacientes activos',    XlsxWriter::S_LBL], [$totalPacientes,  XlsxWriter::S_VAL], ['', XlsxWriter::S_LBL], ['', XlsxWriter::S_LBL]]);
    $W->row($s0, [['  · Paquete 1 (PPL)',        XlsxWriter::S_LBL], [$totalP1,         XlsxWriter::S_VAL], ['Precio base P1:', XlsxWriter::S_LBL], [PRECIO_PAQUETE[1], XlsxWriter::S_CUR]]);
    $W->row($s0, [['  · Paquete 2 (PPL Salud)',  XlsxWriter::S_LBL], [$totalP2,         XlsxWriter::S_VAL], ['Precio base P2:', XlsxWriter::S_LBL], [PRECIO_PAQUETE[2], XlsxWriter::S_CUR]]);
    if ($totalEV > 0) {
        $W->row($s0, [['  · Evento (Prorrateo)', XlsxWriter::S_LBL], [$totalEV,         XlsxWriter::S_VAL], ['Precio base Evento:', XlsxWriter::S_LBL], [PRECIO_PAQUETE[3], XlsxWriter::S_CUR]]);
    }
    $W->emptyRow($s0);
    $W->row($s0, [['ESTADO DE COBERTURA', XlsxWriter::S_SEC_HDR, 4]], 20);
    $W->row($s0, [['Pacientes P1/P2 completos (≥' . PCT_CUMPLIMIENTO . '%)', XlsxWriter::S_LBL], [$completosP1P2, XlsxWriter::S_VAL], ['de ' . ($totalP1 + $totalP2) . ' P1/P2', XlsxWriter::S_LBL], ['', XlsxWriter::S_LBL]]);
    if ($totalEV > 0) {
        $W->row($s0, [['Pacientes Evento con al menos 1 soporte', XlsxWriter::S_LBL], [$facturablesEV, XlsxWriter::S_VAL], ['de ' . $totalEV . ' Evento', XlsxWriter::S_LBL], ['', XlsxWriter::S_LBL]]);
    }
    $W->row($s0, [['Pacientes sin ningún soporte cargado', XlsxWriter::S_LBL], [$sinSoportes, XlsxWriter::S_VAL], ['', XlsxWriter::S_LBL], ['', XlsxWriter::S_LBL]]);
    $W->emptyRow($s0);
    $W->row($s0, [['FACTURACIÓN ESTIMADA DEL PERÍODO', XlsxWriter::S_SEC_HDR, 4]], 20);
    $W->row($s0, [['Facturación estimada (soportes cargados)', XlsxWriter::S_LBL], [$totalFactEst, XlsxWriter::S_CUR], ['', XlsxWriter::S_LBL], ['', XlsxWriter::S_LBL]]);
    $W->row($s0, [['Facturación máxima posible (100% todos)',   XlsxWriter::S_LBL], [$totalFactMax, XlsxWriter::S_CUR], ['', XlsxWriter::S_LBL], ['', XlsxWriter::S_LBL]]);
    $W->row($s0, [['Porcentaje de cumplimiento',                XlsxWriter::S_LBL], [$pctFact,     XlsxWriter::S_PCT], ['', XlsxWriter::S_LBL], ['', XlsxWriter::S_LBL]]);
    $W->emptyRow($s0);
    $W->row($s0, [['COBERTURA POR TIPO DE SERVICIO', XlsxWriter::S_SEC_HDR, 4]], 20);
    $W->row($s0, [['Servicio', XlsxWriter::S_COL_HDR], ['Peso (%)', XlsxWriter::S_COL_HDR], ['Con Soporte', XlsxWriter::S_COL_HDR], ['Sin Soporte', XlsxWriter::S_COL_HDR]]);
    foreach (TIPOS_SERVICIO as $si => $svcName) {
        $W->row($s0, [[$svcName, XlsxWriter::S_LBL], [PESOS_SERVICIO[$si] ?? 0, XlsxWriter::S_VAL], [$servicioStats[$si]['con'], XlsxWriter::S_VAL], [$servicioStats[$si]['sin'], XlsxWriter::S_VAL]]);
    }
    $W->emptyRow($s0);
    $W->row($s0, [['NOTA: REG (Regencia) se marca automáticamente para todos los pacientes PPL.', XlsxWriter::S_LBL, 4]]);

    // ── HOJA 2: MATRIZ DE COBERTURA ───────────────────────────────────────────
    $numSvc    = count(TIPOS_SERVICIO); // 10
    $totalCols = 3 + $numSvc + 4;      // 17
    $s1 = $W->addSheet('Matriz de Cobertura');
    $W->colWidth($s1, 0, 28); $W->colWidth($s1, 1, 14); $W->colWidth($s1, 2, 6);
    for ($ci = 0; $ci < $numSvc; $ci++) { $W->colWidth($s1, 3 + $ci, 7); }
    $W->colWidth($s1, 3 + $numSvc,     7);
    $W->colWidth($s1, 3 + $numSvc + 1, 14);
    $W->colWidth($s1, 3 + $numSvc + 2, 14);
    $W->colWidth($s1, 3 + $numSvc + 3, 11);

    $W->row($s1, [['Matriz de Cobertura — ' . $nombrePeriodo, XlsxWriter::S_TITLE, $totalCols]], 28);
    $hdrRow = [['Apellidos y Nombres', XlsxWriter::S_COL_HDR], ['Documento', XlsxWriter::S_COL_HDR], ['Paq.', XlsxWriter::S_COL_HDR]];
    foreach (TIPOS_SERVICIO as $si => $svcName) {
        $hdrRow[] = [$svcName . "\n" . (PESOS_SERVICIO[$si] ?? 0) . '%', XlsxWriter::S_COL_HDR];
    }
    $hdrRow[] = ['% Cob.', XlsxWriter::S_COL_HDR];
    $hdrRow[] = ['Facturado', XlsxWriter::S_COL_HDR];
    $hdrRow[] = ['Base', XlsxWriter::S_COL_HDR];
    $hdrRow[] = ['Estado', XlsxWriter::S_COL_HDR];
    $W->row($s1, $hdrRow, 36);

    foreach ($matrizCobertura as $pac) {
        $pct       = $pac['pct'];
        $esEV      = $pac['es_evento'];
        $completo  = $pac['completo'];
        $badgeSty  = $pac['paquete'] === 1 ? XlsxWriter::S_P1 : ($pac['paquete'] === 2 ? XlsxWriter::S_P2 : XlsxWriter::S_EV);
        $badgeLbl  = $pac['paquete'] === 1 ? 'P1' : ($pac['paquete'] === 2 ? 'P2' : 'EV');
        $curSty    = $completo ? ($esEV ? XlsxWriter::S_CUR_EV : XlsxWriter::S_CUR_OK) : XlsxWriter::S_CELL_CUR;
        $estadoTxt = $esEV ? ($completo ? 'Prorrateo' : 'Sin soportes') : ($completo ? 'Completo' : ($pct > 0 ? 'Parcial' : 'Sin soportes'));
        $pacRow = [[$pac['nombre'], XlsxWriter::S_CELL_L], [$pac['documento'], XlsxWriter::S_CELL], [$badgeLbl, $badgeSty]];
        foreach (TIPOS_SERVICIO as $si => $svcName) {
            $tiene = isset($pac['servicios'][$si]) && $pac['servicios'][$si] > 0;
            $pacRow[] = [$tiene ? '✓' : '✗', $tiene ? XlsxWriter::S_OK : XlsxWriter::S_NO];
        }
        $pacRow[] = [$pct,              XlsxWriter::S_CELL_PCT];
        $pacRow[] = [$pac['valor'],     $curSty];
        $pacRow[] = [$pac['precio_base'], XlsxWriter::S_CELL_CUR];
        $pacRow[] = [$estadoTxt,        XlsxWriter::S_CELL];
        $W->row($s1, $pacRow);
    }

    $totalLabel = 'TOTAL — ' . $totalPacientes . ' pacientes  (P1:' . $totalP1 . ' · P2:' . $totalP2 . ($totalEV > 0 ? ' · EV:' . $totalEV : '') . ')';
    $totRow = [[$totalLabel, XlsxWriter::S_TOT, 3]];
    foreach (TIPOS_SERVICIO as $si => $svcName) { $totRow[] = [$servicioStats[$si]['con'], XlsxWriter::S_TOT]; }
    $totRow[] = [$pctFact,    XlsxWriter::S_TOT];
    $totRow[] = [$totalFactEst, XlsxWriter::S_TOT_CUR];
    $totRow[] = [$totalFactMax, XlsxWriter::S_TOT_CUR];
    $totRow[] = [($completosP1P2 + $facturablesEV) . ' facturables', XlsxWriter::S_TOT];
    $W->row($s1, $totRow, 20);

    // ── Enviar el archivo ──────────────────────────────────────────────────────
    $xlsxData = $W->build();
    $filename  = 'Informe_PPL_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $nombrePeriodo) . '_' . date('Ymd_Hi') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($xlsxData));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    echo $xlsxData;
    exit;
}

// ── GET /atenciones ───────────────────────────────────────────────────────────
if ($uri === '/atenciones' && $method === 'GET') {
    $busqueda  = Security::sanitizeString($_GET['q'] ?? '', 100);
    $pagina    = max(1, (int)($_GET['p'] ?? 1));
    $porPagina = 20;
    $offset    = ($pagina - 1) * $porPagina;
        $tabActivo = ($_GET['tab'] ?? 'lista') === 'tablero' ? 'tablero' : 'lista';

    // Siempre usar el período activo; si no hay, usar mes/año actual
    $chartMes  = $periodActivo ? (int)$periodActivo['mes']  : (int)date('n');
    $chartAnio = $periodActivo ? (int)$periodActivo['anio'] : (int)date('Y');

    $conds  = ['a.mes_atencion=?', 'a.anio_atencion=?'];
    $params = [$chartMes, $chartAnio];

    if ($busqueda !== '') {
        $conds[]  = "(p.documento LIKE ? OR CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) LIKE ?)";
        $params[] = "%$busqueda%";
        $params[] = "%$busqueda%";
    }

    $where = implode(' AND ', $conds);

    $total = Database::fetchOne(
        "SELECT COUNT(*) AS total FROM Atenciones a JOIN Pacientes p ON p.id=a.paciente_id WHERE $where",
        $params
    )['total'] ?? 0;

    $atenciones = Database::fetchAll(
        "SELECT a.id, a.servicio, a.mes_atencion, a.anio_atencion, a.fecha_carga, a.cargado_por,
                CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS paciente_nombre,
                p.documento,
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

    // ── Tablero de cobertura: solo se calcula si el usuario pidió esa pestaña ──
    $matrizCobertura    = [];
    $servicioStats      = array_fill(0, count(TIPOS_SERVICIO), ['con' => 0, 'sin' => 0]);
    $totalPacientesChart  = 0;
    $totalP1 = $totalP2 = $totalEventoPacientes = 0;
    $conCoberturaCompleta = $conCoberturaTotal = 0;
    $completosP1P2 = $facturablesEvento = 0;
    $totalSoportesChart = $totalAtencionesChart = 0;
    $totalFacturacionEst = $totalFacturacionMax = 0;

    if ($tabActivo === 'tablero') {
        $coberturaRows = Database::fetchAll(
            "SELECT p.id AS pid, p.documento,
                    CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre,
                    p.paquete, a.servicio,
                    COUNT(s.id) AS con_soporte
             FROM Atenciones a
             JOIN Pacientes p ON p.id = a.paciente_id
             LEFT JOIN Soportes s ON s.atencion_id = a.id
             WHERE a.mes_atencion = ? AND a.anio_atencion = ?
               AND p.activo = 1
             GROUP BY p.id, p.documento, p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido, p.paquete, a.servicio
             ORDER BY p.primer_apellido, p.primer_nombre, a.servicio",
            [$chartMes, $chartAnio]
        );

        foreach ($coberturaRows as $r) {
            $pid = $r['pid'];
            if (!isset($matrizCobertura[$pid])) {
                $matrizCobertura[$pid] = [
                    'nombre'    => $r['nombre'],
                    'documento' => $r['documento'],
                    'paquete'   => (int)$r['paquete'],
                    'servicios' => [],
                ];
            }
            $matrizCobertura[$pid]['servicios'][(int)$r['servicio']] = (int)$r['con_soporte'];
        }

        // Incluir pacientes sin ninguna atención en el período (cobertura 0%)
        $todosPacientes = Database::fetchAll(
            "SELECT id, documento, paquete,
                    CONCAT_WS(' ', primer_nombre, segundo_nombre, primer_apellido, segundo_apellido) AS nombre
             FROM Pacientes WHERE activo = 1
             ORDER BY primer_apellido, primer_nombre"
        );
        foreach ($todosPacientes as $tp) {
            if (!isset($matrizCobertura[(int)$tp['id']])) {
                $matrizCobertura[(int)$tp['id']] = [
                    'nombre'    => $tp['nombre'],
                    'documento' => $tp['documento'],
                    'paquete'   => (int)$tp['paquete'],
                    'servicios' => [],
                ];
            }
        }

        // REG (Regencia) es automático para todos
        $regIdx = array_search('REG', TIPOS_SERVICIO);
        if ($regIdx !== false) {
            foreach ($matrizCobertura as &$px) {
                $px['servicios'][$regIdx] = max(1, $px['servicios'][$regIdx] ?? 1);
            }
            unset($px);
        }

        // Calcular estadísticas
        $totalPacientesChart = count($matrizCobertura);
        $servicioStats = array_fill(0, count(TIPOS_SERVICIO), ['con' => 0, 'sin' => 0]);

        foreach ($matrizCobertura as &$px) {
            $pctPond  = 0;
            $esEvento = ($px['paquete'] === 3);
            foreach ($px['servicios'] as $svcIdx => $cnt) {
                $totalAtencionesChart++;
                if ($cnt > 0) { $totalSoportesChart += $cnt; $pctPond += (PESOS_SERVICIO[$svcIdx] ?? 0); }
            }
            foreach (TIPOS_SERVICIO as $svcIdx => $svcCod) {
                if (isset($px['servicios'][$svcIdx]) && $px['servicios'][$svcIdx] > 0) $servicioStats[$svcIdx]['con']++;
                else $servicioStats[$svcIdx]['sin']++;
            }
            if ($esEvento)                $totalEventoPacientes++;
            elseif ($px['paquete'] === 1) $totalP1++;
            else                          $totalP2++;
            $px['pct_ponderado'] = $pctPond;
            $px['es_evento']     = $esEvento;
            $precioBase = PRECIO_PAQUETE[$px['paquete']] ?? PRECIO_PAQUETE[1];
            $px['valor_facturacion'] = (int)round($precioBase * $pctPond / 100);
            $px['completo']          = $esEvento ? ($pctPond > 0) : ($pctPond >= PCT_CUMPLIMIENTO);
            $totalFacturacionEst    += $px['valor_facturacion'];
            $totalFacturacionMax    += $precioBase;
        }
        unset($px);

        foreach ($matrizCobertura as $px) {
            $tieneSoporte = false;
            foreach ($px['servicios'] as $cnt) if ($cnt > 0) { $tieneSoporte = true; break; }
            if ($tieneSoporte)   $conCoberturaTotal++;
            if ($px['completo']) $conCoberturaCompleta++;
            if ($px['paquete'] === 3 && $px['completo']) $facturablesEvento++;
            elseif ($px['completo'])                      $completosP1P2++;
        }
    } // end if tablero

    require BASE_PATH . '/app/Views/atenciones/index.php';
    exit;
}

// ── GET/POST /atenciones/crear ────────────────────────────────────────────────
if ($uri === '/atenciones/crear') {
    Auth::requireRole(ROL_ADMINISTRADOR, ROL_FACTURADOR, ROL_EQUIPO_PPL);

    // Bloquear si el período activo está cerrado (solo informativo — el campo viene del form)
    if ($periodActivo === false) $periodActivo = null;

    $errors = [];
    $values = [
        'paciente_id'   => '',
        'servicio'      => '',
        'anio_atencion' => $periodActivo ? (int)$periodActivo['anio'] : (int)date('Y'),
        'mes_atencion'  => $periodActivo ? (int)$periodActivo['mes']  : (int)date('n'),
    ];
    $pacientes = Database::fetchAll(
        "SELECT id, documento, CONCAT_WS(' ', primer_nombre, segundo_nombre, primer_apellido, segundo_apellido) AS nombre FROM Pacientes WHERE activo=1 ORDER BY primer_apellido, primer_nombre"
    );

    if ($method === 'POST') {
        Security::verifyCsrf();

        $pacienteId   = Security::validateInt($_POST['paciente_id'] ?? '', 1);
        $servicio     = Security::validateInt($_POST['servicio'] ?? '', 0, 9);
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
