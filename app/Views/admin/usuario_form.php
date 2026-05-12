<?php
$values   ??= ['nombre_usuario' => '', 'rol' => ROL_FACTURADOR, 'activo' => 1];
$errors   ??= [];
$modoEditar ??= false;
$pageTitle = ($modoEditar ? 'Editar' : 'Nuevo') . ' Usuario — PPL';
require BASE_PATH . '/app/Views/layout/header.php';
$rolesNombres = [
    ROL_ADMINISTRADOR => 'Administrador',
    ROL_FACTURADOR    => 'Facturador',
    ROL_EQUIPO_PPL    => 'EquipoPPL',
    ROL_ESTADISTICO   => 'Estadístico',
];
?>

<div class="d-flex align-items-center mb-4 gap-2">
    <a href="/admin/usuarios" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
    <h2 class="fw-bold mb-0"><?= $modoEditar ? 'Editar usuario' : 'Nuevo usuario' ?></h2>
</div>

<div class="card border-0 shadow-sm" style="max-width:480px">
    <div class="card-body p-4">
        <?php foreach ($errors as $err): ?>
        <div class="alert alert-danger py-2 small"><?= Security::e($err) ?></div>
        <?php endforeach; ?>

        <form method="post"
              action="<?= $modoEditar ? '/admin/usuarios/' . (int)$values['id'] . '/editar' : '/admin/usuarios/crear' ?>"
              novalidate autocomplete="off">
            <?= Security::csrfField() ?>

            <div class="mb-3">
                <label for="nombre_usuario" class="form-label fw-semibold small">
                    Usuario <span class="text-danger">*</span>
                </label>
                <div class="input-group input-group-sm">
                    <input type="text" id="nombre_usuario" name="nombre_usuario" class="form-control"
                           value="<?= Security::e($values['nombre_usuario']) ?>"
                           maxlength="50" pattern="[a-zA-Z0-9._\-]{3,50}" required autocomplete="off"/>
                    <span class="input-group-text text-muted"><?= Security::e(EMAIL_DOMAIN) ?></span>
                </div>
                <div class="form-text">Solo letras, números, punto, guion, guion bajo.</div>
            </div>

            <div class="mb-3">
                <label for="rol" class="form-label fw-semibold small">Rol <span class="text-danger">*</span></label>
                <select id="rol" name="rol" class="form-select form-select-sm" required>
                    <?php foreach ($rolesNombres as $k => $v): ?>
                    <option value="<?= $k ?>" <?= (int)$values['rol'] === $k ? 'selected' : '' ?>>
                        <?= Security::e($v) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label fw-semibold small">
                    Contraseña <?= $modoEditar ? '<span class="text-muted">(dejar vacío para no cambiar)</span>' : '<span class="text-danger">*</span>' ?>
                </label>
                <input type="password" id="password" name="password" class="form-control form-control-sm"
                       autocomplete="new-password" minlength="8" maxlength="128"
                       <?= $modoEditar ? '' : 'required' ?>/>
                <div class="form-text">Mínimo 8 caracteres, incluir mayúscula, número y símbolo.</div>
            </div>

            <div class="mb-3">
                <label for="password_confirm" class="form-label fw-semibold small">Confirmar contraseña</label>
                <input type="password" id="password_confirm" name="password_confirm" class="form-control form-control-sm"
                       autocomplete="new-password" maxlength="128"/>
            </div>

            <?php if ($modoEditar): ?>
            <div class="mb-3 form-check">
                <input type="checkbox" id="activo" name="activo" class="form-check-input" value="1"
                       <?= $values['activo'] ? 'checked' : '' ?>
                       <?= (int)$values['id'] === Auth::userId() ? 'disabled' : '' ?>/>
                <label for="activo" class="form-check-label small">Usuario activo</label>
            </div>
            <?php endif; ?>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary btn-sm px-4">
                    <i class="bi bi-check-lg me-1"></i><?= $modoEditar ? 'Guardar cambios' : 'Crear usuario' ?>
                </button>
                <a href="/admin/usuarios" class="btn btn-outline-secondary btn-sm">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php require BASE_PATH . '/app/Views/layout/footer.php'; ?>
