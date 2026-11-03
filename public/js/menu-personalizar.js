/**
 * Personalización del menú de módulos, compartida por la barra horizontal
 * (partials/menu-modulos.php) y el menú lateral (partials/menu-sidebar.php).
 *
 * Un solo botón «Personalizar menú» abre un panel con:
 *   - Tipo de menú: horizontal en una fila / en dos filas / vertical (guarda y recarga).
 *   - Reordenar módulos…: entra en modo arrastrar, con una franja Cancelar / Listo.
 *     Nada se guarda mientras se arrastra: solo al pulsar «Listo» y si el orden
 *     cambió. «Cancelar» o Esc devuelven los módulos al orden que tenían.
 *   - Restablecer orden original (pide confirmación).
 *
 * La personalización es del USUARIO, igual en todas sus empresas:
 * Preferencias/guardarMenuAjax → UsuarioPreferenciaService::guardarMenuUsuario().
 */
(function () {
    if (window.CMG_MenuPersonalizar) return;

    var urlGuardar = '';

    /** POST {tipo?, orden?, colapsado?}. Rechaza si el servidor no respondió ok. */
    function guardar(cambios) {
        var fd = new FormData();
        fd.append('menu', JSON.stringify(cambios));
        return fetch(urlGuardar, {
            method: 'POST', body: fd, credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) {
            return r.json().catch(function () { return null; });
        }).then(function (j) {
            if (!j || !j.ok) throw new Error((j && j.error) || 'No se pudo guardar la preferencia del menú.');
            return j;
        });
    }

    function avisarError(titulo, e) {
        var texto = (e && e.message) || 'Error de conexión.';
        if (window.Swal) Swal.fire(titulo, texto, 'error');
        else alert(titulo + '\n' + texto);
    }

    function confirmar(titulo, texto, boton) {
        if (window.Swal) {
            return Swal.fire({
                title: titulo, text: texto, icon: 'question',
                showCancelButton: true, confirmButtonText: boton, cancelButtonText: 'Cancelar'
            }).then(function (r) { return !!r.isConfirmed; });
        }
        return Promise.resolve(window.confirm(titulo + '\n' + texto));
    }

    /**
     * @param {Object} o
     *   boton          botón «Personalizar menú»
     *   url            endpoint Preferencias/guardarMenuAjax
     *   tipoActual     'horizontal' | 'horizontal_filas' | 'vertical'
     *   inicioActual   pantalla al ingresar: 'accesos' | 'dashboard'
     *   puedeDashboard muestra el grupo «Al ingresar mostrar» (sin dashboard no hay qué elegir)
     *   contenedor     elemento que recibe claseModo mientras se reordena
     *   claseModo      clase CSS del modo reordenar
     *   lista          <ul> de los módulos
     *   selectorItem   selector de cada módulo (con data-id-modulo)
     *   claseArrastre  clase del módulo que se está arrastrando
     *   eje            'x' (barra horizontal) | 'y' (menú lateral)
     *   scroller       elemento con scroll, para desplazarlo al arrastrar cerca del borde
     *   franja         franja con [data-cmg-reordenar-cancelar] y [data-cmg-reordenar-listo]
     *   alEntrar       función opcional antes de entrar al modo reordenar
     * @returns {{reordenando: function(): boolean, cancelar: function()}}
     */
    function init(o) {
        urlGuardar = o.url;
        var boton = o.boton;
        var lista = o.lista;
        var puedeReordenar = !!(lista && lista.querySelector(o.selectorItem) && o.franja);
        var snapshot = null;      // nodos en el orden previo al modo reordenar
        var ordenInicial = '';
        var dragEl = null;

        function items() {
            return Array.prototype.slice.call(lista.querySelectorAll(o.selectorItem));
        }
        function ids() {
            return items().map(function (li) { return li.dataset.idModulo; });
        }

        /* ── Panel «Personalizar menú» ── */
        var panel = document.createElement('div');
        panel.className = 'cmg-menu-ajustes d-none';
        panel.setAttribute('role', 'menu');
        var inicioActual = o.inicioActual === 'dashboard' ? 'dashboard' : 'accesos';
        /** Opción tipo radio: data-tipo (tipo de menú) o data-inicio (pantalla al ingresar). */
        var opcionRadio = function (atributo, valor, actual, icono, texto) {
            var marcado = actual === valor;
            return '<button type="button" class="cmg-menu-ajustes__opcion' + (marcado ? ' activo' : '') + '"'
                + ' role="menuitemradio" aria-checked="' + marcado + '" data-' + atributo + '="' + valor + '">'
                + '<i class="bi ' + (marcado ? 'bi-record-circle-fill' : 'bi-circle') + ' cmg-menu-ajustes__radio"></i>'
                + '<i class="bi ' + icono + '"></i><span>' + texto + '</span></button>';
        };
        var opcionTipo = function (tipo, icono, texto) {
            return opcionRadio('tipo', tipo, o.tipoActual, icono, texto);
        };
        panel.innerHTML =
            '<div class="cmg-menu-ajustes__titulo">Personalizar menú</div>'
            + '<div class="cmg-menu-ajustes__grupo">Tipo de menú</div>'
            + opcionTipo('horizontal', 'bi-window', 'Horizontal en una fila')
            + opcionTipo('horizontal_filas', 'bi-view-stacked', 'Horizontal en dos filas')
            + opcionTipo('vertical', 'bi-layout-sidebar', 'Vertical (a la izquierda)')
            + (o.puedeDashboard
                ? '<div class="cmg-menu-ajustes__sep"></div>'
                + '<div class="cmg-menu-ajustes__grupo">Al ingresar mostrar</div>'
                + opcionRadio('inicio', 'accesos', inicioActual, 'bi-lightning-charge', 'Inicio (accesos frecuentes)')
                + opcionRadio('inicio', 'dashboard', inicioActual, 'bi-speedometer2', 'Dashboard')
                : '')
            + (puedeReordenar
                ? '<div class="cmg-menu-ajustes__sep"></div>'
                + '<button type="button" class="cmg-menu-ajustes__opcion" role="menuitem" data-accion="reordenar">'
                + '<i class="bi bi-arrows-move"></i><span>Reordenar módulos…</span></button>'
                + '<button type="button" class="cmg-menu-ajustes__opcion" role="menuitem" data-accion="restablecer">'
                + '<i class="bi bi-arrow-counterclockwise"></i><span>Restablecer orden original</span></button>'
                : '');
        document.body.appendChild(panel);

        function panelAbierto() { return !panel.classList.contains('d-none'); }
        function cerrarPanel() {
            panel.classList.add('d-none');
            boton.setAttribute('aria-expanded', 'false');
            boton.classList.remove('active');
        }
        function abrirPanel() {
            panel.classList.remove('d-none');
            boton.setAttribute('aria-expanded', 'true');
            boton.classList.add('active');
            var r = boton.getBoundingClientRect();
            var w = panel.offsetWidth, h = panel.offsetHeight, pad = 8;
            // Alineado al borde derecho del botón; si no cabe, al izquierdo.
            var left = r.right - w;
            if (left < pad) left = Math.min(r.left, window.innerWidth - w - pad);
            var top = r.bottom + 4;
            if (top + h > window.innerHeight - pad) top = Math.max(pad, window.innerHeight - h - pad);
            panel.style.left = Math.max(pad, left) + 'px';
            panel.style.top = top + 'px';
            var primera = panel.querySelector('.cmg-menu-ajustes__opcion');
            if (primera) primera.focus({ preventScroll: true });
        }

        boton.setAttribute('aria-haspopup', 'true');
        boton.setAttribute('aria-expanded', 'false');
        boton.addEventListener('click', function (e) {
            e.stopPropagation();
            if (panelAbierto()) cerrarPanel(); else abrirPanel();
        });
        document.addEventListener('mousedown', function (e) {
            if (panelAbierto() && !panel.contains(e.target) && !boton.contains(e.target)) cerrarPanel();
        });
        window.addEventListener('resize', function () { if (panelAbierto()) cerrarPanel(); });

        panel.addEventListener('click', function (e) {
            var op = e.target.closest('.cmg-menu-ajustes__opcion');
            if (!op || op.disabled) return;

            var tipo = op.getAttribute('data-tipo');
            if (tipo) {
                if (tipo === o.tipoActual) { cerrarPanel(); return; }
                op.disabled = true;
                guardar({ tipo: tipo })
                    .then(function () { window.location.reload(); })
                    .catch(function (err) {
                        op.disabled = false;
                        cerrarPanel();
                        avisarError('No se pudo cambiar el menú', err);
                    });
                return;
            }

            // Pantalla al ingresar: se guarda sin recargar; solo cambia la marca.
            var inicio = op.getAttribute('data-inicio');
            if (inicio) {
                if (inicio === inicioActual) { cerrarPanel(); return; }
                op.disabled = true;
                guardar({ inicio: inicio })
                    .then(function () {
                        inicioActual = inicio;
                        panel.querySelectorAll('[data-inicio]').forEach(function (b) {
                            var m = b.getAttribute('data-inicio') === inicio;
                            b.classList.toggle('activo', m);
                            b.setAttribute('aria-checked', m ? 'true' : 'false');
                            var radio = b.querySelector('.cmg-menu-ajustes__radio');
                            radio.classList.toggle('bi-record-circle-fill', m);
                            radio.classList.toggle('bi-circle', !m);
                        });
                        cerrarPanel();
                    })
                    .catch(function (err) {
                        cerrarPanel();
                        avisarError('No se pudo guardar la preferencia', err);
                    })
                    .then(function () { op.disabled = false; });
                return;
            }

            var accion = op.getAttribute('data-accion');
            cerrarPanel();
            if (accion === 'reordenar') {
                entrar();
            } else if (accion === 'restablecer') {
                confirmar('¿Restablecer el orden?', 'Los módulos volverán al orden original del sistema.', 'Sí, restablecer')
                    .then(function (si) {
                        if (!si) return;
                        guardar({ orden: [] })
                            .then(function () { window.location.reload(); })
                            .catch(function (err) { avisarError('No se pudo restablecer el orden', err); });
                    });
            }
        });

        /* ── Modo reordenar ── */
        var btnCancelar = o.franja ? o.franja.querySelector('[data-cmg-reordenar-cancelar]') : null;
        var btnListo = o.franja ? o.franja.querySelector('[data-cmg-reordenar-listo]') : null;

        function marcarModo(activo) {
            window.__cmgMenuReordenando = activo;
            o.contenedor.classList.toggle(o.claseModo, activo);
            o.franja.classList.toggle('d-none', !activo);
            boton.disabled = activo;
            items().forEach(function (li) { li.setAttribute('draggable', activo ? 'true' : 'false'); });
        }
        function entrar() {
            if (!puedeReordenar || snapshot) return;
            if (typeof o.alEntrar === 'function') o.alEntrar();
            snapshot = items();
            ordenInicial = ids().join(',');
            marcarModo(true);
            if (btnListo) btnListo.focus({ preventScroll: true });
        }
        function salir() {
            snapshot = null;
            marcarModo(false);
        }
        function cancelar() {
            if (!snapshot) return;
            snapshot.forEach(function (li) { lista.appendChild(li); });
            salir();
        }
        function terminar() {
            if (!snapshot) return;
            var orden = ids();
            if (orden.join(',') === ordenInicial) { salir(); return; }
            btnListo.disabled = true;
            guardar({ orden: orden })
                .then(salir)
                .catch(function (err) { avisarError('No se pudo guardar el orden', err); })
                .then(function () { btnListo.disabled = false; });
        }
        if (btnCancelar) btnCancelar.addEventListener('click', cancelar);
        if (btnListo) btnListo.addEventListener('click', terminar);

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            if (panelAbierto()) { cerrarPanel(); boton.focus(); return; }
            // Con una ventana (modal/Swal) abierta, Esc es de ella.
            if (snapshot && !document.querySelector('.modal.show, .swal2-container')) cancelar();
        });

        if (puedeReordenar) {
            lista.addEventListener('dragstart', function (e) {
                if (!snapshot) return;
                var li = e.target.closest(o.selectorItem);
                if (!li) { e.preventDefault(); return; }
                dragEl = li;
                li.classList.add(o.claseArrastre);
                e.dataTransfer.effectAllowed = 'move';
                try { e.dataTransfer.setData('text/plain', li.dataset.idModulo || ''); } catch (err) {}
            });
            lista.addEventListener('dragover', function (e) {
                if (!dragEl) return;
                e.preventDefault();
                // Cerca del borde, desplaza la lista para llegar a los módulos fuera de la vista.
                if (o.scroller) {
                    var s = o.scroller.getBoundingClientRect(), margen = 40, paso = 14;
                    if (o.eje === 'x') {
                        if (e.clientX < s.left + margen) o.scroller.scrollBy({ left: -paso, behavior: 'instant' });
                        else if (e.clientX > s.right - margen) o.scroller.scrollBy({ left: paso, behavior: 'instant' });
                    } else {
                        if (e.clientY < s.top + margen) o.scroller.scrollBy({ top: -paso, behavior: 'instant' });
                        else if (e.clientY > s.bottom - margen) o.scroller.scrollBy({ top: paso, behavior: 'instant' });
                    }
                }
                var li = e.target.closest(o.selectorItem);
                if (!li || li === dragEl) return;
                var r = li.getBoundingClientRect();
                var despues = o.eje === 'x'
                    ? (e.clientX - r.left) > (r.width / 2)
                    : (e.clientY - r.top) > (r.height / 2);
                lista.insertBefore(dragEl, despues ? li.nextSibling : li);
            });
            lista.addEventListener('drop', function (e) { if (dragEl) e.preventDefault(); });
            lista.addEventListener('dragend', function () {
                if (dragEl) dragEl.classList.remove(o.claseArrastre);
                dragEl = null;
            });
        }

        return {
            reordenando: function () { return !!snapshot; },
            cancelar: cancelar
        };
    }

    window.CMG_MenuPersonalizar = { init: init };
    // Compatibilidad: el sidebar guarda "contraído" con esta función.
    window.CMG_guardarMenuUsuario = function (cambios) { return guardar(cambios); };
})();
