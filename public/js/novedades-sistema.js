/**
 * Gestión de Novedades del sistema (solo superadmin).
 * Listado con búsqueda / orden (CMG_initSort) / paginación AJAX, filas clicables
 * que abren el modal. Modal: barra de acciones (publicar/archivar) + pestaña
 * "Novedad" (formulario con Quill) + pestaña "Leída por" (detalle de lecturas).
 * Endpoints: ver NovedadesSistemaController.
 */
(function () {
    'use strict';

    var CFG = window.NV_CFG || { base: '', modulo: 'novedades_sistema', ordenCol: 'publicado_at', ordenDir: 'DESC' };
    var BASE = CFG.base;
    var currentSort = CFG.ordenCol;
    var currentDir  = CFG.ordenDir;
    var currentPage = 1;

    var tbody       = document.getElementById('nvTbody');
    var inputBuscar = document.getElementById('nvBuscar');
    var modalEl     = document.getElementById('nvModal');
    var form        = document.getElementById('nvForm');
    var quill = null;
    var lecturasCargadasPara = null;   // id cuya pestaña "Leída por" ya se cargó

    var ESTADO_LABEL = { borrador: 'Borrador', publicada: 'Publicada', archivada: 'Archivada' };

    function $(id) { return document.getElementById(id); }

    /** Fecha local de hoy + N días en formato YYYY-MM-DD (para input[type=date]). */
    function fechaMasDias(dias) {
        var d = new Date();
        d.setDate(d.getDate() + dias);
        var m = String(d.getMonth() + 1).padStart(2, '0');
        var day = String(d.getDate()).padStart(2, '0');
        return d.getFullYear() + '-' + m + '-' + day;
    }

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
        });
    }

    function getJson(url) {
        return fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); });
    }

    function postForm(url, data) {
        var body = data instanceof FormData ? new URLSearchParams(data) : new URLSearchParams(data || {});
        return fetch(url, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        }).then(function (r) { return r.json(); });
    }

    function aviso(icon, title, text) {
        if (window.Swal) return Swal.fire({ icon: icon, title: title, text: text || '' });
        alert(title + (text ? '\n' + text : ''));
    }

    function toast(icon, title) {
        if (window.Toast) Toast.fire({ icon: icon, title: title });
    }

    function confirmar(titulo, texto, btnTexto, color) {
        if (!window.Swal) return Promise.resolve({ isConfirmed: confirm(titulo) });
        return Swal.fire({
            title: titulo, text: texto, icon: 'question', showCancelButton: true,
            confirmButtonText: btnTexto, cancelButtonText: 'Cancelar', confirmButtonColor: color || '#0d6efd'
        });
    }

    // ── Listado: búsqueda, orden y paginación ───────────────────────────
    window.NV_fetchSearch = function (page) {
        page = page || 1;
        var b = inputBuscar ? inputBuscar.value.trim() : '';
        var url = BASE + '/novedades-sistema/gestion-search'
                + '?b=' + encodeURIComponent(b)
                + '&page=' + encodeURIComponent(page)
                + '&sort=' + encodeURIComponent(currentSort)
                + '&dir=' + encodeURIComponent(currentDir);
        return getJson(url).then(function (d) {
            if (!d || !d.ok) return;
            currentPage = page;
            tbody.innerHTML = d.rows;
            var pag = $('nvPaginationContainer');
            var info = $('nvPaginationInfo');
            if (pag) pag.innerHTML = d.pagination || '';
            if (info) info.textContent = d.info || '';
        }).catch(function () {});
    };
    window.NV_recargar = function () { return window.NV_fetchSearch(currentPage); };

    var debounce = null;
    if (inputBuscar) {
        inputBuscar.addEventListener('input', function () {
            clearTimeout(debounce);
            debounce = setTimeout(function () { window.NV_fetchSearch(1); }, 350);
        });
    }

    if (typeof window.CMG_initSort === 'function') {
        window.CMG_initSort(CFG.modulo, function (col, dir) {
            currentSort = col;
            currentDir  = dir;
            window.NV_fetchSearch(1);
        }, { col: currentSort, dir: currentDir, container: '#nvTabla' });
    }

    // ── Editor ──────────────────────────────────────────────────────────
    function editor() {
        if (quill) return quill;
        quill = new Quill('#nvEditor', {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ header: [2, 3, false] }],
                    ['bold', 'italic', 'underline'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    ['link', 'clean']
                ]
            }
        });
        return quill;
    }

    // ── Modal ───────────────────────────────────────────────────────────
    function pintarBarra(n) {
        var barra = $('nvBarraAcciones');
        barra.classList.toggle('d-none', !n);
        // Las pestañas "Adjuntos" y "Leída por" solo tienen sentido con una novedad guardada.
        $('nvTabLeidos').parentElement.classList.toggle('d-none', !n);
        $('nvTabAdjuntos').parentElement.classList.toggle('d-none', !n);
        if (!n) return;
        var publicada = n.estado === 'publicada';
        $('nvBtnPublicar').classList.toggle('d-none', publicada);
        $('nvBtnArchivar').classList.toggle('d-none', !publicada);
        $('nvLeidasBadge').textContent = (n.leidas || 0) + ' / ' + (n.total_usuarios || 0);
        $('nvEstadoActual').textContent = 'Estado actual: ' + (ESTADO_LABEL[n.estado] || n.estado);
    }

    function irAPestanaForm() {
        var tab = bootstrap.Tab.getOrCreateInstance($('nvTabForm'));
        tab.show();
    }

    function abrirModal(n) {
        editor();
        form.reset();
        pintarBarra(n);
        lecturasCargadasPara = null;
        $('nvLecturasTbody').innerHTML = '';
        $('nvLeidosResumen').innerHTML = '&nbsp;';
        $('nvId').value = n ? n.id : '';
        $('nvModalTitulo').textContent = n ? 'Editar novedad' : 'Nueva novedad';
        $('nvEliminarModal').classList.toggle('d-none', !n);
        $('nvTitulo').value      = n ? n.titulo : '';
        $('nvTipo').value        = n ? n.tipo : 'nuevo';
        $('nvEstado').value      = n ? n.estado : 'borrador';
        $('nvResumen').value     = n ? n.resumen : '';
        $('nvRutaModulo').value  = n ? (n.ruta_modulo || '') : '';
        $('nvEnlace').value      = n ? (n.enlace || '') : '';
        pintarAdjuntos(n ? (n.adjuntos || []) : []);
        // Vigencia obligatoria: para una novedad nueva se propone 30 días.
        $('nvVigente').value     = n ? n.vigente_hasta : fechaMasDias(30);
        var info = $('nvPublicadoInfo');
        var hayInfo = !!(n && n.publicado_at);
        info.textContent = hayInfo ? 'Publicada por primera vez el ' + n.publicado_at + '.' : '';
        info.classList.toggle('d-none', !hayInfo);
        quill.root.innerHTML = n ? n.contenido : '';
        irAPestanaForm();
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
        setTimeout(function () { $('nvTitulo').focus(); }, 300);
    }

    function abrirEditar(id, mantenerPestana) {
        return getJson(BASE + '/novedades-sistema/detalle?id=' + encodeURIComponent(id)).then(function (d) {
            if (!d || !d.ok) throw new Error((d && d.error) || 'No se pudo cargar.');
            if (mantenerPestana) {
                // Refresco tras publicar/archivar: solo la barra y los datos, sin reabrir.
                pintarBarra(d.novedad);
                $('nvEstado').value = d.novedad.estado;
                var info = $('nvPublicadoInfo');
                info.textContent = d.novedad.publicado_at ? 'Publicada por primera vez el ' + d.novedad.publicado_at + '.' : '';
                info.classList.toggle('d-none', !d.novedad.publicado_at);
            } else {
                abrirModal(d.novedad);
            }
        }).catch(function (e) { aviso('error', 'No se pudo cargar', e.message); });
    }

    $('nvNueva').addEventListener('click', function () { abrirModal(null); });

    form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var html = editor().root.innerHTML.trim();
        if (html === '' || html === '<p><br></p>') {
            irAPestanaForm();
            aviso('warning', 'Contenido vacío', 'Escriba el contenido de la novedad.');
            return;
        }
        if (!$('nvVigente').value) {
            irAPestanaForm();
            aviso('warning', 'Falta la vigencia', 'Indique hasta qué fecha se mostrará la novedad.');
            $('nvVigente').focus();
            return;
        }
        $('nvContenido').value = html;
        var id = $('nvId').value;
        var btn = $('nvGuardar');
        btn.disabled = true;
        postForm(BASE + '/novedades-sistema/' + (id ? 'update' : 'store'), new FormData(form)).then(function (d) {
            if (!d || !d.ok) throw new Error((d && d.error) || 'No se pudo guardar.');
            toast('success', d.msg || 'Guardado');
            window.NV_recargar();
            if (id) {
                bootstrap.Modal.getOrCreateInstance(modalEl).hide();
            } else {
                // Recién creada: se queda abierta en modo edición para poder
                // adjuntar archivos o publicarla sin volver a buscarla.
                abrirEditar(d.id, true);
                $('nvId').value = d.id;
                $('nvModalTitulo').textContent = 'Editar novedad';
                $('nvEliminarModal').classList.remove('d-none');
            }
        }).catch(function (e) {
            aviso('error', 'No se pudo guardar', e.message);
        }).finally(function () { btn.disabled = false; });
    });

    // ── Acciones del modal ──────────────────────────────────────────────
    function eliminar(id, titulo) {
        confirmar('¿Eliminar la novedad?', titulo, 'Eliminar', '#dc3545').then(function (r) {
            if (!r.isConfirmed) return;
            postForm(BASE + '/novedades-sistema/delete', { id: id }).then(function (d) {
                if (!d || !d.ok) throw new Error((d && d.error) || 'No se pudo eliminar.');
                bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                toast('success', d.msg || 'Eliminada');
                window.NV_recargar();
            }).catch(function (e) { aviso('error', 'No se pudo eliminar', e.message); });
        });
    }

    $('nvEliminarModal').addEventListener('click', function () {
        var id = $('nvId').value;
        if (id) eliminar(id, $('nvTitulo').value);
    });

    // Publicar / Archivar: cambia el estado en el servidor, refresca el listado
    // y la barra del modal (sin cerrarlo ni perder lo que se esté escribiendo).
    function cambiarEstado(estado) {
        var id = $('nvId').value;
        if (!id) return;
        var esPub = estado === 'publicada';
        confirmar(
            esPub ? '¿Publicar la novedad?' : '¿Archivar la novedad?',
            esPub ? 'Los usuarios la verán al ingresar al sistema.' : 'Dejará de mostrarse a los usuarios.',
            esPub ? 'Publicar' : 'Archivar',
            esPub ? '#198754' : '#ffc107'
        ).then(function (r) {
            if (!r.isConfirmed) return;
            postForm(BASE + '/novedades-sistema/cambiar-estado', { id: id, estado: estado }).then(function (d) {
                if (!d || !d.ok) throw new Error((d && d.error) || 'No se pudo cambiar el estado.');
                toast('success', d.msg || 'Listo');
                window.NV_recargar();
                abrirEditar(id, true);
            }).catch(function (e) { aviso('error', 'No se pudo cambiar el estado', e.message); });
        });
    }
    $('nvBtnPublicar').addEventListener('click', function () { cambiarEstado('publicada'); });
    $('nvBtnArchivar').addEventListener('click', function () { cambiarEstado('archivada'); });

    // ── Pestaña "Adjuntos" ──────────────────────────────────────────────
    function pintarAdjuntos(lista) {
        var cont = $('nvAdjuntosLista');
        $('nvAdjuntosBadge').textContent = lista.length;
        if (!lista.length) {
            cont.innerHTML = '<div class="list-group-item text-center text-muted py-4"><i class="bi bi-paperclip fs-4 d-block mb-1"></i>Sin archivos adjuntos. Los que subas aquí podrán descargarlos los usuarios desde la novedad.</div>';
            return;
        }
        cont.innerHTML = lista.map(function (a) {
            var vista = a.es_imagen
                ? '<img src="' + esc(a.url_vista) + '" alt="" class="rounded border me-2" style="width:38px;height:38px;object-fit:cover;">'
                : '<i class="bi ' + esc(a.icono) + ' fs-4 me-2"></i>';
            return '<div class="list-group-item d-flex align-items-center gap-2 py-2">'
                 + vista
                 + '<div class="flex-grow-1 text-truncate"><a href="' + esc(a.url) + '" class="text-decoration-none fw-medium" title="Descargar">' + esc(a.nombre) + '</a>'
                 + '<small class="text-muted d-block">' + esc(a.tamano) + '</small></div>'
                 + '<a href="' + esc(a.url) + '" class="btn btn-sm btn-outline-secondary py-0 px-1" title="Descargar"><i class="bi bi-download"></i></a>'
                 + '<button type="button" class="btn btn-sm btn-outline-danger py-0 px-1 nv-adjunto-eliminar" data-id="' + a.id + '" data-nombre="' + esc(a.nombre) + '" title="Eliminar adjunto"><i class="bi bi-trash"></i></button>'
                 + '</div>';
        }).join('');
    }

    function cargarAdjuntos() {
        var id = $('nvId').value;
        if (!id) return;
        getJson(BASE + '/novedades-sistema/adjuntos?id=' + encodeURIComponent(id)).then(function (d) {
            if (d && d.ok) pintarAdjuntos(d.adjuntos || []);
        }).catch(function () {});
    }
    $('nvTabAdjuntos').addEventListener('shown.bs.tab', cargarAdjuntos);

    $('nvAdjuntoInput').addEventListener('change', function () {
        var id = $('nvId').value;
        var files = this.files;
        if (!id || !files || !files.length) return;
        var fd = new FormData();
        fd.append('id', id);
        for (var i = 0; i < files.length; i++) fd.append('archivos[]', files[i]);
        var input = this;
        var wrap = $('nvAdjuntoProgresoWrap');
        var bar = $('nvAdjuntoProgreso');
        var label = $('nvAdjuntoLabel');
        wrap.classList.remove('d-none');
        bar.style.width = '0%'; bar.textContent = '0%';
        label.classList.add('disabled');

        var xhr = new XMLHttpRequest();
        xhr.open('POST', BASE + '/novedades-sistema/adjunto-subir');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.upload.onprogress = function (ev) {
            if (!ev.lengthComputable) return;
            var p = Math.round(ev.loaded / ev.total * 100);
            bar.style.width = p + '%'; bar.textContent = p + '%';
        };
        xhr.onload = function () {
            wrap.classList.add('d-none');
            label.classList.remove('disabled');
            input.value = '';
            var d = null;
            try { d = JSON.parse(xhr.responseText); } catch (e) { d = null; }
            if (!d || !d.ok) {
                aviso('error', 'No se pudo subir', (d && d.error) || 'Respuesta inválida del servidor.');
                return;
            }
            pintarAdjuntos(d.adjuntos || []);
            if (d.subidos > 0) toast('success', d.subidos === 1 ? 'Archivo adjuntado' : d.subidos + ' archivos adjuntados');
            if (d.errores && d.errores.length) aviso('warning', 'Algunos archivos no se subieron', d.errores.join('\n'));
        };
        xhr.onerror = function () {
            wrap.classList.add('d-none');
            label.classList.remove('disabled');
            input.value = '';
            aviso('error', 'No se pudo subir', 'Error de red al subir los archivos.');
        };
        xhr.send(fd);
    });

    $('nvAdjuntosLista').addEventListener('click', function (ev) {
        var btn = ev.target.closest('.nv-adjunto-eliminar');
        if (!btn) return;
        var idAdj = btn.getAttribute('data-id');
        confirmar('¿Eliminar el adjunto?', btn.getAttribute('data-nombre') || '', 'Eliminar', '#dc3545').then(function (r) {
            if (!r.isConfirmed) return;
            postForm(BASE + '/novedades-sistema/adjunto-eliminar', { id: idAdj }).then(function (d) {
                if (!d || !d.ok) throw new Error((d && d.error) || 'No se pudo eliminar.');
                toast('success', d.msg || 'Eliminado');
                cargarAdjuntos();
            }).catch(function (e) { aviso('error', 'No se pudo eliminar', e.message); });
        });
    });

    // ── Pestaña "Leída por" ─────────────────────────────────────────────
    function cargarLecturas(forzar) {
        var id = $('nvId').value;
        if (!id) return;
        if (!forzar && lecturasCargadasPara === id) return;
        var tb = $('nvLecturasTbody');
        tb.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">Cargando…</td></tr>';
        getJson(BASE + '/novedades-sistema/lecturas-detalle?id=' + encodeURIComponent(id)).then(function (d) {
            if (!d || !d.ok) throw new Error((d && d.error) || 'No se pudo cargar.');
            lecturasCargadasPara = id;
            $('nvLeidosResumen').textContent = d.lecturas.length
                ? d.lecturas.length + (d.lecturas.length === 1 ? ' usuario la marcó como leída.' : ' usuarios la marcaron como leída.')
                : 'Nadie la ha marcado como leída todavía.';
            if (!d.lecturas.length) {
                tb.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-4"><i class="bi bi-eye-slash fs-4 d-block mb-1"></i>Sin lecturas registradas.</td></tr>';
                return;
            }
            tb.innerHTML = d.lecturas.map(function (l) {
                return '<tr><td>' + esc(l.usuario) + '</td><td>' + esc(l.empresa || '—') + '</td><td class="text-nowrap">' + esc(l.leido_at) + '</td></tr>';
            }).join('');
        }).catch(function (e) {
            tb.innerHTML = '<tr><td colspan="3" class="text-center text-danger py-3">' + esc(e.message) + '</td></tr>';
        });
    }
    $('nvTabLeidos').addEventListener('shown.bs.tab', function () { cargarLecturas(false); });
    $('nvLeidosRefrescar').addEventListener('click', function () { cargarLecturas(true); });

    // ── Filas ───────────────────────────────────────────────────────────
    tbody.addEventListener('click', function (ev) {
        var tr = ev.target.closest('tr.nv-row');
        if (tr) abrirEditar(tr.getAttribute('data-id'));
    });
    tbody.addEventListener('keydown', function (ev) {
        if (ev.key !== 'Enter') return;
        var tr = ev.target.closest('tr.nv-row');
        if (tr) abrirEditar(tr.getAttribute('data-id'));
    });
})();
