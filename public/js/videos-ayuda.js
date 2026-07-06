/**
 * Gestión de videos de ayuda (superadmin).
 * Subida con barra de progreso (XHR), edición y eliminación vía AJAX.
 * Requiere: window.VA_BASE y Bootstrap bundle (modales).
 */
(function () {
    'use strict';

    var base = (window.VA_BASE || '').replace(/\/$/, '');
    var maxUpload = window.VA_MAX_UPLOAD || 0;                 // límite efectivo del servidor (bytes)
    var maxUploadTxt = window.VA_MAX_UPLOAD_TXT || '';
    var modalEl = document.getElementById('modalVideo');
    if (!modalEl) return;
    var modal = new bootstrap.Modal(modalEl);
    var form = document.getElementById('form-video');

    var el = {
        id: document.getElementById('v-id'),
        titulo: document.getElementById('v-titulo'),
        categoria: document.getElementById('v-categoria'),
        etiquetas: document.getElementById('v-etiquetas'),
        descripcion: document.getElementById('v-descripcion'),
        orden: document.getElementById('v-orden'),
        estado: document.getElementById('v-estado'),
        archivo: document.getElementById('v-archivo'),
        archivoReq: document.getElementById('v-archivo-req'),
        archivoHint: document.getElementById('v-archivo-hint'),
        msg: document.getElementById('v-msg'),
        progressWrap: document.getElementById('v-progress-wrap'),
        progress: document.getElementById('v-progress'),
        tituloModal: document.getElementById('modalVideoTitulo'),
        btnGuardar: document.getElementById('v-btn-guardar'),
        btnEliminar: document.getElementById('v-btn-eliminar')
    };

    function mostrarMsg(tipo, texto) {
        el.msg.className = 'alert alert-' + (tipo === 'error' ? 'danger' : 'success') + ' py-2 mb-3';
        el.msg.textContent = texto;
        el.msg.classList.remove('d-none');
    }
    function ocultarMsg() { el.msg.classList.add('d-none'); }

    function resetProgress() {
        el.progressWrap.classList.add('d-none');
        el.progress.style.width = '0%';
        el.progress.textContent = '0%';
    }

    function abrirNuevo() {
        form.reset();
        el.id.value = '';
        el.tituloModal.innerHTML = '<i class="bi bi-cloud-upload me-1"></i>Subir video';
        el.archivo.setAttribute('required', 'required');
        el.archivoReq.classList.remove('d-none');
        el.archivoHint.textContent = 'MP4, WebM u OGG.' + (maxUploadTxt ? ' Máx. ' + maxUploadTxt + '.' : '');
        el.btnEliminar.classList.add('d-none');
        ocultarMsg();
        resetProgress();
        modal.show();
    }

    function abrirEditar(tr) {
        var d = tr.dataset;
        form.reset();
        el.id.value = d.id || '';
        el.titulo.value = d.titulo || '';
        el.categoria.value = d.categoria || '';
        el.etiquetas.value = d.etiquetas || '';
        el.descripcion.value = d.descripcion || '';
        el.orden.value = d.orden || '0';
        el.estado.value = d.estado || 'activo';
        el.tituloModal.innerHTML = '<i class="bi bi-pencil me-1"></i>Editar video';
        el.archivo.removeAttribute('required');
        el.archivoReq.classList.add('d-none');
        el.archivoHint.textContent = d.archivo
            ? 'Archivo actual: ' + d.archivo + '. Suba uno nuevo solo si desea reemplazarlo.'
            : 'Suba un archivo solo si desea reemplazar el actual.';
        el.btnEliminar.classList.remove('d-none');
        ocultarMsg();
        resetProgress();
        modal.show();
    }

    function recargar() {
        window.location.reload();
    }

    // Enviar (crear/editar) con progreso
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        ocultarMsg();
        if (!el.titulo.value.trim()) { mostrarMsg('error', 'El título es obligatorio.'); return; }

        // Pre-validación de tamaño (evita subir para que el servidor lo rechace).
        var f = el.archivo.files && el.archivo.files[0];
        if (f && maxUpload > 0 && f.size > maxUpload) {
            mostrarMsg('error', 'El video pesa ' + (Math.round(f.size / 1048576 * 10) / 10) + ' MB y supera el máximo permitido (' + maxUploadTxt + '). Elija un archivo más pequeño o pida ampliar el límite del servidor.');
            return;
        }

        var esNuevo = !el.id.value;
        var url = base + '/videos-ayuda/' + (esNuevo ? 'store' : 'update');
        var fd = new FormData(form);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', url, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        el.btnGuardar.disabled = true;
        var txtOrig = el.btnGuardar.innerHTML;
        el.btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Subiendo...';

        if (xhr.upload) {
            el.progressWrap.classList.remove('d-none');
            xhr.upload.onprogress = function (ev) {
                if (ev.lengthComputable) {
                    var pct = Math.round((ev.loaded / ev.total) * 100);
                    el.progress.style.width = pct + '%';
                    el.progress.textContent = pct + '%';
                }
            };
        }

        xhr.onload = function () {
            el.btnGuardar.disabled = false;
            el.btnGuardar.innerHTML = txtOrig;
            var res;
            try { res = JSON.parse(xhr.responseText); } catch (err) { res = null; }
            if (xhr.status >= 200 && xhr.status < 300 && res && res.ok) {
                mostrarMsg('ok', res.msg || 'Guardado.');
                setTimeout(recargar, 800);
            } else {
                resetProgress();
                mostrarMsg('error', (res && res.error) ? res.error : 'Error al guardar (código ' + xhr.status + ').');
            }
        };
        xhr.onerror = function () {
            el.btnGuardar.disabled = false;
            el.btnGuardar.innerHTML = txtOrig;
            resetProgress();
            mostrarMsg('error', 'Error de conexión. Intente de nuevo.');
        };
        xhr.send(fd);
    });

    function eliminar(id) {
        if (!id) return;
        if (!confirm('¿Eliminar este video de ayuda? Esta acción no se puede deshacer.')) return;
        var fd = new FormData();
        fd.append('id', id);
        fetch(base + '/videos-ayuda/delete', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) { return r.json(); })
          .then(function (res) {
              if (res && res.ok) { recargar(); }
              else { alert((res && res.error) ? res.error : 'No se pudo eliminar.'); }
          })
          .catch(function () { alert('Error de conexión.'); });
    }

    // ── Modal: quién ha visto ────────────────────────────────────────────
    var modalVistasEl = document.getElementById('modalVistas');
    var modalVistas = modalVistasEl ? new bootstrap.Modal(modalVistasEl) : null;

    function esc2(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function verVistas(tr) {
        if (!modalVistas) return;
        document.getElementById('mv-titulo').textContent = tr.dataset.titulo || '';
        var body = document.getElementById('mv-body');
        body.innerHTML = '<div class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm"></span> Cargando...</div>';
        modalVistas.show();
        fetch(base + '/videos-ayuda/vistas-detalle?id=' + encodeURIComponent(tr.dataset.id), {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) { return r.json(); })
          .then(function (res) {
              if (!res || !res.ok) { body.innerHTML = '<div class="text-danger text-center py-4">No se pudo cargar el detalle.</div>'; return; }
              if (!res.vistas.length) { body.innerHTML = '<div class="text-muted text-center py-4">Todavía nadie ha visto este video.</div>'; return; }
              var html = '<table class="table table-sm table-hover mb-0"><thead class="table-light"><tr>' +
                  '<th class="ps-3">Usuario</th><th class="text-center">Reproducciones</th><th class="text-end pe-3">Última vez</th></tr></thead><tbody>';
              res.vistas.forEach(function (v) {
                  html += '<tr><td class="ps-3">' + esc2(v.usuario) + '</td>' +
                      '<td class="text-center">' + (v.reproducciones || 0) + '</td>' +
                      '<td class="text-end pe-3 small text-muted">' + esc2(v.ultima) + '</td></tr>';
              });
              html += '</tbody></table>';
              body.innerHTML = html;
          })
          .catch(function () { body.innerHTML = '<div class="text-danger text-center py-4">Error de conexión.</div>'; });
    }

    // Delegación de eventos en la tabla
    document.getElementById('btn-nuevo-video').addEventListener('click', abrirNuevo);

    Array.prototype.forEach.call(document.querySelectorAll('.vg-row'), function (tr) {
        tr.querySelector('.vg-editar').addEventListener('click', function (e) {
            e.stopPropagation();
            abrirEditar(tr);
        });
        tr.querySelector('.vg-eliminar').addEventListener('click', function (e) {
            e.stopPropagation();
            eliminar(tr.dataset.id);
        });
        var verBtn = tr.querySelector('.vg-ver-vistas');
        if (verBtn) {
            verBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                verVistas(tr);
            });
        }
        tr.addEventListener('click', function () { abrirEditar(tr); });
    });

    // Botón eliminar dentro del modal de edición
    el.btnEliminar.addEventListener('click', function () {
        eliminar(el.id.value);
    });
})();
