<?php

/** @var array|null $acuerdo */
/** @var array|null $contrato */
/** @var array $histAcuerdo */
/** @var array $histContrato */
$base = BASE_URL;
$msg = $msg ?? null;
$acuerdo = $acuerdo ?? null;
$contrato = $contrato ?? null;
$histAcuerdo = $histAcuerdo ?? [];
$histContrato = $histContrato ?? [];
$e = static fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');

$docs = [
    'acuerdo_datos' => ['doc' => $acuerdo,  'hist' => $histAcuerdo,  'label' => 'Acuerdo de uso de datos',      'icono' => 'shield-lock', 'color' => 'primary'],
    'contrato_uso'  => ['doc' => $contrato, 'hist' => $histContrato, 'label' => 'Contrato de uso del sistema',  'icono' => 'file-earmark-text', 'color' => 'success'],
];
?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<div class="container-fluid px-3 py-3">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h5 class="mb-0"><i class="bi bi-journal-text me-2"></i>Documentos legales</h5>
        <a href="<?= $base ?>/config" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Volver a configuración
        </a>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-<?= $e($msg[0]) ?> alert-dismissible fade show py-2 px-3 small" role="alert">
            <?= $e($msg[1]) ?>
            <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="alert alert-info py-2 px-3 small d-flex align-items-start gap-2">
        <i class="bi bi-info-circle mt-1"></i>
        <div>
            Estos documentos se envían <b>automáticamente al crear una empresa</b> y pueden reenviarse
            desde <b>Empresas del sistema</b>. Al guardar se publica una <b>versión nueva</b>: los envíos
            anteriores conservan la versión que realmente recibieron.
            <br>
            <span class="text-muted">
                Variables disponibles:
                <code>{{empresa_nombre}}</code> <code>{{empresa_ruc}}</code> <code>{{empresa_direccion}}</code>
                <code>{{empresa_representante}}</code> <code>{{empresa_correo}}</code>
                <code>{{fecha}}</code> <code>{{sistema_nombre}}</code>
            </span>
        </div>
    </div>

    <ul class="nav nav-tabs mb-3" role="tablist">
        <?php $primero = true; foreach ($docs as $tipo => $cfg): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $primero ? 'active' : '' ?>" data-bs-toggle="tab"
                        data-bs-target="#tab-<?= $e($tipo) ?>" type="button" role="tab">
                    <i class="bi bi-<?= $e($cfg['icono']) ?> me-1"></i><?= $e($cfg['label']) ?>
                    <?php if (!empty($cfg['doc'])): ?>
                        <span class="badge bg-<?= $e($cfg['color']) ?> bg-opacity-10 text-<?= $e($cfg['color']) ?> ms-1">v<?= (int) $cfg['doc']['version'] ?></span>
                    <?php else: ?>
                        <span class="badge bg-danger bg-opacity-10 text-danger ms-1">sin configurar</span>
                    <?php endif; ?>
                </button>
            </li>
        <?php $primero = false; endforeach; ?>
    </ul>

    <div class="tab-content">
        <?php $primero = true; foreach ($docs as $tipo => $cfg): $doc = $cfg['doc']; ?>
        <div class="tab-pane fade <?= $primero ? 'show active' : '' ?>" id="tab-<?= $e($tipo) ?>" role="tabpanel">
            <div class="row g-3">

                <div class="col-lg-9">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <form method="POST" action="<?= $base ?>/config/documentos-legales-guardar" class="form-doc" data-tipo="<?= $e($tipo) ?>">
                                <input type="hidden" name="tipo" value="<?= $e($tipo) ?>">
                                <input type="hidden" name="contenido" class="input-contenido">

                                <div class="mb-2">
                                    <label class="form-label small fw-semibold mb-1">Título del documento</label>
                                    <input type="text" name="titulo" class="form-control form-control-sm"
                                           value="<?= $e($doc['titulo'] ?? $cfg['label']) ?>" required maxlength="255">
                                </div>

                                <label class="form-label small fw-semibold mb-1">Contenido</label>
                                <div class="editor-doc border rounded" style="min-height:340px;background:#fff;"><?= $doc['contenido'] ?? '' ?></div>

                                <div class="d-flex gap-2 mt-3">
                                    <button type="submit" class="btn btn-sm btn-primary px-4">
                                        <i class="bi bi-save me-1"></i>Publicar nueva versión
                                    </button>
                                    <a href="<?= $base ?>/config/documentos-legales-previsualizar?tipo=<?= $e($tipo) ?>"
                                       target="_blank" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-file-earmark-pdf me-1"></i>Ver PDF de ejemplo
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3">
                    <div class="card shadow-sm">
                        <div class="card-header py-2 px-3 bg-light">
                            <span class="small fw-semibold"><i class="bi bi-clock-history me-1"></i>Versiones</span>
                        </div>
                        <div class="list-group list-group-flush" style="max-height:420px;overflow:auto;">
                            <?php if (empty($cfg['hist'])): ?>
                                <div class="list-group-item small text-muted">Sin versiones.</div>
                            <?php else: foreach ($cfg['hist'] as $h): ?>
                                <div class="list-group-item py-2 px-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="small fw-semibold">Versión <?= (int) $h['version'] ?></span>
                                        <?php if (!empty($h['vigente']) && $h['vigente'] !== 'f'): ?>
                                            <span class="badge bg-success">vigente</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-muted" style="font-size:.72rem;">
                                        <?= $e(date('d-m-Y H:i:s', strtotime((string) $h['created_at']))) ?>
                                    </div>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        <?php $primero = false; endforeach; ?>
    </div>

</div>

<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<script>
(function () {
    var editores = [];
    document.querySelectorAll('.form-doc').forEach(function (form) {
        var cont = form.querySelector('.editor-doc');
        var quill = new Quill(cont, {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ header: [2, 3, false] }],
                    ['bold', 'italic', 'underline'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    [{ align: [] }],
                    ['link', 'clean']
                ]
            }
        });
        editores.push(quill);

        form.addEventListener('submit', function (ev) {
            var html = quill.root.innerHTML.trim();
            if (html === '' || html === '<p><br></p>') {
                ev.preventDefault();
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Contenido vacío', 'Escriba el contenido del documento.', 'warning');
                } else {
                    alert('Escriba el contenido del documento.');
                }
                return;
            }
            form.querySelector('.input-contenido').value = html;
        });
    });
})();
</script>
