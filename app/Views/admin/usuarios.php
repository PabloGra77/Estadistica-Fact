<?php $pageTitle = 'Usuarios — Admin PPL';
require BASE_PATH . '/app/Views/layout/header.php';
$rolesNombres = [
    ROL_ADMINISTRADOR => 'Administrador',
    ROL_FACTURADOR    => 'Facturador',
    ROL_EQUIPO_PPL    => 'EquipoPPL',
    ROL_ESTADISTICO   => 'Estadístico',
];
$rolesColores = [
    ROL_ADMINISTRADOR => 'primary',
    ROL_FACTURADOR    => 'success',
    ROL_EQUIPO_PPL    => 'warning',
    ROL_ESTADISTICO   => 'purple',
];
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h2 class="fw-bold mb-0"><i class="bi bi-shield-lock-fill me-2 text-danger"></i>Usuarios</h2>
    <a href="/admin/usuarios/crear" class="btn btn-sm btn-primary">
        <i class="bi bi-person-plus-fill me-1"></i>Nuevo usuario
    </a>
</div>

<?php if (!empty($_GET['ok'])): ?>
<div class="alert alert-success alert-dismissible fade show py-2">
    <?php
    $m = (int)$_GET['ok'];
    echo match($m) {
        1 => 'Usuario creado.',
        2 => 'Usuario actualizado.',
        3 => 'Estado cambiado.',
        default => 'Operación completada.'
    };
    ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle small mb-0">
            <thead class="table-light">
                <tr>
                    <th>Usuario</th>
                    <th>Email</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th>Intentos</th>
                    <th>Creado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($usuarios)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Sin usuarios.</td></tr>
            <?php else: ?>
                <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td class="fw-medium"><?= Security::e($u['nombre_usuario']) ?></td>
                    <td class="text-muted"><?= Security::e($u['nombre_usuario'] . EMAIL_DOMAIN) ?></td>
                    <td>
                        <?php $col = $rolesColores[(int)$u['rol']] ?? 'secondary'; ?>
                        <span class="badge text-bg-<?= $col !== 'purple' ? $col : 'secondary' ?>"
                              <?= $col === 'purple' ? 'style="background:#7c3aed!important"' : '' ?>>
                            <?= Security::e($rolesNombres[(int)$u['rol']] ?? '') ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($u['activo']): ?>
                            <span class="badge text-bg-success">Activo</span>
                        <?php else: ?>
                            <span class="badge text-bg-danger">Inactivo</span>
                        <?php endif; ?>
                        <?php if ($u['bloqueo_hasta'] && strtotime($u['bloqueo_hasta']) > time()): ?>
                            <span class="badge text-bg-warning text-dark ms-1">Bloqueado</span>
                        <?php endif; ?>
                    </td>
                    <td><?= Security::e($u['intentos_fallidos']) ?></td>
                    <td><?= Security::e(date('d/m/Y', strtotime($u['fecha_creacion']))) ?></td>
                    <td class="text-end">
                        <a href="/admin/usuarios/<?= (int)$u['id'] ?>/editar" class="btn btn-xs btn-outline-secondary">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <?php if ((int)$u['id'] !== Auth::userId()): ?>
                        <form method="post" action="/admin/usuarios/<?= (int)$u['id'] ?>/toggle"
                              class="d-inline" onsubmit="return confirm('¿Cambiar estado de este usuario?')">
                            <?= Security::csrfField() ?>
                            <button type="submit" class="btn btn-xs <?= $u['activo'] ? 'btn-outline-danger' : 'btn-outline-success' ?> ms-1">
                                <i class="bi bi-<?= $u['activo'] ? 'person-dash' : 'person-check' ?>"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require BASE_PATH . '/app/Views/layout/footer.php'; ?>
