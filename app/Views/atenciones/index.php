<?php $pageTitle = 'Estadística PPL';
require BASE_PATH . '/app/Views/layout/header.php';
$meses = ['','Enero','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
$mesNombres = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$tabActiva = ($_GET['tab'] ?? 'lista') === 'tablero' ? 'tablero' : 'lista';
?>

<div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="fw-bold mb-0"><i class="bi bi-bar-chart-line-fill me-2 text-success"></i>Estadística PPL</h2>
    <?php if (!Auth::isEstadistico()): ?>
    <a href="/atenciones/crear" class="btn btn-success btn-sm">
        <i class="bi bi-plus-lg me-1"></i>Nueva atención
    </a>
    <?php endif; ?>
</div>

<?php if (!empty($_GET['ok'])): ?>
<div class="alert alert-success alert-dismissible fade show py-2" role="alert">
    Atención registrada correctamente.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Tabs -->
<ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item">
        <a class="nav-link <?= $tabActiva==='lista' ? 'active' : '' ?>"
           href="/atenciones?q=<?=urlencode($busqueda)?>&tab=lista">
            <i class="bi bi-list-ul me-1"></i>Lista
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tabActiva==='tablero' ? 'active' : '' ?>"
           href="/atenciones?q=<?=urlencode($busqueda)?>&tab=tablero">
            <i class="bi bi-bar-chart-line-fill me-1"></i>Tablero de Cobertura
        </a>
    </li>
</ul>

<?php if ($tabActiva === 'lista'): ?>
<!-- ═══════════════ TAB LISTA ═══════════════ -->

<?php if ($periodActivo): ?>
<div class="alert alert-primary py-2 mb-3 small">
    <i class="bi bi-lightning-fill me-1"></i>Mostrando atenciones del período activo: <strong><?= Security::e($periodActivo['nombre']) ?></strong>
</div>
<?php else: ?>
<div class="alert alert-secondary py-2 mb-3 small">
    <i class="bi bi-info-circle me-1"></i>Sin período activo — mostrando mes actual: <strong><?= $meses[$chartMes] ?> <?= $chartAnio ?></strong>
</div>
<?php endif; ?>

<form method="get" action="/atenciones" class="mb-3">
    <input type="hidden" name="tab" value="lista"/>
    <div class="input-group input-group-sm" style="max-width:400px">
        <input type="search" name="q" class="form-control form-control-sm"
               placeholder="Buscar paciente..." value="<?= Security::e($busqueda) ?>" maxlength="100"/>
        <button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-search"></i></button>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle small mb-0">
            <thead class="table-light">
                <tr>
                    <th>Paciente</th><th>Documento</th><th>Servicio</th>
                    <th>Período</th><th>Soportes</th><th>Cargado por</th><th>Fecha</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($atenciones)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Sin resultados.</td></tr>
            <?php else: ?>
                <?php foreach ($atenciones as $a): ?>
                <tr>
                    <td class="fw-medium"><?= Security::e($a['paciente_nombre']) ?></td>
                    <td class="text-muted small"><?= Security::e($a['documento']) ?></td>
                    <td><span class="badge text-bg-primary"><?= Security::e(TIPOS_SERVICIO[$a['servicio']] ?? '') ?></span></td>
                    <td><?= Security::e($meses[(int)$a['mes_atencion']]) ?>/<?= Security::e($a['anio_atencion']) ?></td>
                    <td><span class="badge text-bg-<?= (int)$a['num_soportes'] > 0 ? 'success' : 'secondary' ?>">
                        <?= (int)$a['num_soportes'] ?></span></td>
                    <td><?= Security::e($a['cargado_por']) ?></td>
                    <td><?= Security::e(date('d/m/Y', strtotime($a['fecha_carga']))) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($totalPaginas > 1): ?>
<nav class="mt-3"><ul class="pagination pagination-sm justify-content-center">
    <?php for ($i=1;$i<=$totalPaginas;$i++): ?>
    <li class="page-item <?= $i===$pagina?'active':'' ?>">
        <a class="page-link" href="/atenciones?q=<?=urlencode($busqueda)?>&tab=lista&p=<?=$i?>"><?=$i?></a>
    </li>
    <?php endfor; ?>
</ul></nav>
<?php endif; ?>

<?php else: ?>
<!-- ═══════════════ TAB TABLERO DE COBERTURA ═══════════════ -->

<?php if ($periodActivo): ?>
<div class="alert alert-primary py-2 mb-3 small d-flex align-items-center justify-content-between">
    <span><i class="bi bi-lightning-fill me-1"></i>Tablero de cobertura para el período activo: <strong><?= Security::e($periodActivo['nombre']) ?></strong></span>
    <button onclick="exportarExcel()" class="btn btn-success btn-sm ms-3 text-nowrap">
        <i class="bi bi-file-earmark-excel-fill me-1"></i>Exportar Excel
    </button>
</div>
<?php else: ?>
<div class="alert alert-secondary py-2 mb-3 small d-flex align-items-center justify-content-between">
    <span><i class="bi bi-info-circle me-1"></i>Sin período activo — mostrando: <strong><?= $mesNombres[$chartMes] ?> <?= $chartAnio ?></strong></span>
    <button onclick="exportarExcel()" class="btn btn-success btn-sm ms-3 text-nowrap">
        <i class="bi bi-file-earmark-excel-fill me-1"></i>Exportar Excel
    </button>
</div>
<?php endif; ?>

<!-- Cards resumen -->
<?php
$pctCobertura = $totalPacientesChart > 0
    ? round($conCoberturaCompleta / $totalPacientesChart * 100) : 0;
$sinNingun    = $totalPacientesChart - $conCoberturaTotal;
$pctFacturacion = $totalFacturacionMax > 0
    ? round($totalFacturacionEst / $totalFacturacionMax * 100) : 0;
?>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="rounded-3 p-2 bg-primary bg-opacity-10">
                    <i class="bi bi-people-fill fs-4 text-primary"></i>
                </div>
                <div>
                    <div class="fs-3 fw-bold lh-1"><?= $totalPacientesChart ?></div>
                    <div class="small text-muted">Pacientes</div>
                    <div class="d-flex gap-1 mt-1 flex-wrap">
                        <?php if ($totalP1 > 0): ?>
                        <span class="badge text-bg-info" style="font-size:.6rem">P1:<?= $totalP1 ?></span>
                        <?php endif; ?>
                        <?php if ($totalP2 > 0): ?>
                        <span class="badge" style="font-size:.6rem;background:#7c3aed;color:#fff">P2:<?= $totalP2 ?></span>
                        <?php endif; ?>
                        <?php if ($totalEventoPacientes > 0): ?>
                        <span class="badge text-bg-warning" style="font-size:.6rem">EV:<?= $totalEventoPacientes ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="rounded-3 p-2 bg-success bg-opacity-10">
                    <i class="bi bi-patch-check-fill fs-4 text-success"></i>
                </div>
                <div>
                    <div class="fs-3 fw-bold lh-1 text-success"><?= $conCoberturaCompleta ?></div>
                    <div class="small text-muted">≥<?= PCT_CUMPLIMIENTO ?>% completo</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="rounded-3 p-2 bg-warning bg-opacity-10">
                    <i class="bi bi-exclamation-circle-fill fs-4 text-warning"></i>
                </div>
                <div>
                    <div class="fs-3 fw-bold lh-1 text-warning"><?= $conCoberturaTotal - $conCoberturaCompleta ?></div>
                    <div class="small text-muted">Parcial</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="rounded-3 p-2 bg-danger bg-opacity-10">
                    <i class="bi bi-x-circle-fill fs-4 text-danger"></i>
                </div>
                <div>
                    <div class="fs-3 fw-bold lh-1 text-danger"><?= $sinNingun ?></div>
                    <div class="small text-muted">Sin soportes</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-success">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Facturación estimada del período</div>
                        <div class="fs-4 fw-bold text-success">
                            $<?= number_format($totalFacturacionEst, 0, ',', '.') ?>
                        </div>
                        <div class="text-muted" style="font-size:.75rem">
                            de $<?= number_format($totalFacturacionMax, 0, ',', '.') ?> máximo posible
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fs-3 fw-bold text-<?= $pctFacturacion >= PCT_CUMPLIMIENTO ? 'success' : ($pctFacturacion > 0 ? 'warning' : 'danger') ?>">
                            <?= $pctFacturacion ?>%
                        </div>
                        <?php if ($completosP1P2 > 0): ?>
                        <div class="badge text-bg-success">
                            <?= $completosP1P2 ?> P1/P2 completo<?= $completosP1P2 !== 1 ? 's' : '' ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($facturablesEvento > 0): ?>
                        <div class="badge text-bg-warning mt-1">
                            <?= $facturablesEvento ?> Evento facturable<?= $facturablesEvento !== 1 ? 's' : '' ?>
                        </div>
                        <?php elseif ($completosP1P2 === 0): ?>
                        <div class="badge text-bg-secondary">0 facturables</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Barra de progreso global -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="fw-semibold small">Cobertura global de soportes —
                <span class="text-muted"><?= $mesNombres[$chartMes] ?> <?= $chartAnio ?></span></span>
            <span class="badge text-bg-<?= $pctCobertura >= 80 ? 'success' : ($pctCobertura >= 50 ? 'warning' : 'danger') ?> fs-6">
                <?= $pctCobertura ?>%
            </span>
        </div>
        <div class="progress" style="height:14px;border-radius:8px">
            <div class="progress-bar bg-success" style="width:<?= $pctCobertura ?>%;border-radius:8px"
                 role="progressbar" aria-valuenow="<?= $pctCobertura ?>" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
        <div class="d-flex justify-content-between mt-1">
            <small class="text-muted"><?= $totalSoportesChart ?> soportes cargados · <?= $totalPacientesChart ?> pacientes registrados</small>
            <small class="text-muted">
                <?= $completosP1P2 ?>/<?= $totalP1 + $totalP2 ?> P1/P2 completos
                <?php if ($totalEventoPacientes > 0): ?>
                · <?= $facturablesEvento ?>/<?= $totalEventoPacientes ?> Evento facturables
                <?php endif; ?>
            </small>
        </div>
    </div>
</div>

<?php if (!empty($servicioStats) && $totalPacientesChart > 0): ?>
<!-- Gráfico por servicio -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold py-2 small">
        <i class="bi bi-bar-chart-fill me-1 text-primary"></i>Cobertura por tipo de servicio
    </div>
    <div class="card-body">
        <canvas id="chartServicios" height="<?= max(120, count(TIPOS_SERVICIO) * 45) ?>"></canvas>
    </div>
</div>
<?php endif; ?>

<!-- Matriz de cobertura -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
        <span class="fw-semibold small">
            <i class="bi bi-grid-3x3-gap-fill me-1 text-success"></i>Matriz de cobertura por paciente
        </span>
        <div class="d-flex gap-2 align-items-center">
            <span class="d-inline-flex align-items-center gap-1 small">
                <i class="bi bi-check-circle-fill text-success" style="font-size:.85rem"></i> Con soporte
            </span>
            <span class="d-inline-flex align-items-center gap-1 small">
                <i class="bi bi-x-circle-fill text-danger" style="font-size:.85rem"></i> Sin soporte
            </span>
            <span class="d-inline-flex align-items-center gap-1 small text-muted" style="font-size:.78rem">
                (REG = Regencia automática)
            </span>
        </div>
    </div>
    <div class="table-responsive" style="max-height:560px;overflow-y:auto">
        <table class="table table-bordered table-sm align-middle mb-0" style="font-size:.8rem;min-width:700px">
            <thead class="sticky-top bg-white">
                <tr>
                    <th class="text-muted fw-semibold" style="min-width:190px;position:sticky;left:0;background:#fff;z-index:2">
                        Paciente / CC
                    </th>
                    <?php foreach (TIPOS_SERVICIO as $idx => $codigo): ?>
                    <th class="text-center fw-semibold" style="min-width:52px" title="Peso: <?= PESOS_SERVICIO[$idx] ?? 0 ?>%">
                        <span class="badge text-bg-primary"><?= Security::e($codigo) ?></span>
                        <div style="font-size:.65rem;color:#94a3b8;font-weight:400"><?= PESOS_SERVICIO[$idx] ?? 0 ?>%</div>
                    </th>
                    <?php endforeach; ?>
                    <th class="text-center fw-semibold" style="min-width:60px">%</th>
                    <th class="text-center fw-semibold" style="min-width:100px">Facturación</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($matrizCobertura)): ?>
                <tr><td colspan="<?= count(TIPOS_SERVICIO)+3 ?>" class="text-center text-muted py-4">
                    No hay atenciones registradas para este período.
                </td></tr>
            <?php else: ?>
                <?php foreach ($matrizCobertura as $pid => $pac): ?>
                <?php
                    $pct      = $pac['pct_ponderado'];
                    $completo = $pac['completo'];
                    $esEvento = $pac['es_evento'] ?? ($pac['paquete'] === 3);
                    if ($esEvento) {
                        $rowClass = $pct > 0 ? 'table-info' : 'table-light';
                    } else {
                        $rowClass = $completo ? 'table-success' : ($pct > 0 ? 'table-warning' : '');
                    }
                    $precioBase = PRECIO_PAQUETE[$pac['paquete']] ?? PRECIO_PAQUETE[1];
                ?>
                <tr class="<?= $rowClass ?>">
                    <td style="position:sticky;left:0;background:inherit;z-index:1">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-medium text-truncate" style="max-width:160px" title="<?= Security::e($pac['nombre']) ?>">
                                    <?= Security::e($pac['nombre']) ?>
                                </div>
                                <div class="text-muted" style="font-size:.73rem"><?= Security::e($pac['documento']) ?></div>
                            </div>
                            <?php if ($pac['paquete'] === 1): ?>
                            <span class="badge text-bg-info ms-1" style="font-size:.65rem">P1</span>
                            <?php elseif ($pac['paquete'] === 2): ?>
                            <span class="badge ms-1" style="font-size:.65rem;background:#7c3aed;color:#fff">P2</span>
                            <?php else: ?>
                            <span class="badge text-bg-warning ms-1" style="font-size:.65rem">EV</span>
                            <?php endif; ?>
                        </div>
                        <div class="progress mt-1" style="height:4px;border-radius:2px">
                            <div class="progress-bar bg-<?= $pct>=PCT_CUMPLIMIENTO?'success':($pct>0?'warning':'danger') ?>"
                                 style="width:<?= $pct ?>%"></div>
                        </div>
                    </td>
                    <?php foreach (TIPOS_SERVICIO as $svcIdx => $svcCod): ?>
                    <td class="text-center">
                        <?php if (isset($pac['servicios'][$svcIdx]) && $pac['servicios'][$svcIdx] > 0): ?>
                            <i class="bi bi-check-circle-fill text-success" title="Con soporte"></i>
                        <?php else: ?>
                            <i class="bi bi-x-circle-fill text-danger" title="Sin soporte" style="opacity:.7"></i>
                        <?php endif; ?>
                    </td>
                    <?php endforeach; ?>
                    <td class="text-center fw-bold">
                        <span class="text-<?= $pct>=PCT_CUMPLIMIENTO?'success':($pct>0?'warning':'danger') ?>">
                            <?= $pct ?>%
                        </span>
                        <?php if ($esEvento && $pct > 0): ?>
                        <div style="font-size:.65rem" class="text-info">Prorrateo</div>
                        <?php elseif ($completo && !$esEvento): ?>
                        <div style="font-size:.65rem" class="text-success">✓ completo</div>
                        <?php endif; ?>
                    </td>
                    <td class="text-end fw-semibold">
                        <?php if ($esEvento): ?>
                        <div class="text-info">
                            $<?= number_format($pac['valor_facturacion'], 0, ',', '.') ?>
                        </div>
                        <div style="font-size:.68rem;color:#94a3b8">Prorrateo <?= $pct ?>%</div>
                        <?php else: ?>
                        <div class="text-<?= $completo?'success':'dark' ?>">
                            $<?= number_format($pac['valor_facturacion'], 0, ',', '.') ?>
                        </div>
                        <div style="font-size:.68rem;color:#94a3b8">
                            de $<?= number_format($precioBase, 0, ',', '.') ?>
                        </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <!-- Fila totales -->
                <tr class="table-light fw-bold" style="border-top:2px solid #dee2e6">
                    <td style="position:sticky;left:0;background:#f8fafc;z-index:1">
                        <div>TOTAL (<?= $totalPacientesChart ?> pacientes)</div>
                        <div style="font-size:.68rem;font-weight:normal" class="text-muted">
                            <?php if ($totalP1>0): ?><span class="badge text-bg-info" style="font-size:.6rem">P1:<?= $totalP1 ?></span> <?php endif; ?>
                            <?php if ($totalP2>0): ?><span class="badge" style="font-size:.6rem;background:#7c3aed;color:#fff">P2:<?= $totalP2 ?></span> <?php endif; ?>
                            <?php if ($totalEventoPacientes>0): ?><span class="badge text-bg-warning" style="font-size:.6rem">EV:<?= $totalEventoPacientes ?></span><?php endif; ?>
                        </div>
                    </td>
                    <?php foreach (TIPOS_SERVICIO as $i => $c): ?>
                    <td class="text-center small">
                        <span class="text-success"><?= $servicioStats[$i]['con'] ?></span>
                        <span class="text-danger opacity-75">/<?= $servicioStats[$i]['sin'] ?></span>
                    </td>
                    <?php endforeach; ?>
                    <td class="text-center small">
                        <?php if ($completosP1P2 > 0): ?>
                        <div class="text-success"><?= $completosP1P2 ?> compl.</div>
                        <?php endif; ?>
                        <?php if ($facturablesEvento > 0): ?>
                        <div class="text-info"><?= $facturablesEvento ?> EV fact.</div>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <div class="text-success">$<?= number_format($totalFacturacionEst, 0, ',', '.') ?></div>
                        <div style="font-size:.7rem;color:#94a3b8">de $<?= number_format($totalFacturacionMax, 0, ',', '.') ?></div>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($totalPacientesChart > 0): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function(){
    const labels = <?= json_encode(array_values(TIPOS_SERVICIO)) ?>;
    const conData  = <?= json_encode(array_column($servicioStats, 'con')) ?>;
    const sinData  = <?= json_encode(array_column($servicioStats, 'sin')) ?>;

    const ctx = document.getElementById('chartServicios').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Con soporte',
                    data: conData,
                    backgroundColor: 'rgba(34,197,94,0.82)',
                    borderColor:     'rgba(22,163,74,1)',
                    borderWidth: 1,
                    borderRadius: 4,
                },
                {
                    label: 'Sin soporte',
                    data: sinData,
                    backgroundColor: 'rgba(251,191,36,0.75)',
                    borderColor:     'rgba(202,138,4,1)',
                    borderWidth: 1,
                    borderRadius: 4,
                }
            ]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top', labels: { font: { size: 12 }, boxWidth: 14 } },
                tooltip: {
                    callbacks: {
                        label: ctx => {
                            const total = conData[ctx.dataIndex] + sinData[ctx.dataIndex];
                            const pct   = total > 0 ? Math.round(ctx.raw / total * 100) : 0;
                            return ` ${ctx.dataset.label}: ${ctx.raw} pacientes (${pct}%)`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    stacked: true,
                    ticks: { precision: 0 },
                    grid: { color: 'rgba(0,0,0,.05)' }
                },
                y: {
                    stacked: true,
                    ticks: { font: { size: 12 } },
                    grid: { display: false }
                }
            }
        }
    });
})();
</script>
<?php endif; ?>

<?php endif; // fin tab tablero ?>

<!-- ── Modal progreso exportación Excel ─────────────────────────────────── -->
<div id="modalExport" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,.55); align-items:center; justify-content:center;">
  <div class="bg-white rounded-3 shadow-lg p-4" style="min-width:320px; max-width:420px; width:90%;">
    <div class="d-flex align-items-center gap-3 mb-3">
      <div class="spinner-border text-success" role="status" style="width:2rem;height:2rem;"><span class="visually-hidden">Generando...</span></div>
      <div>
        <div class="fw-bold" style="font-size:1rem;">Generando informe Excel…</div>
        <div class="text-muted small" id="exportMsg">Calculando estadísticas del período</div>
      </div>
    </div>
    <div class="progress mb-3" style="height:10px;">
      <div id="exportBar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" style="width:10%"></div>
    </div>
    <div class="text-muted" style="font-size:.8rem;">El archivo se descargará automáticamente cuando esté listo.</div>
  </div>
</div>
<iframe id="exportFrame" style="display:none"></iframe>

<script>
function exportarExcel() {
    const token = 'xlsx_' + Date.now() + '_' + Math.random().toString(36).slice(2);
    const modal = document.getElementById('modalExport');
    const bar   = document.getElementById('exportBar');
    const msg   = document.getElementById('exportMsg');

    // Mostrar modal
    modal.style.display = 'flex';
    bar.style.width = '10%';
    msg.textContent = 'Calculando estadísticas del período…';

    // Animación de progreso falsa (puramente visual)
    const steps = [
        [800,  20, 'Consultando base de datos…'],
        [1800, 40, 'Construyendo matriz de cobertura…'],
        [3000, 60, 'Calculando facturación…'],
        [4500, 80, 'Generando hojas Excel…'],
    ];
    steps.forEach(([ms, pct, texto]) => {
        setTimeout(() => { bar.style.width = pct + '%'; msg.textContent = texto; }, ms);
    });

    // Iniciar la descarga en el iframe oculto
    document.getElementById('exportFrame').src = '/atenciones/exportar-excel?token=' + encodeURIComponent(token);

    // Eliminar cookie vieja si existe
    document.cookie = 'xlsx_ready=; max-age=0; path=/';

    // Polling: cuando el servidor pone la cookie xlsx_ready=<token>, cerramos el modal
    let tries = 0;
    const poll = setInterval(() => {
        tries++;
        const match = document.cookie.split(';').find(c => c.trim().startsWith('xlsx_ready='));
        if (match && match.includes(token)) {
            clearInterval(poll);
            bar.style.width = '100%';
            msg.textContent = '¡Descarga lista!';
            setTimeout(() => {
                modal.style.display = 'none';
                document.cookie = 'xlsx_ready=; max-age=0; path=/';
            }, 700);
        }
        // Seguridad: cerrar si pasan 60s sin respuesta
        if (tries > 120) {
            clearInterval(poll);
            modal.style.display = 'none';
        }
    }, 500);
}
</script>

<?php require BASE_PATH . '/app/Views/layout/footer.php'; ?>
