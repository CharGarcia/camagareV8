(function () {
    'use strict';

    const URL_BASE = window.IF_URL_BASE;
    let ifgCuentasSeleccionadas = []; // [{id, codigo, nombre}]
    let ifgBuscarTimeout = null;

    function postForm(url, data) {
        const body = new URLSearchParams();
        Object.keys(data).forEach((k) => body.append(k, data[k]));
        return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body })
            .then((r) => r.json());
    }

    function mostrarError(mensaje) {
        alert(mensaje || 'Ocurrió un error.');
    }

    // ════════════════════════════════════════════════════════════════
    // TABLERO
    // ════════════════════════════════════════════════════════════════

    function formatoValor(valor, unidad) {
        if (valor === null || valor === undefined) return 'N/D';
        if (unidad === 'porcentaje') return (valor * 100).toFixed(2) + '%';
        if (unidad === 'dias') return valor.toFixed(1) + ' días';
        if (unidad === 'monto') return '$' + valor.toFixed(2);
        return valor.toFixed(2);
    }

    const CATEGORIAS = {
        liquidez: { titulo: 'Liquidez', icono: 'bi-droplet-half', color: 'primary' },
        endeudamiento: { titulo: 'Endeudamiento', icono: 'bi-bar-chart-steps', color: 'warning' },
        rentabilidad: { titulo: 'Rentabilidad', icono: 'bi-graph-up-arrow', color: 'success' },
        actividad: { titulo: 'Actividad', icono: 'bi-arrow-repeat', color: 'info' },
    };

    window.IF_recalcular = function () {
        const fi = document.getElementById('if-fecha-inicio').value;
        const ff = document.getElementById('if-fecha-fin').value;
        const params = new URLSearchParams({ fecha_inicio: fi, fecha_fin: ff });

        fetch(URL_BASE + '/calcularAjax?' + params.toString())
            .then((r) => r.json())
            .then((res) => {
                if (!res.ok) { mostrarError('No se pudieron calcular los índices.'); return; }
                renderTablero(res.data);
            })
            .catch(() => mostrarError('Error de conexión al calcular los índices.'));
    };

    window.IF_imprimirPdf = function () {
        const fi = document.getElementById('if-fecha-inicio').value;
        const ff = document.getElementById('if-fecha-fin').value;
        const params = new URLSearchParams({ fecha_inicio: fi, fecha_fin: ff });
        window.open(URL_BASE + '/pdf?' + params.toString(), '_blank');
    };

    function renderTablero(data) {
        const cont = document.getElementById('if-tablero-contenido');
        let html = '';
        Object.keys(CATEGORIAS).forEach((catKey) => {
            const cat = CATEGORIAS[catKey];
            const items = data[catKey] || [];
            html += '<div class="col-12"><h6 class="fw-bold text-' + cat.color + '"><i class="bi ' + cat.icono + ' me-1"></i>' + cat.titulo + '</h6><div class="row g-3 mb-2">';
            if (items.length === 0) {
                html += '<div class="col-12 text-muted small">Sin índices en esta categoría.</div>';
            }
            items.forEach((ind) => {
                html += '<div class="col-md-3 col-sm-6"><div class="card border-0 shadow-sm h-100"><div class="card-body p-3">'
                    + '<div class="small text-muted text-truncate" title="' + escapeHtml(ind.descripcion || '') + '">' + escapeHtml(ind.nombre) + '</div>'
                    + '<div class="fs-4 fw-bold text-' + cat.color + '">' + formatoValor(ind.valor, ind.unidad) + '</div>'
                    + (ind.interpretacion ? '<div class="small text-muted">' + escapeHtml(ind.interpretacion) + '</div>' : '')
                    + '</div></div></div>';
            });
            html += '</div></div>';
        });
        cont.innerHTML = html;
    }

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.innerText = str;
        return d.innerHTML;
    }

    // ════════════════════════════════════════════════════════════════
    // NIVEL 1 — Clasificación de cuentas
    // ════════════════════════════════════════════════════════════════

    const NOMBRES_GRUPO_NIVEL1 = {
        ACTIVO_CORRIENTE: 'Activo Corriente',
        ACTIVO_NO_CORRIENTE: 'Activo No Corriente',
        PASIVO_CORRIENTE: 'Pasivo Corriente',
        PASIVO_NO_CORRIENTE: 'Pasivo No Corriente',
    };

    function selectClasificacionHtml(idCuenta, grupoSeleccionado) {
        let html = '<select class="form-select form-select-sm" onchange="window.IF_guardarClasificacion(' + idCuenta + ', this)">';
        Object.keys(NOMBRES_GRUPO_NIVEL1).forEach((g) => {
            html += '<option value="' + g + '"' + (g === grupoSeleccionado ? ' selected' : '') + '>' + NOMBRES_GRUPO_NIVEL1[g] + '</option>';
        });
        html += '</select>';
        return html;
    }

    window.IF_guardarClasificacion = function (idCuenta, selectEl) {
        const grupo = selectEl.value;
        if (!grupo) return;

        const fila = selectEl.closest('tr');
        const vieneDeSinClasificar = fila && fila.closest('#if-tbody-sin-clasificar') !== null;

        postForm(URL_BASE + '/guardarClasificacionAjax', { id_cuenta: idCuenta, grupo: grupo })
            .then((res) => {
                if (!res.ok) { mostrarError(res.mensaje); return; }
                if (vieneDeSinClasificar) {
                    moverFilaAClasificadas(fila, idCuenta, grupo);
                }
                // Si ya estaba en "clasificadas", el <select> ya refleja el nuevo valor: no hay más que actualizar.
            })
            .catch(() => mostrarError('Error de conexión al guardar la clasificación.'));
    };

    function moverFilaAClasificadas(fila, idCuenta, grupo) {
        const codigo = fila.children[0].innerText.trim();
        const nombre = fila.children[1].innerText.trim();
        fila.remove();

        const tbodySinClasificar = document.getElementById('if-tbody-sin-clasificar');
        const pendientes = tbodySinClasificar.querySelectorAll('.if-fila-cuenta').length;
        if (pendientes === 0) {
            tbodySinClasificar.innerHTML = '<tr class="if-fila-vacia"><td colspan="3" class="text-center py-4 text-muted">Todas las cuentas de Activo/Pasivo están clasificadas.</td></tr>';
        }

        const badge = document.getElementById('if-badge-sin-clasificar');
        if (badge) {
            if (pendientes === 0) { badge.classList.add('d-none'); }
            badge.innerText = pendientes;
        }

        const tbodyClasificadas = document.getElementById('if-tbody-clasificadas');
        const filaVacia = tbodyClasificadas.querySelector('.if-fila-vacia');
        if (filaVacia) filaVacia.remove();

        const nuevaFila = document.createElement('tr');
        nuevaFila.className = 'if-fila-cuenta';
        nuevaFila.dataset.idCuenta = idCuenta;
        nuevaFila.innerHTML = '<td class="ps-3">' + escapeHtml(codigo) + '</td><td>' + escapeHtml(nombre) + '</td><td>' + selectClasificacionHtml(idCuenta, grupo) + '</td>';
        tbodyClasificadas.appendChild(nuevaFila);
    }

    // ════════════════════════════════════════════════════════════════
    // NIVEL 2 — Grupos personalizados
    // ════════════════════════════════════════════════════════════════

    window.IF_abrirModalGrupo = function (id) {
        ifgCuentasSeleccionadas = [];
        document.getElementById('ifg-id').value = id || '';
        document.getElementById('ifg-codigo').value = '';
        document.getElementById('ifg-nombre').value = '';
        document.getElementById('ifg-descripcion').value = '';
        document.getElementById('ifg-buscar-cuenta').value = '';
        document.getElementById('ifg-btn-eliminar').classList.toggle('d-none', !id);
        document.getElementById('ifGrupoModalTitulo').innerText = id ? 'Editar Grupo' : 'Nuevo Grupo';

        const modal = new bootstrap.Modal(document.getElementById('modalIFGrupo'));

        if (id) {
            fetch(URL_BASE + '/getGrupoAjax?id=' + id)
                .then((r) => r.json())
                .then((res) => {
                    if (res.ok) {
                        ifgCuentasSeleccionadas = res.data.map((c) => ({ id: c.id_cuenta, codigo: c.codigo, nombre: c.nombre }));
                        renderChipsGrupo();
                    }
                });
            // Datos del grupo (código/nombre/descripción) vienen de la fila clickeada en la tabla.
            const fila = document.querySelector('#if-tbody-grupos tr[data-id-grupo="' + id + '"]');
            if (fila) {
                const celdas = fila.querySelectorAll('td');
                document.getElementById('ifg-codigo').value = celdas[0].innerText.trim();
                document.getElementById('ifg-nombre').value = celdas[1].innerText.trim();
                document.getElementById('ifg-descripcion').value = celdas[3].innerText.trim();
            }
        } else {
            renderChipsGrupo();
        }

        modal.show();
    };

    document.addEventListener('input', function (e) {
        if (e.target && e.target.id === 'ifg-buscar-cuenta') {
            clearTimeout(ifgBuscarTimeout);
            const q = e.target.value.trim();
            const dropdown = document.getElementById('ifg-dropdown-cuentas');
            if (q.length < 2) { dropdown.style.display = 'none'; return; }
            ifgBuscarTimeout = setTimeout(() => {
                fetch(URL_BASE + '/buscarCuentasAjax?q=' + encodeURIComponent(q))
                    .then((r) => r.json())
                    .then((res) => {
                        if (!res.ok) return;
                        dropdown.innerHTML = '';
                        res.data.forEach((c) => {
                            const item = document.createElement('button');
                            item.type = 'button';
                            item.className = 'list-group-item list-group-item-action';
                            item.innerText = c.codigo + ' - ' + c.nombre;
                            item.onclick = () => {
                                if (!ifgCuentasSeleccionadas.some((s) => s.id === c.id)) {
                                    ifgCuentasSeleccionadas.push({ id: c.id, codigo: c.codigo, nombre: c.nombre });
                                    renderChipsGrupo();
                                }
                                dropdown.style.display = 'none';
                                document.getElementById('ifg-buscar-cuenta').value = '';
                            };
                            dropdown.appendChild(item);
                        });
                        dropdown.style.display = res.data.length ? 'block' : 'none';
                    });
            }, 250);
        }
    });

    function renderChipsGrupo() {
        const cont = document.getElementById('ifg-chips-cuentas');
        cont.innerHTML = '';
        ifgCuentasSeleccionadas.forEach((c) => {
            const chip = document.createElement('span');
            chip.className = 'badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 d-flex align-items-center gap-1';
            chip.innerText = c.codigo + ' - ' + c.nombre;
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn-close btn-close-sm ms-1';
            btn.style.fontSize = '0.6rem';
            btn.onclick = () => {
                ifgCuentasSeleccionadas = ifgCuentasSeleccionadas.filter((s) => s.id !== c.id);
                renderChipsGrupo();
            };
            chip.appendChild(btn);
            cont.appendChild(chip);
        });
    }

    window.IF_guardarGrupo = function () {
        const data = {
            id: document.getElementById('ifg-id').value,
            codigo: document.getElementById('ifg-codigo').value.trim(),
            nombre: document.getElementById('ifg-nombre').value.trim(),
            descripcion: document.getElementById('ifg-descripcion').value.trim(),
            id_cuentas: JSON.stringify(ifgCuentasSeleccionadas.map((c) => c.id)),
        };
        postForm(URL_BASE + '/guardarGrupoAjax', data).then((res) => {
            if (!res.ok) { mostrarError(res.mensaje); return; }
            location.reload();
        }).catch(() => mostrarError('Error de conexión al guardar el grupo.'));
    };

    window.IF_eliminarGrupo = function () {
        const id = document.getElementById('ifg-id').value;
        if (!id || !confirm('¿Eliminar este grupo?')) return;
        postForm(URL_BASE + '/eliminarGrupoAjax', { id: id }).then((res) => {
            if (!res.ok) { mostrarError(res.mensaje); return; }
            location.reload();
        }).catch(() => mostrarError('Error de conexión al eliminar el grupo.'));
    };

    // ════════════════════════════════════════════════════════════════
    // ÍNDICES PERSONALIZADOS — constructor de fórmula
    // ════════════════════════════════════════════════════════════════

    function opcionesTermino() {
        let html = '<option value="">-- elegir --</option>';
        html += '<optgroup label="Grupos estándar">';
        (window.IF_GRUPOS_ESTANDAR || []).forEach((g) => { html += '<option value="grupo:' + g.codigo + '">' + g.nombre + '</option>'; });
        html += '</optgroup>';
        if ((window.IF_GRUPOS_PERSONALIZADOS || []).length) {
            html += '<optgroup label="Grupos personalizados">';
            window.IF_GRUPOS_PERSONALIZADOS.forEach((g) => { html += '<option value="grupo:' + g.codigo + '">' + g.nombre + '</option>'; });
            html += '</optgroup>';
        }
        html += '<optgroup label="Datos del sistema">';
        (window.IF_FUENTES || []).forEach((f) => { html += '<option value="fuente:' + f.codigo + '">' + f.nombre + '</option>'; });
        html += '</optgroup>';
        html += '<option value="constante:">Valor constante...</option>';
        return html;
    }

    function crearFilaTermino(destino, esPrimero, valorInicial) {
        const fila = document.createElement('div');
        fila.className = 'd-flex align-items-center gap-2 if-termino-row';

        let signoHtml = '';
        if (!esPrimero) {
            signoHtml = '<select class="form-select form-select-sm if-termino-signo" style="width:70px;">'
                + '<option value="+"' + (valorInicial && valorInicial.sign === '-' ? '' : ' selected') + '>+</option>'
                + '<option value="-"' + (valorInicial && valorInicial.sign === '-' ? ' selected' : '') + '>-</option>'
                + '</select>';
        } else {
            signoHtml = '<span class="text-muted" style="width:70px; display:inline-block;">&nbsp;</span>';
        }

        const selectHtml = '<select class="form-select form-select-sm if-termino-tipo">' + opcionesTermino() + '</select>';
        const constInputHtml = '<input type="number" step="any" class="form-control form-control-sm if-termino-const d-none" placeholder="Valor">';
        const btnQuitarHtml = '<button type="button" class="btn btn-outline-danger btn-sm if-termino-quitar"><i class="bi bi-x-lg"></i></button>';

        fila.innerHTML = signoHtml + selectHtml + constInputHtml + btnQuitarHtml;

        const selectTipo = fila.querySelector('.if-termino-tipo');
        const inputConst = fila.querySelector('.if-termino-const');

        selectTipo.addEventListener('change', function () {
            inputConst.classList.toggle('d-none', this.value !== 'constante:');
        });

        fila.querySelector('.if-termino-quitar').addEventListener('click', function () {
            fila.remove();
        });

        if (valorInicial) {
            if (valorInicial.tipo === 'constante') {
                selectTipo.value = 'constante:';
                inputConst.classList.remove('d-none');
                inputConst.value = valorInicial.valor;
            } else {
                selectTipo.value = valorInicial.tipo + ':' + valorInicial.valor;
            }
        }

        return fila;
    }

    window.IF_agregarTermino = function (destino, valorInicial) {
        const cont = document.getElementById(destino === 'numerador' ? 'ifi-numerador-terminos' : 'ifi-denominador-terminos');
        const esPrimero = cont.children.length === 0;
        cont.appendChild(crearFilaTermino(destino, esPrimero, valorInicial));
    };

    window.IF_toggleDenominador = function (activo) {
        document.getElementById('ifi-bloque-denominador').classList.toggle('d-none', !activo);
        if (activo && document.getElementById('ifi-denominador-terminos').children.length === 0) {
            window.IF_agregarTermino('denominador');
        }
    };

    function leerTerminos(destino) {
        const cont = document.getElementById(destino === 'numerador' ? 'ifi-numerador-terminos' : 'ifi-denominador-terminos');
        const terminos = [];
        Array.from(cont.children).forEach((fila) => {
            const signoEl = fila.querySelector('.if-termino-signo');
            const sign = signoEl ? signoEl.value : '+';
            const tipoValor = fila.querySelector('.if-termino-tipo').value;
            if (!tipoValor) return;
            if (tipoValor === 'constante:') {
                const val = parseFloat(fila.querySelector('.if-termino-const').value || '0');
                terminos.push({ sign: sign, tipo: 'const', valor: val });
            } else {
                const [tipo, valor] = tipoValor.split(':');
                terminos.push({ sign: sign, tipo: tipo, valor: valor });
            }
        });
        return terminos;
    }

    function construirNodo(termino) {
        if (termino.tipo === 'const') return { const: termino.valor };
        if (termino.tipo === 'grupo') return { grupo: termino.valor };
        return { fuente: termino.valor };
    }

    function construirCadena(terminos) {
        if (terminos.length === 0) return null;
        let acc = construirNodo(terminos[0]);
        for (let i = 1; i < terminos.length; i++) {
            acc = { op: terminos[i].sign, left: acc, right: construirNodo(terminos[i]) };
        }
        return acc;
    }

    function aplanarCadena(nodo, destinoTerminos, signo) {
        if (nodo.op === '+' || nodo.op === '-') {
            aplanarCadena(nodo.left, destinoTerminos, '+');
            aplanarCadena(nodo.right, destinoTerminos, nodo.op);
            return;
        }
        let tipo = 'fuente', valor = null;
        if ('const' in nodo) { tipo = 'constante'; valor = nodo.const; }
        else if ('grupo' in nodo) { tipo = 'grupo'; valor = nodo.grupo; }
        else if ('fuente' in nodo) { tipo = 'fuente'; valor = nodo.fuente; }
        destinoTerminos.push({ sign: signo, tipo: tipo, valor: valor });
    }

    window.IF_abrirModalIndice = function (id) {
        document.getElementById('ifi-id').value = id || '';
        document.getElementById('ifi-codigo').value = '';
        document.getElementById('ifi-nombre').value = '';
        document.getElementById('ifi-categoria').value = 'liquidez';
        document.getElementById('ifi-unidad').value = 'razon';
        document.getElementById('ifi-descripcion').value = '';
        document.getElementById('ifi-numerador-terminos').innerHTML = '';
        document.getElementById('ifi-denominador-terminos').innerHTML = '';
        document.getElementById('ifi-tiene-denominador').checked = false;
        document.getElementById('ifi-bloque-denominador').classList.add('d-none');
        document.getElementById('ifi-btn-eliminar').classList.toggle('d-none', !id);
        document.getElementById('ifIndiceModalTitulo').innerText = id ? 'Editar Índice' : 'Nuevo Índice Personalizado';

        if (id) {
            const ind = (window.IF_INDICES_DATA || []).find((i) => i.id === id);
            if (ind) {
                document.getElementById('ifi-codigo').value = ind.codigo;
                document.getElementById('ifi-nombre').value = ind.nombre;
                document.getElementById('ifi-categoria').value = ind.categoria;
                document.getElementById('ifi-unidad').value = ind.unidad;
                document.getElementById('ifi-descripcion').value = ind.descripcion || '';

                const formula = typeof ind.formula === 'string' ? JSON.parse(ind.formula) : ind.formula;
                let numeradorNodo = formula, denominadorNodo = null;
                if (formula.op === '/') {
                    numeradorNodo = formula.left;
                    denominadorNodo = formula.right;
                }
                const numeradorTerminos = [];
                aplanarCadena(numeradorNodo, numeradorTerminos, '+');
                numeradorTerminos.forEach((t) => window.IF_agregarTermino('numerador', t));

                if (denominadorNodo) {
                    document.getElementById('ifi-tiene-denominador').checked = true;
                    document.getElementById('ifi-bloque-denominador').classList.remove('d-none');
                    const denominadorTerminos = [];
                    aplanarCadena(denominadorNodo, denominadorTerminos, '+');
                    denominadorTerminos.forEach((t) => window.IF_agregarTermino('denominador', t));
                }
            }
        } else {
            window.IF_agregarTermino('numerador');
        }

        new bootstrap.Modal(document.getElementById('modalIFIndice')).show();
    };

    window.IF_guardarIndice = function () {
        const numeradorTerminos = leerTerminos('numerador');
        if (numeradorTerminos.length === 0) { mostrarError('Agregue al menos un término al numerador.'); return; }

        let formula = construirCadena(numeradorTerminos);
        if (document.getElementById('ifi-tiene-denominador').checked) {
            const denominadorTerminos = leerTerminos('denominador');
            if (denominadorTerminos.length === 0) { mostrarError('Agregue al menos un término al denominador.'); return; }
            formula = { op: '/', left: formula, right: construirCadena(denominadorTerminos) };
        }

        const data = {
            id: document.getElementById('ifi-id').value,
            codigo: document.getElementById('ifi-codigo').value.trim(),
            nombre: document.getElementById('ifi-nombre').value.trim(),
            categoria: document.getElementById('ifi-categoria').value,
            unidad: document.getElementById('ifi-unidad').value,
            descripcion: document.getElementById('ifi-descripcion').value.trim(),
            activo: 'true',
            formula: JSON.stringify(formula),
        };

        postForm(URL_BASE + '/guardarIndiceAjax', data).then((res) => {
            if (!res.ok) { mostrarError(res.mensaje); return; }
            location.reload();
        }).catch(() => mostrarError('Error de conexión al guardar el índice.'));
    };

    window.IF_eliminarIndice = function () {
        const id = document.getElementById('ifi-id').value;
        if (!id || !confirm('¿Eliminar este índice?')) return;
        postForm(URL_BASE + '/eliminarIndiceAjax', { id: id }).then((res) => {
            if (!res.ok) { mostrarError(res.mensaje); return; }
            location.reload();
        }).catch(() => mostrarError('Error de conexión al eliminar el índice.'));
    };

    window.IF_cambiarActivoIndice = function (id, activo) {
        postForm(URL_BASE + '/cambiarActivoIndiceAjax', { id: id, activo: activo ? 'true' : 'false' }).then((res) => {
            if (!res.ok) mostrarError(res.mensaje);
        }).catch(() => mostrarError('Error de conexión al cambiar el estado.'));
    };
})();
