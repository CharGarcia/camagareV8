<?php

/**
 * Pestaña «Contable» de las fichas de proveedor y cliente: las reglas por entidad de
 * Configuración Contable (asientos_programados con tipo_referencia = 'proveedor' | 'cliente'),
 * editadas desde la propia ficha. La lógica vive en public/js/components/ficha_contable.js
 * (se carga aquí, una sola vez) y usa los endpoints de modulos/configuracion-contable, que
 * validan los permisos de ese módulo.
 *
 * Quien lo incluye decide si se pinta (el usuario debe poder ver Configuración Contable) y
 * pasa $fichaContable:
 *   panel            id del tab-pane (el botón de la pestaña apunta a él con data-bs-target)
 *   tipo_referencia  'proveedor' | 'cliente'
 *   id_input         input con el id de la ficha
 *   nombre_input     input con el nombre (para el mensaje de confirmación)
 *   modal            id del modal de la ficha
 *   evento           CustomEvent que emite la ficha al guardar (detail.id = id guardado)
 *   tipos            [tipo_asiento => etiqueta] que admiten reglas por esta entidad
 *   entidad          'el proveedor' | 'el cliente' (textos)
 *   sin_barra        true = sin selector de tipo de asiento, badges de estado ni botones
 *                    (Copiar de General / Quitar configuración / Configuración Contable);
 *                    se usa el primer tipo de 'tipos'
 *   modo_asiento     true = barra con el selector «Asiento: General | Personalizado» en lugar del
 *                    selector de tipo (se usa el primer tipo de 'tipos'). En General las cuentas
 *                    se muestran en solo lectura; Personalizado permite elegir cuentas propias y
 *                    volver a General quita todas (con confirmación). Lo usa el empleado.
 *   ayuda            texto (HTML de confianza, escrito en el código) en lugar de la ayuda de
 *                    clientes/proveedores
 *   nomina           App\Helpers\NominaConceptos::paraJs(): cadenas de respaldo y cuándo aplica
 *                    cada concepto
 *   opciones         [fr|dt|dc|iess => id del <select> de la ficha]: con 'nomina', la pestaña solo
 *                    muestra los conceptos que aplican a lo elegido y se repinta al cambiarlo
 *
 * Sus controles no llevan "name": viven dentro del <form> de la ficha y no se envían con él.
 */

/** @var array $fichaContable */
$fcPerm   = \App\Helpers\Permisos::porRuta('modulos/configuracion-contable');
$fcUrl    = BASE_URL . '/modulos/configuracion-contable';
$fcEnt    = $fichaContable['entidad'] ?? 'la ficha';
$fcModo   = !empty($fichaContable['modo_asiento']);
$fcJson   = static fn($v): string => htmlspecialchars((string) json_encode($v, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
?>
<div class="tab-pane fade" id="<?= htmlspecialchars($fichaContable['panel']) ?>" role="tabpanel"
     data-ficha-contable="1"
     data-tipo-referencia="<?= htmlspecialchars($fichaContable['tipo_referencia']) ?>"
     data-id-input="<?= htmlspecialchars($fichaContable['id_input']) ?>"
     data-nombre-input="<?= htmlspecialchars($fichaContable['nombre_input'] ?? '') ?>"
     data-modal="<?= htmlspecialchars($fichaContable['modal']) ?>"
     data-evento="<?= htmlspecialchars($fichaContable['evento'] ?? '') ?>"
     data-entidad="<?= htmlspecialchars($fcEnt) ?>"
     data-url-contable="<?= htmlspecialchars($fcUrl) ?>"
     data-url-cuentas="<?= htmlspecialchars(BASE_URL . '/modulos/plan-cuentas/searchAjaxCuentas') ?>"
     data-puede-crear="<?= !empty($fcPerm['crear']) ? '1' : '0' ?>"
     data-puede-eliminar="<?= !empty($fcPerm['eliminar']) ? '1' : '0' ?>"
     <?php if ($fcModo): ?>data-modo-asiento="1"<?php endif; ?>
     <?php if (!empty($fichaContable['nomina'])): ?>data-nomina="<?= $fcJson($fichaContable['nomina']) ?>"<?php endif; ?>
     <?php if (!empty($fichaContable['opciones'])): ?>data-opciones="<?= $fcJson($fichaContable['opciones']) ?>"<?php endif; ?>>
    <div data-fctb="sin-guardar" class="text-center text-muted small py-4">
        <i class="bi bi-info-circle me-1"></i> Guarde <?= htmlspecialchars($fcEnt) ?> para configurar sus cuentas contables.
    </div>
    <div data-fctb="wrap" class="d-none">
        <?php if ($fcModo): ?>
        <?php // Tipo de asiento fijo (el primero de 'tipos'); el JS lo lee igual por data-fctb="tipo". ?>
        <input type="hidden" data-fctb="tipo" value="<?= htmlspecialchars((string) array_key_first($fichaContable['tipos'] ?? [])) ?>">
        <div class="d-flex flex-wrap align-items-end gap-2 mb-2">
            <div>
                <label class="form-label small fw-bold text-muted mb-1 d-block">Asiento contable</label>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-secondary" data-fctb="modo-general" title="Usa las cuentas de la configuración General">
                        <i class="bi bi-diagram-3 me-1"></i> General
                    </button>
                    <button type="button" class="btn btn-outline-secondary" data-fctb="modo-personalizado" title="Elegir cuentas propias para <?= htmlspecialchars($fcEnt) ?>">
                        <i class="bi bi-person-gear me-1"></i> Personalizado
                    </button>
                </div>
            </div>
            <div class="d-flex align-items-center gap-1 pb-1" data-fctb="estado"></div>
            <div class="ms-auto d-flex flex-wrap gap-1">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-fctb="copiar" title="Copia como propias las cuentas de la configuración General que aún no tenga">
                    <i class="bi bi-clipboard-check me-1"></i> Copiar cuentas de General
                </button>
                <a class="btn btn-outline-primary btn-sm" href="<?= htmlspecialchars($fcUrl) ?>" target="_blank" rel="noopener" title="Abrir Configuración Contable en otra pestaña">
                    <i class="bi bi-box-arrow-up-right me-1"></i> Configuración Contable
                </a>
            </div>
        </div>
        <?php elseif (!empty($fichaContable['sin_barra'])): ?>
        <?php // Sin barra: tipo de asiento fijo (el primero de 'tipos'); el JS lo lee igual por data-fctb="tipo". ?>
        <input type="hidden" data-fctb="tipo" value="<?= htmlspecialchars((string) array_key_first($fichaContable['tipos'] ?? [])) ?>">
        <?php else: ?>
        <div class="d-flex flex-wrap align-items-end gap-2 mb-2">
            <div style="width:340px;">
                <label class="form-label small fw-bold text-muted mb-1 d-block">Tipo de asiento</label>
                <select class="form-select form-select-sm" data-fctb="tipo">
                    <?php foreach (($fichaContable['tipos'] ?? []) as $fcValor => $fcEtiqueta): ?>
                        <option value="<?= htmlspecialchars($fcValor) ?>"><?= htmlspecialchars($fcEtiqueta) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="d-flex align-items-center gap-1 pb-1" data-fctb="estado"></div>
            <div class="ms-auto d-flex flex-wrap gap-1">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-fctb="copiar" title="Copia las cuentas de la configuración General que aún no tenga">
                    <i class="bi bi-clipboard-check me-1"></i> Copiar cuentas de General
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm" data-fctb="quitar" title="Quitar todas las cuentas propias: vuelve a usar la configuración General">
                    <i class="bi bi-trash me-1"></i> Quitar configuración
                </button>
                <a class="btn btn-outline-primary btn-sm" href="<?= htmlspecialchars($fcUrl) ?>" target="_blank" rel="noopener" title="Abrir Configuración Contable en otra pestaña">
                    <i class="bi bi-box-arrow-up-right me-1"></i> Configuración Contable
                </a>
            </div>
        </div>
        <?php endif; ?>
        <div class="text-muted mb-2" style="font-size:.72rem; line-height:1.5;">
            <i class="bi bi-info-circle me-1"></i>
            <?php if (!empty($fichaContable['ayuda'])): ?>
            <?= $fichaContable['ayuda'] ?>
            <?php else: ?>
            Si <?= htmlspecialchars($fcEnt) ?> tiene al menos una cuenta propia, <b>todo el documento</b> se contabiliza con sus cuentas;
            los conceptos que no le asigne usan la cuenta de la configuración <b>General</b> (se muestra en gris)
            y ya no se reparten por producto, categoría o marca. El IVA por tarifa sigue su propia cascada.
            Cada cuenta se guarda al elegirla; al vaciar el campo se quita.
            <?php endif; ?>
        </div>
        <div data-fctb="cuerpo"></div>
    </div>
</div>
<?php if (!defined('FICHA_CONTABLE_JS_LOADED')): define('FICHA_CONTABLE_JS_LOADED', true); ?>
    <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/ficha_contable.js?v=<?= asset_ver('/js/components/ficha_contable.js') ?>"></script>
<?php endif; ?>
