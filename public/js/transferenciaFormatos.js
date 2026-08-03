/**
 * Editor de columnas de /config/transferencia-formatos. Arma el array
 * `campos` (mismo shape que TransferenciaFormatoService::normalizarCampos)
 * como JSON en el input oculto #tf-campos-json antes de enviar el form.
 */
(function () {
    let filaSeq = 0;

    function origenDatoOptions(selected) {
        let html = '';
        for (const [valor, etiqueta] of Object.entries(window.TF_ORIGEN_DATO || {})) {
            html += `<option value="${valor}"${valor === selected ? ' selected' : ''}>${etiqueta}</option>`;
        }
        return html;
    }

    window.TF_agregarFilaCampo = function (c) {
        c = c || {};
        const id = 'tfc' + (++filaSeq);
        const tbody = document.getElementById('tf-campos-tbody');

        const tr = document.createElement('tr');
        tr.className = 'tf-fila-campo';
        tr.dataset.rowId = id;
        tr.innerHTML = `
            <td class="text-center text-muted fw-bold tf-c-num"></td>
            <td><input type="text" class="form-control form-control-sm tf-c-etiqueta" value="${escHtml(c.etiqueta || '')}" placeholder="Ej: Cuenta"></td>
            <td><select class="form-select form-select-sm tf-c-origen">${origenDatoOptions(c.origen_dato || '')}</select></td>
            <td><input type="text" class="form-control form-control-sm tf-c-valorfijo" value="${escHtml(c.valor_fijo || '')}" placeholder="Solo si el dato es 'Texto fijo'"></td>
            <td><select class="form-select form-select-sm tf-c-tipo">
                    <option value="texto"${(c.tipo_dato || 'texto') === 'texto' ? ' selected' : ''}>Texto</option>
                    <option value="numero"${c.tipo_dato === 'numero' ? ' selected' : ''}>Número</option>
                    <option value="fecha"${c.tipo_dato === 'fecha' ? ' selected' : ''}>Fecha</option>
                </select></td>
            <td class="text-center"><input type="checkbox" class="form-check-input tf-c-mayus" ${c.mayusculas ? 'checked' : ''}></td>
            <td class="text-center"><input type="checkbox" class="form-check-input tf-c-tildes" ${c.quitar_tildes ? 'checked' : ''}></td>
            <td><input type="number" min="1" class="form-control form-control-sm tf-c-maxcar" value="${c.max_caracteres || ''}"></td>
            <td><input type="number" min="1" class="form-control form-control-sm tf-c-long" value="${c.longitud_fija || ''}"></td>
            <td><input type="text" maxlength="1" class="form-control form-control-sm tf-c-relleno" value="${escHtml(c.relleno_caracter || '')}" placeholder="' ' u '0'"></td>
            <td class="text-nowrap">
                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-1 border-0" title="Mover arriba" onclick="TF_moverFila('${id}', -1)"><i class="bi bi-arrow-up"></i></button>
                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-1 border-0" title="Mover abajo" onclick="TF_moverFila('${id}', 1)"><i class="bi bi-arrow-down"></i></button>
                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-1 border-0" title="Opciones avanzadas" onclick="TF_toggleAvanzado('${id}')"><i class="bi bi-sliders"></i></button>
                <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1 border-0" title="Quitar" onclick="this.closest('tr').nextElementSibling?.remove(); this.closest('tr').remove(); TF_renumerarFilas();"><i class="bi bi-trash"></i></button>
            </td>`;
        tbody.appendChild(tr);

        const trAv = document.createElement('tr');
        trAv.className = 'tf-fila-avanzada d-none';
        trAv.dataset.rowId = id;
        trAv.innerHTML = `
            <td colspan="11" class="bg-light">
                <div class="row g-2 p-2 small">
                    <div class="col-md-2">
                        <label class="form-label mb-0">Alineación</label>
                        <select class="form-select form-select-sm tf-c-alineacion">
                            <option value=""${!c.alineacion ? ' selected' : ''}>Automática</option>
                            <option value="izquierda"${c.alineacion === 'izquierda' ? ' selected' : ''}>Izquierda</option>
                            <option value="derecha"${c.alineacion === 'derecha' ? ' selected' : ''}>Derecha</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-0">Formato número</label>
                        <select class="form-select form-select-sm tf-c-formatonum">
                            <option value="decimal_punto"${(c.formato_numero || 'decimal_punto') === 'decimal_punto' ? ' selected' : ''}>Decimal (180.00)</option>
                            <option value="entero_centavos"${c.formato_numero === 'entero_centavos' ? ' selected' : ''}>Entero en centavos (18000)</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label mb-0">Decimales</label>
                        <input type="number" min="0" class="form-control form-control-sm tf-c-decimales" value="${c.decimales ?? 2}">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input tf-c-alfanum" ${c.solo_alfanumerico ? 'checked' : ''}>
                            <label class="form-check-label">Solo alfanumérico</label>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label mb-0">Mapeo de valores (uno por línea, "clave=valor")</label>
                        <textarea class="form-control form-control-sm tf-c-mapeo" rows="2" placeholder="ahorros=AHO&#10;corriente=CTE">${escHtml(mapeoToTexto(c.mapeo_valores))}</textarea>
                    </div>
                </div>
            </td>`;
        tbody.appendChild(trAv);

        actualizarVisibilidadValorFijo(tr);
        tr.querySelector('.tf-c-origen').addEventListener('change', () => actualizarVisibilidadValorFijo(tr));
        TF_renumerarFilas();
    };

    /** Numera las filas visibles en orden (1, 2, 3…) para que el usuario no se pierda entre tantas columnas. */
    window.TF_renumerarFilas = function () {
        document.querySelectorAll('#tf-campos-tbody > .tf-fila-campo').forEach((tr, i) => {
            const celda = tr.querySelector('.tf-c-num');
            if (celda) celda.textContent = i + 1;
        });
    };

    function parDeFila(id) {
        const tbody = document.getElementById('tf-campos-tbody');
        return [
            tbody.querySelector(`.tf-fila-campo[data-row-id="${id}"]`),
            tbody.querySelector(`.tf-fila-avanzada[data-row-id="${id}"]`),
        ];
    }

    /** Mueve la fila (y su fila de opciones avanzadas asociada) una posición arriba (-1) o abajo (+1). El orden en pantalla define el orden real de las columnas en el archivo. */
    window.TF_moverFila = function (id, direccion) {
        const tbody = document.getElementById('tf-campos-tbody');
        const filas = Array.from(tbody.querySelectorAll('.tf-fila-campo'));
        const idx = filas.findIndex(tr => tr.dataset.rowId === id);
        const idxDestino = idx + direccion;
        if (idxDestino < 0 || idxDestino >= filas.length) return;

        const [filaActual, avActual] = parDeFila(id);
        const [filaDestino, avDestino] = parDeFila(filas[idxDestino].dataset.rowId);

        if (direccion < 0) {
            tbody.insertBefore(filaActual, filaDestino);
            tbody.insertBefore(avActual, filaDestino);
        } else {
            const referencia = avDestino.nextSibling;
            tbody.insertBefore(filaActual, referencia);
            tbody.insertBefore(avActual, referencia);
        }
        TF_renumerarFilas();
    };

    window.TF_toggleAvanzado = function (id) {
        const fila = document.querySelector(`.tf-fila-avanzada[data-row-id="${id}"]`);
        if (fila) fila.classList.toggle('d-none');
    };

    function actualizarVisibilidadValorFijo(tr) {
        const origen = tr.querySelector('.tf-c-origen').value;
        const input = tr.querySelector('.tf-c-valorfijo');
        input.disabled = origen !== 'texto_fijo';
        if (origen !== 'texto_fijo') input.value = '';
    }

    function mapeoToTexto(mapeo) {
        if (!mapeo || typeof mapeo !== 'object') return '';
        return Object.entries(mapeo).map(([k, v]) => `${k}=${v}`).join('\n');
    }

    function escHtml(v) {
        return String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    function limpiarTablaCampos() {
        document.getElementById('tf-campos-tbody').innerHTML = '';
    }

    function toggleTipoArchivo() {
        const tipo = document.getElementById('tf-tipo-archivo').value;
        document.querySelectorAll('.tf-solo-xlsx').forEach(el => el.classList.toggle('d-none', tipo !== 'xlsx'));
        document.querySelectorAll('.tf-solo-delimitado').forEach(el => el.classList.toggle('d-none', tipo !== 'csv' && tipo !== 'txt_delimitado'));
    }

    window.TF_abrirModalCrear = function () {
        const form = document.getElementById('tfForm');
        form.reset();
        form.action = window.TF_STORE_URL;
        document.getElementById('tf-id').value = '';
        document.getElementById('tfModalTitle').innerHTML = '<i class="bi bi-plus-circle"></i> Nuevo formato';
        document.getElementById('tf-solo-lectura-aviso').classList.add('d-none');
        setSoloLectura(false);
        limpiarTablaCampos();
        TF_agregarFilaCampo();
        toggleTipoArchivo();
        new bootstrap.Modal(document.getElementById('tfModal')).show();
    };

    window.TF_abrirModalEditar = function (f) {
        const form = document.getElementById('tfForm');
        form.reset();
        form.action = window.TF_UPDATE_URL;
        document.getElementById('tf-id').value = f.id;
        document.getElementById('tf-nombre').value = f.nombre || '';
        document.getElementById('tf-banco').value = f.id_banco || '';
        document.getElementById('tf-estado').value = f.estado || 'activo';
        document.getElementById('tf-descripcion').value = f.descripcion || '';
        document.getElementById('tf-tipo-archivo').value = f.tipo_archivo || 'xlsx';
        document.getElementById('tf-nombre-hoja').value = f.nombre_hoja || '';
        document.getElementById('tf-delimitador').value = f.delimitador || '';
        document.getElementById('tf-encabezado').checked = f.incluye_encabezado !== false;
        document.getElementById('tfModalTitle').innerHTML = '<i class="bi bi-pencil"></i> Editar formato';

        const esClasePhp = !!f.clase_formatter;
        document.getElementById('tf-solo-lectura-aviso').classList.toggle('d-none', !esClasePhp);
        document.getElementById('tf-clase-nombre').textContent = f.clase_formatter || '';
        setSoloLectura(esClasePhp);

        limpiarTablaCampos();
        (f.campos && f.campos.length ? f.campos : [{}]).forEach(c => TF_agregarFilaCampo(c));
        toggleTipoArchivo();
        new bootstrap.Modal(document.getElementById('tfModal')).show();
    };

    function setSoloLectura(soloLectura) {
        ['tf-tipo-archivo', 'tf-nombre-hoja', 'tf-delimitador', 'tf-encabezado'].forEach(id => {
            document.getElementById(id).disabled = soloLectura;
        });
        document.getElementById('tf-campos-editor').classList.toggle('d-none', soloLectura);
    }

    document.addEventListener('DOMContentLoaded', () => {
        const selTipo = document.getElementById('tf-tipo-archivo');
        if (selTipo) selTipo.addEventListener('change', toggleTipoArchivo);

        const form = document.getElementById('tfForm');
        if (!form) return;
        form.addEventListener('submit', (e) => {
            if (!document.getElementById('tf-campos-editor').classList.contains('d-none')) {
                const tipoArchivo = document.getElementById('tf-tipo-archivo').value;
                const campos = [];
                document.querySelectorAll('.tf-fila-campo').forEach(tr => {
                    const avanzada = document.querySelector(`.tf-fila-avanzada[data-row-id="${tr.dataset.rowId}"]`);
                    const etiqueta = tr.querySelector('.tf-c-etiqueta').value.trim();
                    const origen = tr.querySelector('.tf-c-origen').value;
                    if (!etiqueta || !origen) return;
                    campos.push({
                        etiqueta,
                        origen_dato: origen,
                        valor_fijo: tr.querySelector('.tf-c-valorfijo').value.trim(),
                        tipo_dato: tr.querySelector('.tf-c-tipo').value,
                        mayusculas: tr.querySelector('.tf-c-mayus').checked,
                        quitar_tildes: tr.querySelector('.tf-c-tildes').checked,
                        max_caracteres: tr.querySelector('.tf-c-maxcar').value,
                        longitud_fija: tr.querySelector('.tf-c-long').value,
                        relleno_caracter: tr.querySelector('.tf-c-relleno').value,
                        alineacion: avanzada.querySelector('.tf-c-alineacion').value,
                        formato_numero: avanzada.querySelector('.tf-c-formatonum').value,
                        decimales: avanzada.querySelector('.tf-c-decimales').value,
                        solo_alfanumerico: avanzada.querySelector('.tf-c-alfanum').checked,
                        mapeo_valores: avanzada.querySelector('.tf-c-mapeo').value,
                    });
                });

                if (!campos.length) {
                    e.preventDefault();
                    alert('Agregue al menos una columna.');
                    return;
                }
                if (tipoArchivo === 'txt_ancho_fijo') {
                    const sinLongitud = campos.filter(c => !c.longitud_fija).map(c => c.etiqueta);
                    if (sinLongitud.length) {
                        e.preventDefault();
                        alert('El tipo de archivo "TXT ancho fijo" requiere longitud fija en todas las columnas, incluso las que queden en blanco (se rellenan con espacios pero igual ocupan su posición). Falta en: ' + sinLongitud.join(', '));
                        return;
                    }
                }
                document.getElementById('tf-campos-json').value = JSON.stringify(campos);
            }
        });
    });
})();
