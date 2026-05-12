<?php $pageTitle = 'Atenciones — PPL';
require BASE_PATH . '/app/Views/layout/header.php';
$meses = ['','Enero','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h2 class="fw-bold mb-0"><i class="bi bi-calendar2-check-fill me-2 text-success"></i>Atenciones</h2>
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

<!-- Filtros -->
<form method="get" action="/atenciones" class="mb-3 row g-2 align-items-end" style="max-width:700px">
    <div class="col">
        <input type="search" name="q" class="form-control form-control-sm"
               placeholder="Buscar paciente..." value="<?= Security::e($busqueda) ?>" maxlength="100"/>
    </div>
    <div class="col-auto">
        <select name="mes" class="form-select form-select-sm">
            <option value="">Mes</option>
            <?php for ($i=1;$i<=12;$i++): ?>
            <option value="<?=$i?>" <?= $filtroMes===$i ? 'selected':'' ?>><?= $meses[$i] ?></option>
            <?php endfor; ?>
        </select>
    </div>
    <div class="col-auto">
        <input type="number" name="anio" class="form-control form-control-sm" placeholder="Año"
               value="<?= Security::e($filtroAnio ?? '') ?>" min="2020" max="2099" style="width:80px"/>
    </div>
    <div class="col-auto">
        <button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-search"></i></button>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle small mb-0">
            <thead class="table-light">
                <tr>
                    <th>Paciente</th>
                    <th>Documento</th>
                    <th>Servicio</th>
                    <th>Período</th>
                    <th>Soportes</th>
                    <th>Cargado por</th>
                    <th>Fecha</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($atenciones)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">Sin resultados.</td></tr>
            <?php else: ?>
                <?php foreach ($atenciones as $a): ?>
                <tr>
                    <td class="fw-medium"><?= Security::e($a['paciente_nombre']) ?></td>
                    <td class="text-muted small"><?= Security::e($a['documento']) ?></td>
                    <td><span class="badge text-bg-primary"><?= Security::e(TIPOS_SERVICIO[$a['servicio']] ?? '') ?></span></td>
                    <td><?= Security::e($meses[(int)$a['mes_atencion']]) ?>/<?= Security::e($a['anio_atencion']) ?></td>
                    <td><span class="badge text-bg-<?= (int)$a['num_soportes'] > 0 ? 'success' : 'secondary' ?>">
                        <?= Security::e($a['num_soportes']) ?></span></td>
                    <td><?= Security::e($a['cargado_por']) ?></td>
                    <td><?= Security::e(date('d/m/Y', strtotime($a['fecha_carga']))) ?></td>
                    <td>
                        <a href="/soportes/subir?atencion_id=<?= (int)$a['id'] ?>"
                           class="btn btn-xs btn-outline-primary" title="Subir soporte">
                            <i class="bi bi-upload"></i>
                        </a>
                    </td>
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
        <a class="page-link" href="/atenciones?q=<?=urlencode($busqueda)?>&mes=<?=($filtroMes??'')?>&anio=<?=($filtroAnio??'')?>&p=<?=$i?>"><?=$i?></a>
    </li>
    <?php endfor; ?>
</ul></nav>
<?php endif; ?>

<?php require BASE_PATH . '/app/Views/layout/footer.php'; ?>
