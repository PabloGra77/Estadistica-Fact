<?php
$values ??= [];
$errors ??= [];
$pageTitle = 'Nueva Atención — PPL';
require BASE_PATH . '/app/Views/layout/header.php';
$meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$atencionPresel = Security::validateInt($_GET['atencion_id'] ?? '', 1) ?? null;
?>

<div class="d-flex align-items-center mb-4 gap-2">
    <a href="/atenciones" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
    <h2 class="fw-bold mb-0">Nueva atención</h2>
</div>

<div class="card border-0 shadow-sm" style="max-width:560px">
    <div class="card-body p-4">
        <?php foreach ($errors as $err): ?>
        <div class="alert alert-danger py-2 small"><?= Security::e($err) ?></div>
        <?php endforeach; ?>

        <form method="post" action="/atenciones/crear" novalidate>
            <?= Security::csrfField() ?>

            <div class="mb-3">
                <label for="paciente_id" class="form-label fw-semibold small">Paciente <span class="text-danger">*</span></label>
                <select id="paciente_id" name="paciente_id" class="form-select form-select-sm" required>
                    <option value="">— Seleccione —</option>
                    <?php foreach ($pacientes as $p): ?>
                    <option value="<?= (int)$p['id'] ?>"
                        <?= (int)($values['paciente_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>>
                        <?= Security::e($p['documento'] . ' — ' . $p['nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="servicio" class="form-label fw-semibold small">Servicio <span class="text-danger">*</span></label>
                <select id="servicio" name="servicio" class="form-select form-select-sm" required>
                    <option value="">— Seleccione —</option>
                    <?php foreach (TIPOS_SERVICIO as $idx => $svc): ?>
                    <option value="<?= $idx ?>" <?= (int)($values['servicio'] ?? -1) === $idx ? 'selected' : '' ?>>
                        <?= Security::e($svc) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="row g-2 mb-3">
                <div class="col">
                    <label for="mes_atencion" class="form-label fw-semibold small">Mes <span class="text-danger">*</span></label>
                    <select id="mes_atencion" name="mes_atencion" class="form-select form-select-sm" required>
                        <?php for ($i=1;$i<=12;$i++): ?>
                        <option value="<?=$i?>" <?= (int)($values['mes_atencion'] ?? date('n')) === $i ? 'selected':'' ?>>
                            <?= $meses[$i] ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col">
                    <label for="anio_atencion" class="form-label fw-semibold small">Año <span class="text-danger">*</span></label>
                    <input type="number" id="anio_atencion" name="anio_atencion" class="form-control form-control-sm"
                           value="<?= Security::e($values['anio_atencion'] ?? date('Y')) ?>"
                           min="2020" max="2099" required/>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-success btn-sm px-4">
                    <i class="bi bi-check-lg me-1"></i>Registrar atención
                </button>
                <a href="/atenciones" class="btn btn-outline-secondary btn-sm">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php require BASE_PATH . '/app/Views/layout/footer.php'; ?>
