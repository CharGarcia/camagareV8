<?php
/**
 * Modal del beneficiario del dividendo (sección C.1 del anexo).
 * Se abre sobre el modal principal; el z-index lo eleva anexo_dividendos.js
 * porque app.css fuerza .modal { z-index: 5060 !important }.
 *
 * @var array $catalogo
 */
?>
<div class="modal fade" id="modalAdiBeneficiario" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">

            <div class="modal-header bg-light py-2">
                <h6 class="modal-title fw-bold">
                    <i class="bi bi-person-badge text-primary me-2"></i>
                    <span id="adi-ben-titulo">Nuevo beneficiario</span>
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body p-3">
                <input type="hidden" id="adi-ben-id" value="">
                <input type="hidden" id="adi-ben-tipo-entidad" value="">
                <input type="hidden" id="adi-ben-id-entidad" value="">

                <div class="row g-3">
                    <div class="col-12 position-relative">
                        <label class="form-label small fw-bold d-block">Buscar en clientes, proveedores o empleados</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input type="text" id="adi-ben-buscar" class="form-control"
                                   placeholder="Nombre o identificación..." autocomplete="off">
                        </div>
                        <div class="list-group adi-tercero-lista d-none shadow" id="adi-ben-resultados"></div>
                        <div class="form-text adi-nota">
                            Opcional: rellena la identificación y el nombre, y deja el vínculo con el tercero del sistema.
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold d-block">Tipo de identificación</label>
                        <select id="adi-ben-tipo-id" class="form-select form-select-sm">
                            <?php foreach ($catalogo['tipo_identificacion'] as $cod => $nom): ?>
                                <option value="<?= $cod ?>"><?= $cod ?> - <?= htmlspecialchars($nom) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold d-block">Identificación</label>
                        <input type="text" id="adi-ben-numero-id" class="form-control form-control-sm" maxlength="13">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold d-block">Nombre o razón social</label>
                        <input type="text" id="adi-ben-nombre" class="form-control form-control-sm" maxlength="500">
                    </div>

                    <div class="col-md-8">
                        <label class="form-label small fw-bold d-block">Tipo de beneficiario</label>
                        <select id="adi-ben-tipo" class="form-select form-select-sm"></select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold d-block">País de residencia</label>
                        <select id="adi-ben-pais" class="form-select form-select-sm">
                            <?php foreach ($catalogo['paises'] as $cod => $nom): ?>
                                <option value="<?= $cod ?>"><?= $cod ?> - <?= htmlspecialchars($nom) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6" id="adi-ben-wrap-regimen">
                        <label class="form-label small fw-bold d-block">
                            ¿El dividendo está gravado en el estado de residencia del beneficiario?
                        </label>
                        <select id="adi-ben-regimen" class="form-select form-select-sm">
                            <option value="">— No aplica —</option>
                            <?php foreach ($catalogo['respuesta'] as $cod => $nom): ?>
                                <option value="<?= $cod ?>"><?= htmlspecialchars($nom) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3" id="adi-ben-wrap-tipo-efec">
                        <label class="form-label small fw-bold d-block">Tipo id. beneficiario efectivo</label>
                        <select id="adi-ben-tipo-efec" class="form-select form-select-sm">
                            <option value="">— No aplica —</option>
                            <option value="R">R - RUC</option>
                            <option value="C">C - Cédula</option>
                            <option value="P">P - Pasaporte</option>
                        </select>
                    </div>
                    <div class="col-md-3" id="adi-ben-wrap-num-efec">
                        <label class="form-label small fw-bold d-block">Identificación beneficiario efectivo</label>
                        <input type="text" id="adi-ben-num-efec" class="form-control form-control-sm" maxlength="13">
                    </div>
                </div>

                <div class="alert alert-light border adi-nota mt-3 mb-0" id="adi-ben-ayuda"></div>
            </div>

            <div class="modal-footer bg-light py-2 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-danger btn-sm d-none" id="adi-ben-btn-eliminar"
                        onclick="ADI_eliminarBeneficiario()">
                    <i class="bi bi-trash me-1"></i> Eliminar
                </button>
                <div class="ms-auto d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary btn-sm px-4" onclick="ADI_guardarBeneficiario()">
                        <i class="bi bi-check-lg me-1"></i> Guardar
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>
