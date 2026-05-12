<?php $pageTitle = 'Soportes — PPL';
require BASE_PATH . '/app/Views/layout/header.php';
$estadoColores = [0=>'warning',1=>'info',2=>'success',3=>'danger',4=>'secondary'];
$meses = ['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h2 class="fw-bold mb-0"><i class="bi bi-file-earmark-text-fill me-2" style="color:#7c3aed"></i>Soportes</h2>
    <?php if (!Auth::isEstadistico()): ?>
    <div class="d-flex gap-2">
        <a href="/soportes/importar-zip" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-file-zip me-1"></i>Importar ZIP
        </a>
        <a href="/soportes/subir" class="btn btn-sm btn-primary">
            <i class="bi bi-upload me-1"></i>Subir soporte
        </a>
    </div>
    <?php endif; ?>
</div>

<?php if (!empty($_GET['ok'])): ?>
<div class="alert alert-success alert-dismissible fade show py-2">
    <?= (int)$_GET['ok'] === 1 ? 'Soporte cargado correctamente.' : 'Soporte auditado.' ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Filtros -->
<form method="get" action="/soportes" class="mb-3 row g-2 align-items-end" style="max-width:600px">
    <div class="col">
        <input type="search" name="q" class="form-control form-control-sm"
               placeholder="Buscar paciente o archivo..." value="<?= Security::e($busqueda) ?>" maxlength="100"/>
    </div>
    <div class="col-auto">
        <select name="estado" class="form-select form-select-sm">
            <option value="">Estado</option>
            <?php foreach (ESTADOS_SOPORTE as $k => $v): ?>
            <option value="<?= $k ?>" <?= $estado===$k?'selected':'' ?>><?= Security::e($v) ?></option>
            <?php endforeach; ?>
        </select>
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
                    <th>Archivo</th>
                    <th>Paciente</th>
                    <th>Servicio/Período</th>
                    <th>Estado</th>
                    <th>Cargado por</th>
                    <th>Fecha</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($soportes)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Sin resultados.</td></tr>
            <?php else: ?>
                <?php foreach ($soportes as $s): ?>
                <tr>
                    <td class="fw-medium text-truncate" style="max-width:180px" title="<?= Security::e($s['nombre_original']) ?>">
                        <i class="bi bi-file-earmark-pdf text-danger me-1"></i>
                        <?= Security::e($s['nombre_original']) ?>
                    </td>
                    <td><?= Security::e($s['paciente_nombre']) ?><br>
                        <span class="text-muted"><?= Security::e($s['documento']) ?></span></td>
                    <td>
                        <span class="badge text-bg-primary"><?= Security::e(TIPOS_SERVICIO[$s['servicio']] ?? '') ?></span>
                        <br><small><?= Security::e($meses[(int)$s['mes_atencion']]) ?>/<?= Security::e($s['anio_atencion']) ?></small>
                    </td>
                    <td>
                        <span class="badge text-bg-<?= $estadoColores[$s['estado']] ?? 'secondary' ?>">
                            <?= Security::e(ESTADOS_SOPORTE[$s['estado']] ?? '') ?>
                        </span>
                    </td>
                    <td><?= Security::e($s['cargado_por']) ?></td>
                    <td><?= Security::e(date('d/m/Y', strtotime($s['fecha_carga']))) ?></td>
                    <td class="text-end">
                        <a href="/soportes/<?= (int)$s['id'] ?>/descargar" class="btn btn-xs btn-outline-secondary" title="Descargar">
                            <i class="bi bi-download"></i>
                        </a>
                        <?php if ((Auth::isAdmin() || Auth::isFacturador()) && (int)$s['estado'] === 0): ?>
                        <button class="btn btn-xs btn-outline-success ms-1" data-bs-toggle="modal"
                                data-bs-target="#modalAuditar" data-id="<?= (int)$s['id'] ?>"
                                data-nombre="<?= Security::e($s['nombre_original']) ?>">
                            <i class="bi bi-check2-square"></i>
                        </button>
                        <?php endif; ?>
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
        <a class="page-link" href="/soportes?q=<?=urlencode($busqueda)?>&estado=<?=($estado??'')?>&p=<?=$i?>"><?=$i?></a>
    </li>
    <?php endfor; ?>
</ul></nav>
<?php endif; ?>

<!-- Modal auditar -->
<div class="modal fade" id="modalAuditar" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title">Auditar soporte</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formAuditar" method="post" action="" novalidate>
                <?= Security::csrfField() ?>
                <div class="modal-body">
                    <p class="small mb-2" id="modalNombreArchivo"></p>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Resolución</label>
                        <select name="estado" class="form-select form-select-sm" required>
                            <option value="2">✅ Aprobar manual</option>
                            <option value="3">❌ Rechazar</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label small fw-semibold">Observación</label>
                        <textarea name="observacion" class="form-control form-control-sm" rows="3" maxlength="500"></textarea>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="submit" class="btn btn-sm btn-primary">Guardar</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('modalAuditar').addEventListener('show.bs.modal', function (e) {
    const btn = e.relatedTarget;
    document.getElementById('formAuditar').action = '/soportes/' + btn.dataset.id + '/auditar';
    document.getElementById('modalNombreArchivo').textContent = btn.dataset.nombre;
});
</script>

<?php require BASE_PATH . '/app/Views/layout/footer.php'; ?>
