/* ==========================================================================
 * CMG_ModalNav — botones Anterior / Siguiente en los modales abiertos desde
 * una fila de un listado.
 *
 * Automático, sin configuración por módulo:
 *   1. Se recuerda el último clic del usuario sobre una fila de un listado
 *      (<tbody><tr> fuera de modales/offcanvas) y en qué elemento de la fila fue.
 *   2. Si a continuación se abre un modal, queda asociado a esa fila y en su
 *      encabezado (junto a la X) aparecen las flechas ‹ ›.
 *   3. Navegar = cerrar el modal sin animación y repetir el MISMO clic en la
 *      fila vecina. Así se reutiliza el código de apertura de cada módulo tal
 *      cual (fetch, hidratación, permisos, solo lectura...), sin conocerlo.
 *      Al llegar al borde de la página se pulsa la flecha de paginación del
 *      listado y se abre la primera/última fila de la página nueva.
 *   4. Cambios sin guardar: si el usuario escribió o cambió algo en el modal
 *      (eventos reales, no los que dispara el propio código al hidratar), o
 *      quitó/agregó líneas, se pide confirmación antes de descartarlos. Pulsar
 *      Guardar/Actualizar limpia la marca.
 *
 * Excluir un modal: atributo data-cmg-nav="off" (p. ej. Egresos, que trae su
 * propia navegación sin cerrar el modal).
 * Atajo de teclado: Alt + ← / Alt + →.
 * ========================================================================== */
(function () {
    'use strict';

    var FUERA_DE_LISTADO = '.modal, .offcanvas, .dropdown-menu, .swal2-container';
    var CLAVE_INTENTO = 'cmg_modal_nav_intento';
    var ultimoClic = null;   // { tr, tbody, firma, t }
    var navegando = false;

    // ── Filas del listado ────────────────────────────────────────────────────
    function esFilaListado(tr) {
        if (!tr || !tr.parentElement || tr.parentElement.tagName !== 'TBODY') return false;
        if (tr.closest(FUERA_DE_LISTADO)) return false;
        // Fila de "No se encontraron registros" / separadores de grupo.
        if (tr.cells.length === 1 && tr.cells[0].colSpan > 1) return false;
        return true;
    }

    function clasesDe(el) {
        return Array.prototype.filter.call(el.classList, function (c) {
            return ['active', 'show', 'disabled', 'focus', 'table-active'].indexOf(c) === -1;
        }).sort().join(' ');
    }

    function iconoDe(el) {
        var i = el.querySelector('i[class*="bi-"], i[class*="fa-"]');
        return i ? clasesDe(i) : '';
    }

    function indiceCelda(tr, el) {
        var td = el.closest('td, th');
        return td && td.parentElement === tr ? td.cellIndex : 0;
    }

    // Qué se pulsó dentro de la fila, descrito de forma que se pueda ubicar lo
    // equivalente en otra fila (misma celda, mismo tipo de control, mismo ícono).
    function firmaDe(tr, target) {
        var accion = target.closest('a, button, [onclick], [role="button"], [data-bs-toggle]');
        if (!accion || accion === tr || !tr.contains(accion)) {
            return { tipo: 'fila', celda: indiceCelda(tr, target) };
        }
        return {
            tipo: 'elem',
            celda: indiceCelda(tr, accion),
            tag: accion.tagName,
            clases: clasesDe(accion),
            title: accion.getAttribute('title') || '',
            icono: iconoDe(accion)
        };
    }

    function resolverEn(tr, f) {
        if (f.tipo === 'fila') return tr.cells[f.celda] || tr.cells[0] || tr;
        var ambitos = tr.cells[f.celda] ? [tr.cells[f.celda], tr] : [tr];
        for (var a = 0; a < ambitos.length; a++) {
            var cands = ambitos[a].querySelectorAll(f.tag);
            for (var i = 0; i < cands.length; i++) {
                var c = cands[i];
                if (clasesDe(c) === f.clases && (c.getAttribute('title') || '') === f.title && iconoDe(c) === f.icono) return c;
            }
        }
        return null;
    }

    function visible(el) {
        return !!(el && el.getClientRects().length);
    }

    function tbodyDe(ctx) {
        if (ctx.tbody && ctx.tbody.isConnected) return ctx.tbody;
        if (ctx.tbodyId) return document.getElementById(ctx.tbodyId);
        return null;
    }

    function filasDe(ctx) {
        var tb = tbodyDe(ctx);
        if (!tb) return [];
        return Array.prototype.filter.call(tb.rows, function (tr) {
            return esFilaListado(tr) && visible(tr) && !!resolverEn(tr, ctx.firma);
        });
    }

    // La fila del registro abierto: la misma si sigue en el DOM; si el listado se
    // repintó (p. ej. tras guardar), la que tiene su data-id, o la de su posición.
    function filaActual(ctx, filas) {
        if (ctx.tr && ctx.tr.isConnected && filas.indexOf(ctx.tr) !== -1) return ctx.tr;
        if (ctx.id) {
            for (var i = 0; i < filas.length; i++) if (filas[i].dataset.id === ctx.id) return filas[i];
        }
        return filas[ctx.indice] || null;
    }

    // ── Paginación del listado ───────────────────────────────────────────────
    function deshabilitado(el) {
        return el.disabled || el.classList.contains('disabled') || el.getAttribute('aria-disabled') === 'true' ||
            !!(el.closest('li') && el.closest('li').classList.contains('disabled'));
    }

    function botonPagina(ctx, dir) {
        var tb = tbodyDe(ctx);
        if (!tb) return null;
        var icono = dir > 0 ? 'bi-chevron-right' : 'bi-chevron-left';
        var textos = dir > 0 ? ['›', '»', 'Siguiente'] : ['‹', '«', 'Anterior'];
        var ambitos = [tb.closest('.card'), document];
        for (var a = 0; a < ambitos.length; a++) {
            if (!ambitos[a]) continue;
            var cands = ambitos[a].querySelectorAll('button, a');
            for (var i = 0; i < cands.length; i++) {
                var b = cands[i];
                if (b.closest('table, .modal, .offcanvas, .dropdown-menu, .cmg-modal-nav')) continue;
                var esFlecha = b.querySelector('i.' + icono) || textos.indexOf((b.textContent || '').trim()) !== -1;
                if (!esFlecha) continue;
                // Ámbito documento: solo contenedores que se declaran de paginación.
                if (ambitos[a] === document && !b.closest('[id*="aginat"], [id*="aginac"], .pagination')) continue;
                return deshabilitado(b) || !visible(b) ? null : b;
            }
        }
        return null;
    }

    function esperarCambioListado(ctx, antes) {
        return new Promise(function (resolve) {
            var raiz = (tbodyDe(ctx) && tbodyDe(ctx).closest('.card')) || document.body;
            var fin = null, quieto = null;
            var obs = new MutationObserver(function () {
                clearTimeout(quieto);
                quieto = setTimeout(terminar, 120);
            });
            function terminar() {
                obs.disconnect(); clearTimeout(fin); clearTimeout(quieto);
                var tb = tbodyDe(ctx);
                resolve(!!tb && tb.innerHTML !== antes);
            }
            obs.observe(raiz, { childList: true, subtree: true, characterData: true });
            fin = setTimeout(terminar, 10000);
        });
    }

    // ── Botones en el encabezado del modal ───────────────────────────────────
    function grupoDe(modal) {
        var header = modal.querySelector('.modal-header');
        if (!header) return null;
        var g = header.querySelector(':scope > .cmg-modal-nav');
        if (g) return g;
        g = document.createElement('div');
        g.className = 'cmg-modal-nav btn-group btn-group-sm ms-auto me-2 d-none';
        g.innerHTML =
            '<button type="button" class="btn btn-outline-secondary" data-cmg-nav-dir="-1" title="Registro anterior (Alt+←)"><i class="bi bi-chevron-left"></i></button>' +
            '<button type="button" class="btn btn-outline-secondary" data-cmg-nav-dir="1" title="Registro siguiente (Alt+→)"><i class="bi bi-chevron-right"></i></button>';
        var cerrar = header.querySelector(':scope > .btn-close, :scope > [data-bs-dismiss="modal"]');
        if (cerrar) header.insertBefore(g, cerrar); else header.appendChild(g);
        g.addEventListener('click', function (e) {
            var b = e.target.closest('[data-cmg-nav-dir]');
            if (b && !b.disabled) navegar(modal, parseInt(b.getAttribute('data-cmg-nav-dir'), 10));
        });
        // El listado puede cambiar con el modal abierto (guardar repinta filas):
        // se recalcula al acercar el mouse, antes de que el usuario pulse.
        g.addEventListener('mouseenter', function () { actualizar(modal); });
        return g;
    }

    function actualizar(modal) {
        var ctx = modal._cmgNav;
        var g = ctx ? grupoDe(modal) : modal.querySelector('.modal-header > .cmg-modal-nav');
        if (!g) return;
        var cerrar = modal.querySelector('.modal-header > .btn-close');
        g.classList.toggle('d-none', !ctx);
        // La X tiene margin-left:auto (del CSS de Bootstrap o de un `ms-auto` en la vista);
        // con el grupo visible, el auto lo lleva el grupo. Estilo en línea con !important
        // porque una clase `ms-0` pierde contra `ms-auto` (ambas !important, ms-auto va después)
        // y dos márgenes auto reparten el espacio: las flechas quedaban a media barra.
        if (cerrar) {
            if (ctx) cerrar.style.setProperty('margin-left', '0', 'important');
            else cerrar.style.removeProperty('margin-left');
        }
        if (!ctx) return;
        var filas = filasDe(ctx);
        var actual = filaActual(ctx, filas);
        var idx = filas.indexOf(actual);
        var btns = g.querySelectorAll('[data-cmg-nav-dir]');
        btns[0].disabled = navegando || !(idx > 0 || botonPagina(ctx, -1));
        btns[1].disabled = navegando || !((idx >= 0 && idx < filas.length - 1) || botonPagina(ctx, 1));
        filas.forEach(function (tr) { tr.classList.toggle('table-active', tr === actual); });
    }

    // ── Cambios sin guardar ──────────────────────────────────────────────────
    function modalConNav(el) {
        var m = el && el.closest ? el.closest('.modal') : null;
        return m && m._cmgNav ? m : null;
    }

    function marcarSucio(e) {
        if (!e.isTrusted || navegando) return;
        var t = e.target;
        var m = modalConNav(t);
        if (!m || t.closest('.modal-header, .dropdown-menu, .cmg-modal-nav')) return;
        if (t.type === 'search' || t.disabled || t.readOnly) return;
        m._cmgSucio = true;
    }
    document.addEventListener('input', marcarSucio, true);
    document.addEventListener('change', marcarSucio, true);

    var ICONOS_EDICION = /\bbi-(trash|trash3|x|x-lg|x-circle|dash|dash-circle|plus|plus-lg|plus-circle)\b/;

    document.addEventListener('click', function (e) {
        if (!e.isTrusted || navegando) return;
        var boton = e.target.closest ? e.target.closest('button, a, [onclick]') : null;
        var modal = e.target.closest ? e.target.closest('.modal') : null;
        if (!boton || !modal || boton.closest('.cmg-modal-nav')) return;

        if (modal._cmgNav) {
            // Guardar / Actualizar: lo que había queda en manos del módulo.
            var enFooter = !!boton.closest('.modal-footer');
            if ((enFooter && /\bbtn-(primary|success)\b/.test(boton.className) && !boton.hasAttribute('data-bs-dismiss')) ||
                /guardar|actualizar/i.test(boton.id || '')) {
                modal._cmgSucio = false;
                return;
            }
            // Quitar / agregar líneas dentro del cuerpo.
            // (botón rojo solo dentro de tablas: en la barra de acciones, el rojo es el PDF)
            if (boton.closest('.modal-body') && !boton.closest('.nav, .dropdown-menu') &&
                ((boton.closest('table') && /\bbtn-(outline-)?danger\b/.test(boton.className)) || ICONOS_EDICION.test(boton.innerHTML))) {
                modal._cmgSucio = true;
            }
            return;
        }
        // Confirmar en un modal secundario (buscador de documentos, alta rápida...)
        // suele cargar datos en el modal de abajo.
        if (boton.closest('.modal-footer') && /\bbtn-(primary|success)\b/.test(boton.className)) {
            document.querySelectorAll('.modal.show').forEach(function (m) { if (m._cmgNav) m._cmgSucio = true; });
        }
    }, true);

    function confirmarDescartar(modal) {
        if (!modal._cmgSucio) return Promise.resolve(true);
        if (!window.Swal) return Promise.resolve(window.confirm('Hay cambios sin guardar. ¿Descartarlos y continuar?'));
        return Swal.fire({
            icon: 'warning',
            title: 'Cambios sin guardar',
            text: 'Modificó este registro y no lo guardó. Si continúa, esos cambios se pierden.',
            showCancelButton: true,
            confirmButtonText: 'Descartar y continuar',
            cancelButtonText: 'Seguir editando',
            confirmButtonColor: '#dc3545',
            reverseButtons: true,
            target: modal
        }).then(function (r) { return !!r.isConfirmed; });
    }

    // ── Navegar ──────────────────────────────────────────────────────────────
    function restaurarFade(modal) {
        if (modal._cmgSinFade) { modal.classList.add('fade'); modal._cmgSinFade = false; }
    }

    function ocultar(modal) {
        return new Promise(function (resolve) {
            var inst = window.bootstrap && bootstrap.Modal.getInstance(modal);
            if (!inst || !modal.classList.contains('show')) { resolve(true); return; }
            var hecho = false;
            function listo() {
                if (hecho) return;
                hecho = true;
                resolve(!modal.classList.contains('show'));
            }
            modal.addEventListener('hidden.bs.modal', listo, { once: true });
            // Sin animación: el cierre y la reapertura son inmediatos.
            if (modal.classList.contains('fade')) { modal.classList.remove('fade'); modal._cmgSinFade = true; }
            inst.hide();
            // Un módulo puede impedir el cierre (hide.bs.modal + preventDefault).
            setTimeout(function () {
                if (!hecho) { modal.removeEventListener('hidden.bs.modal', listo); restaurarFade(modal); listo(); }
            }, 1500);
        });
    }

    function abrirFila(tr, firma) {
        ultimoClic = { tr: tr, tbody: tr.parentElement, firma: firma, t: Date.now() };
        var el = resolverEn(tr, firma) || tr.cells[0] || tr;
        el.click();
    }

    function navegar(modal, dir) {
        var ctx = modal._cmgNav;
        if (!ctx || navegando) return;
        confirmarDescartar(modal).then(function (ok) {
            if (!ok || navegando) return;
            var filas = filasDe(ctx);
            var idx = filas.indexOf(filaActual(ctx, filas));
            var destino = idx >= 0 ? filas[idx + dir] : null;
            var pagina = destino ? null : botonPagina(ctx, dir);
            if (!destino && !pagina) return;

            navegando = true;
            actualizar(modal);
            ocultar(modal).then(function (cerrado) {
                if (!cerrado) return null;
                if (destino) return destino;
                // Borde de la página: pasar de página y abrir la primera/última fila.
                // Si la paginación recarga la página entera, el intento queda guardado
                // y se completa al cargar (ver más abajo).
                guardarIntento(ctx, dir);
                var tb = tbodyDe(ctx);
                var antes = tb ? tb.innerHTML : '';
                pagina.click();
                return esperarCambioListado(ctx, antes).then(function (cambio) {
                    borrarIntento();
                    if (!cambio) return null;
                    var nuevas = filasDe(ctx);
                    return dir > 0 ? nuevas[0] : nuevas[nuevas.length - 1];
                });
            }).then(function (fila) {
                navegando = false;
                restaurarFade(modal);
                if (fila) {
                    // La reapertura también sin animación.
                    if (modal.classList.contains('fade')) { modal.classList.remove('fade'); modal._cmgSinFade = true; }
                    setTimeout(function () { restaurarFade(modal); }, 15000);
                    abrirFila(fila, ctx.firma);
                }
            }, function () { navegando = false; restaurarFade(modal); });
        });
    }

    // ── Paginación que recarga la página: completar el salto al cargar ───────
    function guardarIntento(ctx, dir) {
        try {
            sessionStorage.setItem(CLAVE_INTENTO, JSON.stringify({
                ruta: location.pathname, dir: dir, firma: ctx.firma, tbodyId: ctx.tbodyId || '', t: Date.now()
            }));
        } catch (e) { /* sin storage: se queda en la página nueva sin abrir */ }
    }

    function borrarIntento() {
        try { sessionStorage.removeItem(CLAVE_INTENTO); } catch (e) { /* nada */ }
    }

    function retomarIntento() {
        var it = null;
        try { it = JSON.parse(sessionStorage.getItem(CLAVE_INTENTO) || 'null'); } catch (e) { it = null; }
        borrarIntento();
        if (!it || it.ruta !== location.pathname || Date.now() - it.t > 20000) return;
        var tb = (it.tbodyId && document.getElementById(it.tbodyId)) || document.querySelector('.cmg-table-card tbody');
        if (!tb) return;
        var filas = filasDe({ tbody: tb, tbodyId: it.tbodyId, firma: it.firma });
        var fila = it.dir > 0 ? filas[0] : filas[filas.length - 1];
        if (fila) abrirFila(fila, it.firma);
    }

    // ── Enganche con los eventos de la página ────────────────────────────────
    document.addEventListener('click', function (e) {
        if (!e.isTrusted || navegando || !e.target.closest) return;
        var tr = e.target.closest('tr');
        if (tr && esFilaListado(tr)) {
            ultimoClic = { tr: tr, tbody: tr.parentElement, firma: firmaDe(tr, e.target), t: Date.now() };
            return;
        }
        // Cualquier otro clic fuera de modales (p. ej. "Nuevo") rompe la asociación.
        if (!e.target.closest(FUERA_DE_LISTADO)) ultimoClic = null;
    }, true);

    document.addEventListener('shown.bs.modal', function (e) {
        var modal = e.target;
        if (!modal.classList || !modal.classList.contains('modal')) return;
        restaurarFade(modal);
        if (modal.getAttribute('data-cmg-nav') === 'off') return;
        var c = ultimoClic;
        if (c && Date.now() - c.t < 15000 && c.tr.isConnected) {
            var filas = filasDe({ tbody: c.tbody, firma: c.firma });
            modal._cmgNav = {
                tr: c.tr, tbody: c.tbody, tbodyId: c.tbody.id || '', firma: c.firma,
                id: c.tr.dataset.id || '', indice: Math.max(0, filas.indexOf(c.tr))
            };
            ultimoClic = null;
        } else {
            modal._cmgNav = null;
        }
        modal._cmgSucio = false;
        actualizar(modal);
    });

    document.addEventListener('hidden.bs.modal', function (e) {
        var modal = e.target;
        if (navegando || !modal._cmgNav) return;
        var tb = tbodyDe(modal._cmgNav);
        if (tb) Array.prototype.forEach.call(tb.querySelectorAll('tr.table-active'), function (tr) { tr.classList.remove('table-active'); });
        modal._cmgNav = null;
        modal._cmgSucio = false;
        actualizar(modal);
    });

    document.addEventListener('keydown', function (e) {
        if (!e.altKey || (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight')) return;
        var abiertos = document.querySelectorAll('.modal.show');
        var top = abiertos[abiertos.length - 1];
        if (!top || !top._cmgNav) return;
        e.preventDefault();
        var b = top.querySelector('.cmg-modal-nav [data-cmg-nav-dir="' + (e.key === 'ArrowLeft' ? '-1' : '1') + '"]');
        if (b && !b.disabled) navegar(top, e.key === 'ArrowLeft' ? -1 : 1);
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { setTimeout(retomarIntento, 300); });
    } else {
        setTimeout(retomarIntento, 300);
    }

    window.CMG_ModalNav = { navegar: navegar, actualizar: actualizar };
})();
