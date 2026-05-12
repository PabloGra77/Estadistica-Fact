<?php $pageTitle = 'Pacientes — PPL';
require BASE_PATH . '/app/Views/layout/header.php';
?>

<div class="d-flex align-items-start align-items-sm-center justify-content-between mb-4 flex-column flex-sm-row gap-2">
    <h2 class="fw-bold mb-0"><i class="bi bi-people-fill me-2 text-primary"></i>Pacientes</h2>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/pacientes/exportar" class="btn btn-outline-success btn-sm">
            <i class="bi bi-file-earmark-arrow-down me-1"></i>Descargar informe
        </a>
        <?php if (Auth::isAdmin() || Auth::isFacturador() || Auth::isEquipoPPL()): ?>
        <a href="/pacientes/importar" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-upload me-1"></i>Importar
        </a>
        <a href="/pacientes/crear" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Nuevo paciente
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($_GET['ok'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?= Security::e((int)$_GET['ok'] === 1 ? 'Paciente creado correctamente.' : 'Paciente actualizado.') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Buscador -->
<form method="get" action="/pacientes" class="mb-3">
    <div class="input-group input-group-sm">
        <input type="search" name="q" class="form-control" placeholder="Buscar por nombre o documento..."
               value="<?= Security::e($busqueda) ?>" maxlength="100"/>
        <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
    </div>
</form>

<div class="card border-0 shadow-sm">
        <table class="table table-hover align-middle small mb-0" style="table-layout:fixed;width:100%">
            <thead class="table-light">
                <tr>
                    <th style="width:55px">#</th>
                    <th style="width:130px">Documento</th>
                    <th>Nombre</th>
                    <th style="width:80px">Paquete</th>
                    <th style="width:85px">Atenciones</th>
                    <th style="width:44px"></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($pacientes)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">Sin resultados.</td></tr>
            <?php else: ?>
                <?php foreach ($pacientes as $p): ?>
                <tr>
                    <td class="text-muted"><?= Security::e($p['id']) ?></td>
                    <td class="fw-medium" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= Security::e($p['documento']) ?></td>
                    <td style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= Security::e($p['nombre']) ?></td>
                    <td><span class="badge text-bg-secondary">Paq. <?= Security::e($p['paquete']) ?></span></td>
                    <td><span class="badge text-bg-info text-dark"><?= Security::e($p['num_atenciones']) ?></span></td>
                    <td>
                        <?php if (Auth::isAdmin() || Auth::isFacturador()): ?>
                        <a href="/pacientes/<?= (int)$p['id'] ?>/editar" class="btn btn-xs btn-outline-secondary">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
</div>

<!-- Paginación -->
<?php if ($totalPaginas > 1): ?>
<nav class="mt-3">
    <ul class="pagination pagination-sm justify-content-center">
        <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
        <li class="page-item <?= $i === $pagina ? 'active' : '' ?>">
            <a class="page-link" href="/pacientes?q=<?= urlencode($busqueda) ?>&p=<?= $i ?>"><?= $i ?></a>
        </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<?php require BASE_PATH . '/app/Views/layout/footer.php'; ?>
