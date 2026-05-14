<?php
$modoEditar ??= false;
$values ??= ['documento' => '', 'primer_nombre' => '', 'segundo_nombre' => '', 'primer_apellido' => '', 'segundo_apellido' => '', 'paquete' => 1, 'nui' => '', 'fecha_nacimiento' => '', 'activo' => 1];
$errors     ??= [];
// Calcular edad si hay fecha de nacimiento
$edadTexto = '';
if (!empty($values['fecha_nacimiento'])) {
    $fnac = new DateTime($values['fecha_nacimiento']);
    $hoy  = new DateTime();
    $edadTexto = $fnac->diff($hoy)->y . ' años';
}
$pageTitle = ($modoEditar ? 'Editar' : 'Nuevo') . ' Paciente — PPL';
require BASE_PATH . '/app/Views/layout/header.php';
?>

<div class="d-flex align-items-center mb-4 gap-2">
    <a href="/pacientes" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
    <h2 class="fw-bold mb-0"><?= $modoEditar ? 'Editar paciente' : 'Nuevo paciente' ?></h2>
</div>

<div class="card border-0 shadow-sm" style="max-width:560px">
    <div class="card-body p-4">
        <?php foreach ($errors as $err): ?>
        <div class="alert alert-danger alert-sm py-2"><?= Security::e($err) ?></div>
        <?php endforeach; ?>

        <form method="post" action="<?= $modoEditar ? '/pacientes/' . (int)$values['id'] . '/editar' : '/pacientes/crear' ?>" novalidate>
            <?= Security::csrfField() ?>

            <div class="mb-3">
                <label for="documento" class="form-label fw-semibold small">Documento <span class="text-danger">*</span></label>
                <input type="text" id="documento" name="documento" class="form-control form-control-sm"
                       value="<?= Security::e($values['documento']) ?>"
                       maxlength="20" pattern="[0-9A-Za-z\-]{3,20}" required
                       <?= $modoEditar ? '' : '' ?> />
                <div class="form-text">Solo letras, números y guiones. Máx. 20 caracteres.</div>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label for="primer_nombre" class="form-label fw-semibold small">Primer nombre <span class="text-danger">*</span></label>
                    <input type="text" id="primer_nombre" name="primer_nombre" class="form-control form-control-sm"
                           value="<?= Security::e($values['primer_nombre']) ?>"
                           maxlength="80" required />
                </div>
                <div class="col-6">
                    <label for="segundo_nombre" class="form-label fw-semibold small">Segundo nombre <span class="text-muted fw-normal">(opcional)</span></label>
                    <input type="text" id="segundo_nombre" name="segundo_nombre" class="form-control form-control-sm"
                           value="<?= Security::e($values['segundo_nombre'] ?? '') ?>"
                           maxlength="80" />
                </div>
                <div class="col-6">
                    <label for="primer_apellido" class="form-label fw-semibold small">Primer apellido <span class="text-danger">*</span></label>
                    <input type="text" id="primer_apellido" name="primer_apellido" class="form-control form-control-sm"
                           value="<?= Security::e($values['primer_apellido']) ?>"
                           maxlength="80" required />
                </div>
                <div class="col-6">
                    <label for="segundo_apellido" class="form-label fw-semibold small">Segundo apellido <span class="text-muted fw-normal">(opcional)</span></label>
                    <input type="text" id="segundo_apellido" name="segundo_apellido" class="form-control form-control-sm"
                           value="<?= Security::e($values['segundo_apellido'] ?? '') ?>"
                           maxlength="80" />
                </div>
            </div>

            <div class="mb-3">
                <label for="paquete" class="form-label fw-semibold small">Paquete <span class="text-danger">*</span></label>
                <select id="paquete" name="paquete" class="form-select form-select-sm" required>
                    <option value="1" <?= (int)$values['paquete'] === 1 ? 'selected' : '' ?>>Paquete 1</option>
                    <option value="2" <?= (int)$values['paquete'] === 2 ? 'selected' : '' ?>>Paquete 2</option>
                    <option value="3" <?= (int)$values['paquete'] === 3 ? 'selected' : '' ?>>Evento <small class="text-muted">(misma estadística que P1)</small></option>
                </select>
            </div>

            <div class="mb-3">
                <label for="nui" class="form-label fw-semibold small">NUI <span class="text-muted fw-normal">(opcional)</span></label>
                <input type="text" id="nui" name="nui" class="form-control form-control-sm"
                       value="<?= Security::e($values['nui'] ?? '') ?>"
                       maxlength="30" placeholder="Número único interno" />
            </div>

            <div class="mb-3">
                <label for="fecha_nacimiento" class="form-label fw-semibold small">Fecha de nacimiento <span class="text-muted fw-normal">(opcional)</span></label>
                <div class="input-group input-group-sm">
                    <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" class="form-control form-control-sm"
                           value="<?= Security::e($values['fecha_nacimiento'] ?? '') ?>"
                           max="<?= date('Y-m-d') ?>" />
                    <?php if ($edadTexto): ?>
                    <span class="input-group-text text-muted"><?= Security::e($edadTexto) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($modoEditar): ?>
            <div class="mb-3 form-check">
                <input type="checkbox" id="activo" name="activo" class="form-check-input" value="1"
                       <?= $values['activo'] ? 'checked' : '' ?> />
                <label for="activo" class="form-check-label small">Paciente activo</label>
            </div>
            <?php endif; ?>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary btn-sm px-4">
                    <i class="bi bi-check-lg me-1"></i><?= $modoEditar ? 'Guardar cambios' : 'Crear paciente' ?>
                </button>
                <a href="/pacientes" class="btn btn-outline-secondary btn-sm">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php require BASE_PATH . '/app/Views/layout/footer.php'; ?>
