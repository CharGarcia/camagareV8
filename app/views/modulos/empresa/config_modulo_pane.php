<?php
/**
 * Pestaña de personalización de un módulo en Configuración de Empresa (una por
 * módulo del catálogo App\Helpers\ConfigModulo). Se dibuja sola a partir del
 * catálogo; el guardado lo hace public/js/modulos/empresa_config_modulos.js
 * (EmpresaController::save, section = config_modulo).
 *
 * Variables: $cm (entrada de ConfigModuloService::datosPantalla()), $base.
 */
$h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div class="tab-pane fade" id="cfgmod-<?= $h($cm['slug']) ?>" role="tabpanel">
    <form class="cfgmod-form" data-modulo="<?= $h($cm['modulo']) ?>"
          data-url-guardar="<?= $h($base) ?>/modulos/empresa/save"
          data-url-clientes="<?= $h($base) ?>/modulos/empresa/getClientesConfigAjax">
        <h6 class="fw-bold fs-6 text-primary border-bottom pb-2 mb-2">
            <i class="bi <?= $h($cm['icono']) ?> me-1"></i><?= $h($cm['titulo']) ?>
        </h6>
        <?php if ($cm['descripcion'] !== ''): ?>
            <p class="text-muted small mb-3"><?= $h($cm['descripcion']) ?></p>
        <?php endif; ?>

        <?php if (!$cm['disponible']): ?>
            <div class="alert alert-warning small py-2">
                <i class="bi bi-exclamation-triangle me-1"></i>
                Falta ejecutar <code>database/2026-11-24_config_modulos.sql</code> en la base de datos. Mientras tanto se usan los valores por defecto y no se puede guardar.
            </div>
        <?php endif; ?>

        <?php foreach ($cm['opciones'] as $clave => $op): ?>
            <div class="cfgmod-opcion mb-4" data-clave="<?= $h($clave) ?>" data-tipo="<?= $h($op['tipo']) ?>">
                <?php if ($op['tipo'] === 'bool'): ?>
                    <div class="form-check form-switch">
                        <input class="form-check-input cfgmod-valor" type="checkbox" id="cfgmod-<?= $h($cm['slug'] . '-' . $clave) ?>" <?= !empty($op['valor']) ? 'checked' : '' ?>>
                        <label class="form-check-label small fw-bold" for="cfgmod-<?= $h($cm['slug'] . '-' . $clave) ?>"><?= $h($op['etiqueta']) ?></label>
                    </div>

                <?php elseif ($op['tipo'] === 'select'): ?>
                    <label class="form-label small fw-bold d-block"><?= $h($op['etiqueta']) ?></label>
                    <select class="form-select form-select-sm cfgmod-valor" style="max-width:320px">
                        <?php foreach ($op['valores'] as $v => $txt): ?>
                            <option value="<?= $h($v) ?>" <?= (string) $op['valor'] === (string) $v ? 'selected' : '' ?>><?= $h($txt) ?></option>
                        <?php endforeach; ?>
                    </select>

                <?php elseif ($op['tipo'] === 'texto'): ?>
                    <label class="form-label small fw-bold d-block"><?= $h($op['etiqueta']) ?></label>
                    <input type="text" class="form-control form-control-sm cfgmod-valor" maxlength="<?= (int) ($op['max'] ?? 255) ?>" value="<?= $h($op['valor']) ?>">

                <?php elseif ($op['tipo'] === 'columnas'): ?>
                    <?php
                    $colsDef = [];
                    foreach ($op['columnas'] as $k => $c) {
                        $colsDef[] = ['clave' => $k, 'titulo' => $c['titulo'], 'fija' => !empty($c['fija'])];
                    }
                    ?>
                    <div class="small fw-bold mb-1"><?= $h($op['etiqueta']) ?></div>
                    <?php if (!empty($op['ayuda'])): ?>
                        <div class="text-muted small mb-2"><?= $h($op['ayuda']) ?></div>
                    <?php endif; ?>

                    <div class="border rounded-3 p-2 mb-3 bg-light">
                        <div class="small fw-semibold mb-1">Para todos los clientes (por defecto)</div>
                        <?php foreach ($op['columnas'] as $k => $c): ?>
                            <?php $fija = !empty($c['fija']); ?>
                            <div class="form-check form-check-inline small">
                                <input class="form-check-input cfgmod-col-empresa" type="checkbox" value="<?= $h($k) ?>"
                                       id="cfgmod-<?= $h($cm['slug'] . '-' . $clave . '-' . $k) ?>"
                                       <?= ($fija || in_array($k, (array) $op['valor'], true)) ? 'checked' : '' ?>
                                       <?= $fija ? 'disabled title="Siempre visible"' : '' ?>>
                                <label class="form-check-label" for="cfgmod-<?= $h($cm['slug'] . '-' . $clave . '-' . $k) ?>"><?= $h($c['titulo']) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if (!empty($op['por_cliente'])): ?>
                        <div class="cfgmod-clientes" data-columnas="<?= $h(json_encode($colsDef, JSON_UNESCAPED_UNICODE)) ?>">
                            <div class="small fw-semibold mb-1">Clientes con columnas propias</div>
                            <div class="text-muted small mb-2">Estos clientes usan sus propias columnas en lugar de las de arriba. Quite un cliente para que vuelva a usar las columnas por defecto.</div>

                            <div class="position-relative mb-2" style="max-width:440px">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input type="text" class="form-control cfgmod-buscar-cliente" placeholder="Buscar cliente por nombre o identificación para agregarlo…" autocomplete="off">
                                </div>
                                <div class="list-group position-absolute w-100 shadow-sm cfgmod-resultados d-none" style="z-index:1050; max-height:240px; overflow:auto;"></div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle small mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Cliente</th>
                                            <?php foreach ($op['columnas'] as $k => $c): ?>
                                                <th class="text-center text-nowrap"><?= $h($c['titulo']) ?></th>
                                            <?php endforeach; ?>
                                            <th style="width:36px"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="cfgmod-tbody">
                                        <?php foreach ($op['clientes'] as $cli): ?>
                                            <tr data-id-cliente="<?= (int) $cli['id'] ?>">
                                                <td>
                                                    <div class="fw-semibold"><?= $h($cli['nombre']) ?></div>
                                                    <div class="text-muted" style="font-size:.7rem"><?= $h($cli['identificacion']) ?></div>
                                                </td>
                                                <?php foreach ($op['columnas'] as $k => $c): ?>
                                                    <?php $fija = !empty($c['fija']); ?>
                                                    <td class="text-center">
                                                        <input class="form-check-input cfgmod-col-cli" type="checkbox" value="<?= $h($k) ?>"
                                                               <?= ($fija || in_array($k, (array) $cli['valor'], true)) ? 'checked' : '' ?>
                                                               <?= $fija ? 'disabled' : '' ?>>
                                                    </td>
                                                <?php endforeach; ?>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-sm btn-link text-danger p-0 cfgmod-quitar" title="Quitar cliente"><i class="bi bi-x-lg"></i></button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="cfgmod-vacio <?= $op['clientes'] ? 'd-none' : '' ?>">
                                            <td colspan="<?= count($op['columnas']) + 2 ?>" class="text-center text-muted py-2">Ningún cliente con columnas propias: todos usan las columnas por defecto.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <div class="text-end">
            <button type="submit" class="btn btn-primary btn-sm px-4 cfgmod-guardar" <?= $cm['disponible'] ? '' : 'disabled' ?>>Guardar Configuración</button>
        </div>
    </form>
</div>
