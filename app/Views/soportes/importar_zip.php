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
│   └── 1006526333_TS.pdf        ← Trabajo Social
└── ...</pre>
        <p class="mb-1 small fw-semibold">Códigos de servicio válidos:</p>
        <div class="row g-2 mb-3">
            <?php
            $descripciones = [
                'VALPS' => 'Primera vez Psicología',
                'VALPQ' => 'Primera vez Psiquiatría',
                'PS'    => 'Evolución Psicología',
                'PQ'    => 'Evolución Psiquiátrica',
                'TF'    => 'Fisioterapia',
                'TS'    => 'Trabajo Social',
                'TZ'    => 'Tamizaje',
                'PYP'   => 'PYP',
                'MED'   => 'Acta de Medicamentos',
                'REG'   => 'Regencia',
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
        </div>
    </div>
</div>

<!-- Formulario -->
<?php if (!$completado): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-0 pt-1">
        <ul class="nav nav-tabs card-header-tabs" id="tabModo" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="tab-upload-btn" data-bs-toggle="tab"
                        data-bs-target="#tab-upload" type="button" role="tab">
                    <i class="bi bi-upload me-1"></i>Subir ZIP
                    <span class="badge text-bg-secondary ms-1" style="font-size:.65rem">hasta ~200 MB</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-inbox-btn" data-bs-toggle="tab"
                        data-bs-target="#tab-inbox" type="button" role="tab">
                    <i class="bi bi-inbox-fill me-1"></i>Bandeja del servidor
                    <span class="badge text-bg-success ms-1" style="font-size:.65rem">ZIPs grandes</span>
                </button>
            </li>
        </ul>
    </div>
    <div class="card-body tab-content">

        <!-- Pestaña: Subir ZIP por HTTP -->
        <div class="tab-pane fade show active" id="tab-upload" role="tabpanel">
            <form method="post" action="/soportes/importar-zip" enctype="multipart/form-data" class="row g-3 mt-1">
                <?= Security::csrfField() ?>
                <input type="hidden" name="modo" value="upload"/>
                <input type="hidden" name="MAX_FILE_SIZE" value="<?= MAX_UPLOAD_MB * 1024 * 1024 ?>"/>
                <div class="col-12">
                    <label class="form-label fw-semibold" for="zip_soportes">Seleccionar archivo ZIP</label>
                    <input type="file" id="zip_soportes" name="zip_soportes"
                           accept=".zip,application/zip" class="form-control" required/>
                    <div class="form-text">Límite: <?= MAX_UPLOAD_MB ?> MB. Para ZIPs más grandes use la pestaña <strong>Bandeja del servidor</strong>.</div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-upload me-1"></i>Procesar ZIP
                    </button>
                </div>
            </form>
        </div>

        <!-- Pestaña: Bandeja de entrada (archivos grandes) -->
        <div class="tab-pane fade" id="tab-inbox" role="tabpanel">
            <div class="alert alert-info small mt-3 mb-3 py-2">
                <i class="bi bi-lightbulb-fill me-1"></i>
                <strong>Para ZIPs de varios GB</strong>, copie el archivo directamente a la carpeta de bandeja en el servidor:<br>
                <code class="d-block mt-1"><?= Security::e(INBOX_PATH) ?></code>
                <span class="d-block mt-1 text-muted">Puede hacerlo por red local, USB, o cualquier medio. El servidor procesa el ZIP sin moverlo ni descomprimirlo completamente.</span>
            </div>

            <?php if (empty($inboxZips)): ?>
            <div class="text-center text-muted py-4">
                <i class="bi bi-inbox display-5 d-block mb-2 opacity-25"></i>
                La bandeja está vacía. Copie un archivo <code>.zip</code> a la carpeta indicada y recargue la página.
            </div>
            <?php else: ?>
            <form method="post" action="/soportes/importar-zip" class="row g-3 mt-0">
                <?= Security::csrfField() ?>
                <input type="hidden" name="modo" value="inbox"/>
                <div class="col-12">
                    <label class="form-label fw-semibold">ZIP disponible en la bandeja</label>
                    <select name="zip_inbox" class="form-select" required>
                        <option value="">— Seleccionar —</option>
                        <?php foreach ($inboxZips as $zipPath):
                            $nombre = basename($zipPath);
                            $tamMB  = round(filesize($zipPath) / 1048576, 1);
                        ?>
                        <option value="<?= Security::e($nombre) ?>">
                            <?= Security::e($nombre) ?> (<?= $tamMB ?> MB)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">El archivo permanece en la bandeja después de procesar. Bórrelo manualmente cuando ya no lo necesite.</div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-gear-fill me-1"></i>Procesar desde bandeja
                    </button>
                </div>
            </form>
            <?php endif; ?>
        </div>

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
