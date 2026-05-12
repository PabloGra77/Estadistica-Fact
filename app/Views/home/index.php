<?php $pageTitle = 'Inicio — Tablero PPL';
require BASE_PATH . '/app/Views/layout/header.php';

$meses = ['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
$serviciosNombres = TIPOS_SERVICIO;
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h2 class="fw-bold mb-0">Tablero de Atenciones</h2>
        <p class="text-muted small mb-0">IPS Goleman Servicio Integral S.A.S. · <?= date('d/m/Y') ?></p>
    </div>
    <div class="text-end">
        <span class="badge rounded-pill" style="background:#0d2660;font-size:.8rem;">
            Hola, <?= Security::e(Auth::username()) ?>
        </span>
    </div>
</div>

<!-- Tarjetas resumen -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon" style="background:rgba(13,38,96,.1);color:#0d2660">
                    <i class="bi bi-people-fill fs-4"></i>
                </div>
                <div>
                    <div class="stat-num"><?= number_format((int)$totalPacientes) ?></div>
                    <div class="stat-label">Pacientes activos</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon" style="background:rgba(52,211,153,.12);color:#059669">
                    <i class="bi bi-calendar2-check-fill fs-4"></i>
                </div>
                <div>
                    <div class="stat-num"><?= number_format((int)$totalAtenciones) ?></div>
                    <div class="stat-label">Atenciones totales</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon" style="background:rgba(251,191,36,.12);color:#d97706">
                    <i class="bi bi-hourglass-split fs-4"></i>
                </div>
                <div>
                    <div class="stat-num"><?= number_format((int)$soportesPendientes) ?></div>
                    <div class="stat-label">Soportes pendientes</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon" style="background:rgba(16,185,129,.12);color:#10b981">
                    <i class="bi bi-check-circle-fill fs-4"></i>
                </div>
                <div>
                    <div class="stat-num"><?= number_format((int)$soportesAprobados) ?></div>
                    <div class="stat-label">Soportes aprobados</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Últimas atenciones del mes -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom fw-semibold py-3">
        <i class="bi bi-clock-history me-2 text-primary"></i>
        Atenciones del mes en curso —
        <?= $meses[(int)date('m')] ?> <?= date('Y') ?>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Paciente</th>
                    <th>Documento</th>
                    <th>Servicio</th>
                    <th>Período</th>
                    <th>Cargado por</th>
                    <th>Fecha</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($ultimasAtenciones)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">Sin atenciones este mes.</td></tr>
            <?php else: ?>
                <?php foreach ($ultimasAtenciones as $a): ?>
                <tr>
                    <td class="fw-medium"><?= Security::e($a['nombre']) ?></td>
                    <td class="text-muted"><?= Security::e($a['documento']) ?></td>
                    <td><span class="badge text-bg-primary"><?= Security::e($serviciosNombres[$a['servicio']] ?? $a['servicio']) ?></span></td>
                    <td><?= Security::e($meses[(int)$a['mes_atencion']]) ?>/<?= Security::e($a['anio_atencion']) ?></td>
                    <td><?= Security::e($a['cargado_por']) ?></td>
                    <td><?= Security::e(date('d/m H:i', strtotime($a['fecha_carga']))) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white text-end py-2">
        <a href="/atenciones" class="btn btn-sm btn-outline-primary">Ver todas las atenciones</a>
    </div>
</div>

<?php require BASE_PATH . '/app/Views/layout/footer.php'; ?>
