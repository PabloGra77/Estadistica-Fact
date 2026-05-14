<?php
$meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$pageTitle = 'Configurar Pacientes — ' . Security::e($periodo['nombre']) . ' — PPL';
require BASE_PATH . '/app/Views/layout/header.php';
?>

<!-- Encabezado -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div class="d-flex align-items-center gap-2">
        <a href="/admin/periodos" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h2 class="fw-bold mb-0">
                <i class="bi bi-people me-2 text-primary"></i>Tabla de salida — Pacientes
            </h2>
            <div class="text-muted small mt-1">
                <i class="bi bi-calendar3 me-1"></i>
                Período: <strong><?= Security::e($periodo['nombre']) ?></strong>
                &mdash; <?= $meses[(int)$periodo['mes']] ?> <?= (int)$periodo['anio'] ?>
            </div>
        </div>
    </div>
    <a href="/admin/periodos?ok=1" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-skip-forward me-1"></i>Omitir y continuar
    </a>
</div>

<div class="alert alert-info py-2 small mb-3">
    <i class="bi bi-info-circle me-1"></i>
    Revisa y actualiza el <strong>paquete</strong> de cada paciente activo para este período.
    Los cambios se guardarán al hacer clic en <strong>Guardar cambios</strong>.
</div>

<form method="post" action="/admin/periodos/<?= (int)$periodo['id'] ?>/pacientes">
    <?= Security::csrfField() ?>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent d-flex align-items-center justify-content-between flex-wrap gap-2">
            <span class="fw-semibold">
                <i class="bi bi-table me-1"></i>
                Pacientes activos <span class="badge bg-secondary ms-1"><?= count($pacientes) ?></span>
            </span>
            <div class="d-flex gap-2 flex-wrap">
                <a href="/pacientes/importar" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-upload me-1"></i>Importar CSV
                </a>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnTodo1">
                    <i class="bi bi-1-circle me-1"></i>Todo P1
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnTodo2">
                    <i class="bi bi-2-circle me-1"></i>Todo P2
                </button>
                <button type="button" class="btn btn-outline-warning btn-sm" id="btnTodo3">
                    <i class="bi bi-star me-1"></i>Todo Evento
                </button>
            </div>
        </div>

        <?php if (empty($pacientes)): ?>
        <div class="card-body text-center py-5">
            <i class="bi bi-people fs-1 d-block mb-3 text-muted opacity-50"></i>
            <p class="text-muted mb-2 fw-semibold">No hay pacientes activos registrados.</p>
            <p class="small text-muted mb-4">Importa la base de pacientes desde un archivo CSV para continuar con la configuración del período.</p>
            <div class="d-flex justify-content-center gap-2 flex-wrap">
                <a href="/pacientes/importar" class="btn btn-primary btn-sm">
                    <i class="bi bi-upload me-1"></i>Importar pacientes (CSV)
                </a>
                <a href="/pacientes/importar/plantilla" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-download me-1"></i>Descargar plantilla CSV
                </a>
            </div>
            <p class="text-muted x-small mt-3">Después de importar, regresa a esta página para asignar paquetes.</p>
        </div>
        <?php else: ?>
        <!-- Buscador rápido -->
        <div class="card-body border-bottom pb-2 pt-3">
            <input type="search" id="filtroPaciente" class="form-control form-control-sm"
                   placeholder="Filtrar por nombre o documento…" autocomplete="off"/>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small" id="tablaPacientes">
                <thead class="table-light">
                    <tr>
                        <th style="width:130px">Documento</th>
                        <th>Apellidos y nombres</th>
                        <th style="width:90px">NUI</th>
                        <th style="width:150px" class="text-center">Paquete</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pacientes as $pac): ?>
                <tr data-search="<?= strtolower(Security::e($pac['documento'] . ' ' . $pac['nombre_completo'])) ?>">
                    <td class="fw-mono text-muted"><?= Security::e($pac['documento']) ?></td>
                    <td>
                        <div class="fw-semibold">
                            <?= Security::e(trim($pac['primer_apellido'] . ' ' . $pac['segundo_apellido'])) ?>
                        </div>
                        <div class="text-muted x-small">
                            <?= Security::e(trim($pac['primer_nombre'] . ' ' . $pac['segundo_nombre'])) ?>
                        </div>
                    </td>
                    <td class="text-muted"><?= Security::e($pac['nui'] ?? '') ?></td>
                    <td class="text-center">
                        <div class="btn-group btn-group-sm paquete-group" role="group">
                            <input type="radio" class="btn-check" autocomplete="off"
                                   name="paquete[<?= (int)$pac['id'] ?>]"
                                   id="p1_<?= (int)$pac['id'] ?>" value="1"
                                   <?= (int)$pac['paquete'] === 1 ? 'checked' : '' ?>>
                            <label class="btn btn-outline-primary" for="p1_<?= (int)$pac['id'] ?>">
                                P1
                            </label>

                            <input type="radio" class="btn-check" autocomplete="off"
                                   name="paquete[<?= (int)$pac['id'] ?>]"
                                   id="p2_<?= (int)$pac['id'] ?>" value="2"
                                   <?= (int)$pac['paquete'] === 2 ? 'checked' : '' ?>>
                            <label class="btn btn-outline-success" for="p2_<?= (int)$pac['id'] ?>">
                                P2
                            </label>

                            <input type="radio" class="btn-check" autocomplete="off"
                                   name="paquete[<?= (int)$pac['id'] ?>]"
                                   id="p3_<?= (int)$pac['id'] ?>" value="3"
                                   <?= (int)$pac['paquete'] === 3 ? 'checked' : '' ?>>
                            <label class="btn btn-outline-warning" for="p3_<?= (int)$pac['id'] ?>">
                                EV
                            </label>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div class="card-footer bg-transparent d-flex justify-content-between align-items-center">
            <span class="text-muted small">
                <span id="contadorVisible"><?= count($pacientes) ?></span> paciente(s) mostrado(s)
            </span>
            <div class="d-flex gap-2">
                <a href="/admin/periodos?ok=1" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-skip-forward me-1"></i>Omitir
                </a>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-check-lg me-1"></i>Guardar cambios y continuar
                </button>
            </div>
        </div>
    </div>
</form>

<script>
// ── Filtro rápido ──────────────────────────────────────────────────────────────
const filtro = document.getElementById('filtroPaciente');
const filas  = document.querySelectorAll('#tablaPacientes tbody tr');
const counter = document.getElementById('contadorVisible');

if (filtro) {
    filtro.addEventListener('input', () => {
        const q = filtro.value.toLowerCase();
        let visible = 0;
        filas.forEach(fila => {
            const match = fila.dataset.search.includes(q);
            fila.style.display = match ? '' : 'none';
            if (match) visible++;
        });
        if (counter) counter.textContent = visible;
    });
}

// ── Botones "Todo Paquete X" ───────────────────────────────────────────────────
document.getElementById('btnTodo1')?.addEventListener('click', () => {
    document.querySelectorAll('input[type=radio][value="1"]').forEach(r => { r.checked = true; });
});
document.getElementById('btnTodo2')?.addEventListener('click', () => {
    document.querySelectorAll('input[type=radio][value="2"]').forEach(r => { r.checked = true; });
});
document.getElementById('btnTodo3')?.addEventListener('click', () => {
    document.querySelectorAll('input[type=radio][value="3"]').forEach(r => { r.checked = true; });
});
</script>

<?php require BASE_PATH . '/app/Views/layout/footer.php'; ?>
