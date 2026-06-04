<?php
$base = BASE_URL;
$nivelUsuario = $nivel ?? 0;
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h4 class="mb-0 fw-bold"><i class="bi bi-file-earmark-excel text-success me-2"></i><?= htmlspecialchars($titulo) ?></h4>
        <p class="text-muted mb-0 small">Importa registros masivamente a partir de una plantilla de Excel.</p>
    </div>
    <a href="<?= $base ?>/config" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver a Configuración</a>
</div>

<div class="row">
    <div class="col-md-5">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-bottom-0">
                <h6 class="mb-0 fw-bold"><i class="bi bi-1-circle text-primary me-2"></i>Paso 1: Configurar y Descargar Plantilla</h6>
            </div>
            <div class="card-body">
                <form id="formDescargarPlantilla" method="GET" action="<?= $base ?>/config/importadorExcel">
                    <input type="hidden" name="action" value="descargarPlantillaAjax">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Tipo de Catálogo</label>
                        <div class="d-flex gap-4">
                            <div class="form-check">
                                <input class="form-check-input check-tipo-catalogo" type="radio" name="tipo_catalogo" id="tipoOperativas" value="0" checked>
                                <label class="form-check-label" for="tipoOperativas">Tablas Operativas</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input check-tipo-catalogo" type="radio" name="tipo_catalogo" id="tipoGlobales" value="1">
                                <label class="form-check-label" for="tipoGlobales">Tablas Globales</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Entidad a importar</label>
                        <select class="form-select" name="entidad" id="selectEntidad" required>
                            <option value="">-- Seleccione el catálogo --</option>
                            <!-- Llenado vía JS -->
                        </select>
                    </div>

                    <!-- Filtros solo para tablas operativas -->
                    <div id="contenedorFiltrosDestino" class="d-none">
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Empresa de destino</label>
                            <select class="form-select" name="id_empresa" id="selectEmpresa">
                                <?php foreach ($empresasDestino as $emp): ?>
                                    <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['razon_social']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if($nivelUsuario < 3): ?>
                                <div class="form-text text-muted" style="font-size:0.75rem;"><i class="bi bi-info-circle"></i> Solo puedes importar a tu empresa activa.</div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Ambiente</label>
                            <select class="form-select" name="tipo_ambiente" id="selectAmbiente">
                                <option value="1">1 - Pruebas</option>
                                <option value="2">2 - Producción</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mt-2" id="btnDescargar" disabled>
                        <i class="bi bi-download me-2"></i>Descargar Plantilla de Ejemplo
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-bottom-0">
                <h6 class="mb-0 fw-bold"><i class="bi bi-2-circle text-primary me-2"></i>Paso 2: Subir y Procesar</h6>
            </div>
            <div class="card-body">
                <form id="formProcesarExcel" method="POST" enctype="multipart/form-data">
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Archivo de Excel diligenciado</label>
                        <input type="file" class="form-control" name="archivo_excel" id="archivoExcel" accept=".xlsx" required disabled>
                        <div class="form-text text-muted" style="font-size:0.75rem;">Asegúrate de no modificar las cabeceras de la plantilla.</div>
                    </div>

                    <div class="alert alert-warning border-warning border-opacity-50 small bg-warning bg-opacity-10 d-flex align-items-center gap-2">
                        <i class="bi bi-exclamation-triangle fs-4 text-warning"></i>
                        <div>
                            <strong>¡Atención!</strong> Si ocurre un error de validación en alguna fila (ej. falta de un dato obligatorio), <b>toda la importación será abortada</b> y ningún registro se guardará.
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success w-100" id="btnProcesar" disabled>
                        <i class="bi bi-cloud-arrow-up me-2"></i>Iniciar Importación Masiva
                    </button>
                </form>

                <div id="divResultado" class="mt-4 d-none">
                    <div class="alert" id="alertResultado" role="alert"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const selectEntidad = document.getElementById('selectEntidad');
    const contenedorFiltros = document.getElementById('contenedorFiltrosDestino');
    const btnDescargar = document.getElementById('btnDescargar');
    const archivoExcel = document.getElementById('archivoExcel');
    const btnProcesar = document.getElementById('btnProcesar');
    const formProcesar = document.getElementById('formProcesarExcel');
    const selectEmpresa = document.getElementById('selectEmpresa');
    const selectAmbiente = document.getElementById('selectAmbiente');
    const alertResultado = document.getElementById('alertResultado');
    const divResultado = document.getElementById('divResultado');

    const entidades = <?= json_encode($entidades) ?>;

    let tsEntidad = null;
    let tsEmpresa = null;

    if (typeof TomSelect !== 'undefined') {
        tsEntidad = new TomSelect('#selectEntidad', {
            placeholder: '-- Seleccione el catálogo --',
            searchField: ['text'],
            maxOptions: 50,
            options: [],
            valueField: 'id',
            labelField: 'text',
        });
        tsEmpresa = new TomSelect('#selectEmpresa', {
            searchField: ['text'],
            maxOptions: 50
        });
    }

    const radiosTipoCatalogo = document.querySelectorAll('.check-tipo-catalogo');

    const updateOpcionesEntidad = () => {
        const isGlobalReq = document.getElementById('tipoGlobales').checked;
        const options = [];
        for (const key in entidades) {
            const ent = entidades[key];
            const esGlobal = ent.global || false;
            if (isGlobalReq === esGlobal) {
                options.push({ id: key, text: ent.nombre, global: esGlobal ? '1' : '0' });
            }
        }

        if (tsEntidad) {
            tsEntidad.clear();
            tsEntidad.clearOptions();
            tsEntidad.addOptions(options);
            tsEntidad.refreshOptions(false);
        } else {
            selectEntidad.innerHTML = '<option value="">-- Seleccione el catálogo --</option>';
            options.forEach(opt => {
                const o = document.createElement('option');
                o.value = opt.id;
                o.textContent = opt.text;
                o.setAttribute('data-global', opt.global);
                selectEntidad.appendChild(o);
            });
        }
    };

    // Inicializar opciones
    updateOpcionesEntidad();

    radiosTipoCatalogo.forEach(r => r.addEventListener('change', updateOpcionesEntidad));

    const handleChangeEntidad = (value) => {
        if (!value) {
            contenedorFiltros.classList.add('d-none');
            btnDescargar.disabled = true;
            archivoExcel.disabled = true;
            btnProcesar.disabled = true;
            return;
        }

        let isGlobal = false;
        if (tsEntidad) {
            const opt = tsEntidad.options[value];
            isGlobal = opt && opt.global === '1';
        } else {
            const option = selectEntidad.options[selectEntidad.selectedIndex];
            isGlobal = option.getAttribute('data-global') === '1';
        }
        
        if (isGlobal) {
            contenedorFiltros.classList.add('d-none');
        } else {
            contenedorFiltros.classList.remove('d-none');
        }

        btnDescargar.disabled = false;
        archivoExcel.disabled = false;
        archivoExcel.value = ''; // Limpiar input si cambian de entidad
        btnProcesar.disabled = true;
    };

    if (tsEntidad) {
        tsEntidad.on('change', handleChangeEntidad);
    } else {
        selectEntidad.addEventListener('change', (e) => handleChangeEntidad(e.target.value));
    }

    archivoExcel.addEventListener('change', () => {
        if (archivoExcel.files.length > 0) {
            btnProcesar.disabled = false;
        } else {
            btnProcesar.disabled = true;
        }
    });

    formProcesar.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        btnProcesar.disabled = true;
        btnProcesar.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Procesando...';
        divResultado.classList.add('d-none');
        alertResultado.className = 'alert';

        const formData = new FormData();
        const valEntidad = selectEntidad.value;
        formData.append('entidad', valEntidad);
        formData.append('archivo_excel', archivoExcel.files[0]);

        let isGlobal = false;
        if (tsEntidad) {
            const opt = tsEntidad.options[valEntidad];
            isGlobal = opt && opt.global === '1';
        } else {
            const option = selectEntidad.options[selectEntidad.selectedIndex];
            isGlobal = option.getAttribute('data-global') === '1';
        }

        if (!isGlobal) {
            formData.append('id_empresa', selectEmpresa.value);
            formData.append('tipo_ambiente', selectAmbiente.value);
        }

        try {
            const resp = await fetch('<?= $base ?>/config/importadorExcel?action=procesarImportacionAjax', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await resp.json();

            divResultado.classList.remove('d-none');
            if (data.ok) {
                alertResultado.classList.add('alert-success');
                alertResultado.innerHTML = `<i class="bi bi-check-circle-fill me-2"></i> ${data.mensaje}`;
                formProcesar.reset();
                btnProcesar.disabled = true;
            } else {
                alertResultado.classList.add('alert-danger');
                alertResultado.innerHTML = `<i class="bi bi-x-circle-fill me-2"></i> ${data.mensaje}`;
            }
        } catch (error) {
            divResultado.classList.remove('d-none');
            alertResultado.classList.add('alert-danger');
            alertResultado.innerHTML = `<i class="bi bi-x-circle-fill me-2"></i> Hubo un error de red al intentar subir el archivo.`;
        } finally {
            btnProcesar.innerHTML = '<i class="bi bi-cloud-arrow-up me-2"></i>Iniciar Importación Masiva';
            if (archivoExcel.files.length > 0) btnProcesar.disabled = false;
        }
    });
});
</script>
