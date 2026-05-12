<?php $pageTitle = 'Importar ZIP — Soportes';
require BASE_PATH . '/app/Views/layout/header.php'; ?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h2 class="fw-bold mb-0">
        <i class="bi bi-file-zip-fill me-2" style="color:#7c3aed"></i>Importar soportes desde ZIP
    </h2>
    <a href="/soportes" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver a Soportes
    </a>
</div>

<!-- Instrucciones -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold py-2">
        <i class="bi bi-info-circle-fill text-primary me-1"></i>Estructura del archivo ZIP
    </div>
    <div class="card-body pb-2">
        <p class="mb-2 small">El ZIP debe contener <strong>subcarpetas nombradas con el documento (CC) de cada paciente</strong>.
        Dentro de cada subcarpeta van los PDFs con el siguiente formato:</p>
        <pre class="bg-light rounded p-3 small mb-3">archivo.zip
├── 872394723ERR/
│   ├── 872394723ERR_PS.pdf      ← Evolución Psicología
│   ├── 872394723ERR_VALPS.pdf   ← Valoración Inicial Psicología
│   └── 872394723ERR_PQ.pdf      ← Evolución Psiquiatría
├── 1006526333/
│   └── 1006526333_TF.pdf        ← Trabajo Social/Familiar
└── ...</pre>
        <p class="mb-1 small fw-semibold">Códigos de servicio válidos:</p>
        <div class="row g-2 mb-3">
            <?php
            $descripciones = [
                'VALPS' => 'Valoración Inicial Psicología',
                'VALPQ' => 'Valoración Inicial Psiquiatría',
                'PS'    => 'Evolución Psicología',
                'PQ'    => 'Evolución Psiquiatría',
                'TF'    => 'Trabajo Social / Familiar',
                'TS'    => 'Terapia Sistémica',
            ];
            foreach (TIPOS_SERVICIO as $idx => $codigo): ?>
            <div class="col-6 col-md-4 col-lg-3">
                <span class="badge text-bg-primary me-1"><?= Security::e($codigo) ?></span>
                <small class="text-muted"><?= Security::e($descripciones[$codigo] ?? '') ?></small>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="alert alert-warning py-2 small mb-0">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>
            <strong>El documento del paciente debe existir en el sistema.</strong>
            Los archivos de pacientes no encontrados se omiten con una advertencia.
            Las atenciones del mes/año actual se crean automáticamente si no existen.
        </div>
    </div>
</div>

<!-- Formulario -->
<?php if (!$completado): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="post" action="/soportes/importar-zip" enctype="multipart/form-data" class="row g-3">
            <?= Security::csrfField() ?>
            <input type="hidden" name="MAX_FILE_SIZE" value="<?= MAX_UPLOAD_MB * 1024 * 1024 ?>"/>

            <div class="col-12">
                <label class="form-label fw-semibold" for="zip_soportes">
                    Seleccionar archivo ZIP
                </label>
                <input type="file" id="zip_soportes" name="zip_soportes"
                       accept=".zip,application/zip"
                       class="form-control" required/>
                <div class="form-text">Tamaño máximo permitido: <?= MAX_UPLOAD_MB ?> MB</div>
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-upload me-1"></i>Procesar ZIP
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Resultados -->
<?php if ($completado): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white d-flex align-items-center justify-content-between py-2">
        <span class="fw-semibold">
            <i class="bi bi-list-check me-1"></i>Resultados del proceso
        </span>
        <span>
            <span class="badge text-bg-success"><?= $procesados ?> procesados</span>
            <?php if ($errores > 0): ?>
            <span class="badge text-bg-danger ms-1"><?= $errores ?> con error/advertencia</span>
            <?php endif; ?>
        </span>
    </div>
    <div class="card-body p-0">
        <ul class="list-group list-group-flush" style="max-height:480px;overflow-y:auto">
            <?php foreach ($resultados as $r): ?>
            <li class="list-group-item list-group-item-<?= Security::e($r['tipo']) ?> py-1 small">
                <?php if ($r['tipo'] === 'success'): ?>
                    <i class="bi bi-check-circle-fill me-1"></i>
                <?php elseif ($r['tipo'] === 'warning'): ?>
                    <i class="bi bi-exclamation-circle-fill me-1"></i>
                <?php else: ?>
                    <i class="bi bi-x-circle-fill me-1"></i>
                <?php endif; ?>
                <?= Security::e($r['msg']) ?>
            </li>
            <?php endforeach; ?>
            <?php if (empty($resultados)): ?>
            <li class="list-group-item text-muted small py-2">No se encontraron archivos para procesar.</li>
            <?php endif; ?>
        </ul>
    </div>
</div>

<div class="d-flex gap-2">
    <a href="/soportes/importar-zip" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-upload me-1"></i>Importar otro ZIP
    </a>
    <a href="/soportes" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-file-earmark-text me-1"></i>Ver Soportes
    </a>
    <a href="/atenciones" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-bar-chart-line me-1"></i>Ver Atenciones
    </a>
</div>
<?php endif; ?>

<?php require BASE_PATH . '/app/Views/layout/footer.php'; ?>
