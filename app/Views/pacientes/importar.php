<?php
$pageTitle = 'Importar Pacientes — PPL';
require BASE_PATH . '/app/Views/layout/header.php';
$importResult ??= null;
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h2 class="fw-bold mb-0">
        <i class="bi bi-upload me-2 text-primary"></i>Importar pacientes
    </h2>
    <a href="/pacientes" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<?php if ($importResult !== null): ?>
    <?php if (!empty($importResult['importErrors'])): ?>
    <div class="alert alert-warning">
        <strong><i class="bi bi-exclamation-triangle me-1"></i>Se encontraron errores:</strong>
        <ul class="mb-1 mt-1 small">
            <?php foreach ($importResult['importErrors'] as $e): ?>
            <li><?= Security::e($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    <?php if ($importResult['insertados'] > 0 || $importResult['actualizados'] > 0): ?>
    <div class="alert alert-success">
        <i class="bi bi-check-circle me-1"></i>
        <strong><?= (int)$importResult['insertados'] ?></strong> paciente(s) creado(s),
        <strong><?= (int)$importResult['actualizados'] ?></strong> actualizado(s).
        <a href="/pacientes" class="ms-2">Ver listado →</a>
    </div>
    <?php endif; ?>
<?php endif; ?>

<div class="row g-4">
    <!-- Formulario de carga -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h5 class="fw-semibold mb-3"><i class="bi bi-file-earmark-spreadsheet me-2 text-success"></i>Cargar archivo CSV</h5>
                <form method="post" action="/pacientes/importar" enctype="multipart/form-data">
                    <?= Security::csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Archivo CSV <span class="text-danger">*</span></label>
                        <input type="file" name="csv" class="form-control" accept=".csv,text/csv" required>
                        <div class="form-text">Formato aceptado: <code>.csv</code> separado por <code>;</code> o <code>,</code> — máx. 5 MB</div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-upload me-1"></i>Importar pacientes
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Instrucciones -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h5 class="fw-semibold mb-3"><i class="bi bi-info-circle me-2 text-info"></i>Instrucciones</h5>
                <p class="small text-muted mb-2">El archivo debe tener las siguientes columnas en orden:</p>
                <div class="table-responsive mb-3">
                    <table class="table table-bordered table-sm small mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Columna</th>
                                <th>Descripción</th>
                                <th>Ejemplo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td class="fw-medium">Documento (CC)</td><td>Número de cédula</td><td>12345678</td></tr>
                            <tr><td class="fw-medium">Nombre completo</td><td>Apellidos y nombres</td><td>PEREZ JUAN</td></tr>
                            <tr><td class="fw-medium">Paquete</td><td>1 o 2</td><td>1</td></tr>
                            <tr><td class="fw-medium">NUI</td><td>Número único interno (opcional)</td><td>NUI-0023</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="alert alert-light border small mb-3 py-2">
                    <strong>Nota:</strong> Si el documento ya existe, se actualizan los datos. Si es nuevo, se crea el registro.
                </div>
                <a href="/pacientes/importar/plantilla" class="btn btn-outline-success btn-sm w-100">
                    <i class="bi bi-file-earmark-arrow-down me-1"></i>Descargar plantilla CSV
                </a>
            </div>
        </div>
    </div>
</div>

<?php require BASE_PATH . '/app/Views/layout/footer.php'; ?>
