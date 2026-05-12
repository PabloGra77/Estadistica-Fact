<?php
$values ??= [];
$errors ??= [];
$pageTitle = 'Subir Soporte — PPL';
require BASE_PATH . '/app/Views/layout/header.php';
$meses = ['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
$atencionPresel = Security::validateInt($_GET['atencion_id'] ?? '', 1) ?? null;
?>

<div class="d-flex align-items-center mb-4 gap-2">
    <a href="/soportes" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
    <h2 class="fw-bold mb-0">Subir soporte</h2>
</div>

<div class="card border-0 shadow-sm" style="max-width:560px">
    <div class="card-body p-4">
        <?php foreach ($errors as $err): ?>
        <div class="alert alert-danger py-2 small"><?= Security::e($err) ?></div>
        <?php endforeach; ?>

        <form method="post" action="/soportes/subir" enctype="multipart/form-data" novalidate>
            <?= Security::csrfField() ?>

            <div class="mb-3">
                <label for="atencion_id" class="form-label fw-semibold small">Atención <span class="text-danger">*</span></label>
                <select id="atencion_id" name="atencion_id" class="form-select form-select-sm" required>
                    <option value="">— Seleccione —</option>
                    <?php foreach ($atenciones as $a): ?>
                    <option value="<?= (int)$a['id'] ?>"
                        <?= ((int)($values['atencion_id'] ?? $atencionPresel ?? 0) === (int)$a['id']) ? 'selected' : '' ?>>
                        <?= Security::e(
                            $a['nombre'] . ' (' . $a['documento'] . ') · ' .
                            (TIPOS_SERVICIO[$a['servicio']] ?? '') . ' · ' .
                            $meses[(int)$a['mes_atencion']] . '/' . $a['anio_atencion']
                        ) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="soporte" class="form-label fw-semibold small">Archivo soporte <span class="text-danger">*</span></label>
                <input type="file" id="soporte" name="soporte" class="form-control form-control-sm"
                       accept=".pdf,.jpg,.jpeg,.png,.tif,.tiff" required/>
                <div class="form-text">Formatos: PDF, JPG, PNG, TIFF. Máx. <?= MAX_UPLOAD_MB ?> MB.</div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary btn-sm px-4">
                    <i class="bi bi-upload me-1"></i>Cargar soporte
                </button>
                <a href="/soportes" class="btn btn-outline-secondary btn-sm">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php require BASE_PATH . '/app/Views/layout/footer.php'; ?>
