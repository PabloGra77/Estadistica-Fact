<?php
$pageTitle = 'Períodos — PPL';
$meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
require BASE_PATH . '/app/Views/layout/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h2 class="fw-bold mb-0"><i class="bi bi-calendar3 me-2 text-primary"></i>Períodos</h2>
</div>

<?php if (isset($_GET['ok'])): ?>
<div class="alert alert-success alert-dismissible fade show py-2 small" role="alert">
    <?= match((int)$_GET['ok']) {
        1 => '<i class="bi bi-check-circle me-1"></i>Período creado correctamente.',
        2 => '<i class="bi bi-check-circle me-1"></i>Período activado. Todo el sistema operará sobre este período.',
        3 => '<i class="bi bi-lock me-1"></i>Período cerrado. Ya no se pueden registrar atenciones en él.',
        default => 'Operación realizada.'
    } ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($_GET['err'])): ?>
<div class="alert alert-danger py-2 small">
    <?= match($_GET['err']) {
        'datos'     => 'Complete todos los campos correctamente.',
        'duplicado' => 'Ya existe un período para ese mes y año.',
        'noactiv'   => 'No se puede activar un período cerrado.',
        default     => 'Error desconocido.'
    } ?>
</div>
<?php endif; ?>

<div class="row g-4">
    <!-- Panel: crear nuevo período -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-semibold">
                <i class="bi bi-plus-circle me-1 text-primary"></i>Nuevo período
            </div>
            <div class="card-body">
                <form method="post" action="/admin/periodos/crear" novalidate>
                    <?= Security::csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" class="form-control form-control-sm"
                               placeholder="Ej: Mayo 2026" maxlength="100" required
                               id="nombrePeriodo"/>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col">
                            <label class="form-label small fw-semibold">Mes <span class="text-danger">*</span></label>
                            <select name="mes" class="form-select form-select-sm" id="mesSelect" required>
                                <option value="">— Mes —</option>
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?= $i ?>" <?= $i === (int)date('n') ? 'selected' : '' ?>>
                                    <?= $meses[$i] ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col">
                            <label class="form-label small fw-semibold">Año <span class="text-danger">*</span></label>
                            <input type="number" name="anio" class="form-control form-control-sm"
                                   value="<?= date('Y') ?>" min="2020" max="2099" required id="anioInput"/>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-plus-lg me-1"></i>Crear período
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Lista de períodos -->
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-semibold">
                <i class="bi bi-list-ul me-1"></i>Períodos registrados
            </div>
            <div class="card-body p-0">
                <?php if (empty($periodos)): ?>
                <div class="text-center text-muted py-5">
                    <i class="bi bi-calendar-x fs-1 d-block mb-2 opacity-50"></i>
                    No hay períodos registrados. Crea el primero.
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Período</th>
                                <th class="text-center">Atenciones</th>
                                <th class="text-center">Estado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($periodos as $p): ?>
                            <tr class="<?= $p['activo'] ? 'table-primary' : '' ?>">
                                <td>
                                    <div class="fw-semibold"><?= Security::e($p['nombre']) ?></div>
                                    <div class="text-muted x-small"><?= $meses[(int)$p['mes']] ?> <?= $p['anio'] ?>
                                        <?php if ($p['activo']): ?>
                                        <span class="badge bg-primary ms-1"><i class="bi bi-lightning-fill"></i> Activo</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge text-bg-secondary"><?= (int)$p['total_atenciones'] ?></span>
                                </td>
                                <td class="text-center">
                                    <?php if ($p['estado'] === 'abierto'): ?>
                                        <span class="badge bg-success"><i class="bi bi-unlock-fill me-1"></i>Abierto</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><i class="bi bi-lock-fill me-1"></i>Cerrado</span>
                                        <?php if ($p['fecha_cierre']): ?>
                                        <div class="text-muted x-small mt-1"><?= date('d/m/Y', strtotime($p['fecha_cierre'])) ?></div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($p['estado'] === 'abierto' && !$p['activo']): ?>
                                    <form method="post" action="/admin/periodos/<?= (int)$p['id'] ?>/activar"
                                          class="d-inline" onsubmit="return confirm('¿Activar el período <?= Security::e($p['nombre']) ?>? El período activo actual quedará en segundo plano.')">
                                        <?= Security::csrfField() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-lightning-fill"></i> Activar
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <?php if ($p['activo']): ?>
                                    <span class="badge bg-primary py-2 px-3">Período en curso</span>
                                    <?php endif; ?>
                                    <?php if ($p['estado'] === 'abierto'): ?>
                                    <form method="post" action="/admin/periodos/<?= (int)$p['id'] ?>/cerrar"
                                          class="d-inline ms-1" onsubmit="return confirm('¿Cerrar el período <?= Security::e($p['nombre']) ?>? No se podrán registrar más atenciones en este período.')">
                                        <?= Security::csrfField() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-lock-fill"></i> Cerrar
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-completar nombre al cambiar mes/año
const mesSelect  = document.getElementById('mesSelect');
const anioInput  = document.getElementById('anioInput');
const nombreInput = document.getElementById('nombrePeriodo');
const mesesNombre = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
function actualizarNombre() {
    if (nombreInput.value === '' || nombreInput.dataset.touched !== '1') {
        const m = parseInt(mesSelect.value) || 0;
        const a = anioInput.value || '';
        nombreInput.value = m ? mesesNombre[m] + ' ' + a : '';
    }
}
mesSelect.addEventListener('change', actualizarNombre);
anioInput.addEventListener('input', actualizarNombre);
nombreInput.addEventListener('input', () => { nombreInput.dataset.touched = '1'; });
actualizarNombre();
</script>

<?php require BASE_PATH . '/app/Views/layout/footer.php'; ?>
