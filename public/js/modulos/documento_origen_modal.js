/**
 * Modal de solo lectura del documento que originó un asiento.
 *
 * Lo usan Mayores y el mayor auxiliar de Estados Financieros: al pulsar el número en la columna
 * "Documento Ref." se abre aquí la factura, compra, egreso, etc. La vista que lo incluye debe
 * definir window.DOCORIGEN_URL con la ruta de su propio endpoint, para que el permiso que se
 * valide sea el del módulo desde el que se abre.
 */
(function () {
    'use strict';

    let modalInstance = null;

    const escapar = (valor) => String(valor ?? '').replace(/[&<>"']/g, (c) => (
        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
    ));

    function campo(label, valor) {
        if (valor === null || valor === undefined || valor === '') return '';
        return `<div class="col-6 col-md-3">
                    <div class="small text-muted">${escapar(label)}</div>
                    <div class="fw-semibold">${escapar(valor)}</div>
                </div>`;
    }

    function mostrarError(mensaje) {
        document.getElementById('loaderDocumentoOrigen').classList.add('d-none');
        document.getElementById('cuerpoDocumentoOrigen').classList.add('d-none');
        const err = document.getElementById('errorDocumentoOrigen');
        err.textContent = mensaje;
        err.classList.remove('d-none');
    }

    function pintar(doc) {
        document.getElementById('tituloDocumentoOrigen').innerHTML =
            `<i class="bi bi-file-earmark-text text-primary me-2"></i> ${escapar(doc.etiqueta)}: ${escapar(doc.numero)}`;

        const tercero = doc.tercero
            ? doc.tercero + (doc.tercero_identificacion ? ` (${doc.tercero_identificacion})` : '')
            : '';

        document.getElementById('cabeceraDocumentoOrigen').innerHTML =
            campo('Número', doc.numero) + campo('Fecha', doc.fecha) +
            campo('Tercero', tercero) + campo('Estado', doc.estado);

        document.getElementById('theadDocumentoOrigen').innerHTML = doc.columnas
            .map(c => `<th class="${c.numerica ? 'text-end' : ''}">${escapar(c.label)}</th>`).join('');

        const tbody = document.getElementById('tbodyDocumentoOrigen');
        if (!doc.lineas.length) {
            tbody.innerHTML = `<tr><td colspan="${doc.columnas.length}" class="text-center text-muted py-3">
                                   Este documento no tiene líneas registradas.</td></tr>`;
        } else {
            tbody.innerHTML = doc.lineas.map(fila =>
                '<tr>' + fila.map((valor, i) =>
                    `<td class="${doc.columnas[i] && doc.columnas[i].numerica ? 'text-end' : ''}">${escapar(valor)}</td>`
                ).join('') + '</tr>'
            ).join('');
        }

        document.getElementById('totalesDocumentoOrigen').innerHTML = doc.totales.map((t, i) => {
            const ultimo = i === doc.totales.length - 1;
            return `<div class="d-flex justify-content-between ${ultimo ? 'fw-bold border-top pt-1' : ''}">
                        <span class="text-muted me-4">${escapar(t.label)}</span>
                        <span>${escapar(t.valor)}</span>
                    </div>`;
        }).join('');

        document.getElementById('observacionesDocumentoOrigen').innerHTML = doc.observaciones
            ? `<span class="fw-semibold">Observaciones:</span> ${escapar(doc.observaciones)}` : '';

        document.getElementById('loaderDocumentoOrigen').classList.add('d-none');
        document.getElementById('errorDocumentoOrigen').classList.add('d-none');
        document.getElementById('cuerpoDocumentoOrigen').classList.remove('d-none');
    }

    window.DOCORIGEN_abrirModal = async function (modulo, idDocumento) {
        if (!modulo || !idDocumento) return;

        if (!modalInstance) {
            const el = document.getElementById('modalDocumentoOrigen');
            if (!el) return;
            modalInstance = new bootstrap.Modal(el);
        }

        document.getElementById('loaderDocumentoOrigen').classList.remove('d-none');
        document.getElementById('errorDocumentoOrigen').classList.add('d-none');
        document.getElementById('cuerpoDocumentoOrigen').classList.add('d-none');
        document.getElementById('tituloDocumentoOrigen').innerHTML =
            '<i class="bi bi-file-earmark-text text-primary me-2"></i> Documento';
        modalInstance.show();

        try {
            const url = `${window.DOCORIGEN_URL}?modulo=${encodeURIComponent(modulo)}&id=${encodeURIComponent(idDocumento)}`;
            const resp = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const contentType = resp.headers.get('content-type') || '';
            if (!contentType.includes('application/json')) {
                mostrarError(`No se pudo cargar el documento (HTTP ${resp.status}).`);
                return;
            }
            const json = await resp.json();
            if (json.success) {
                pintar(json.data);
            } else {
                mostrarError(json.error || 'No se pudo cargar el documento.');
            }
        } catch (e) {
            console.error(e);
            mostrarError('Error de red o servidor al cargar el documento.');
        }
    };
})();
