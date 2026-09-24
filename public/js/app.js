/**
 * JavaScript propio del MVC
 */

// Formatea una fecha en YYYY-MM-DD usando el calendario LOCAL del navegador.
// toISOString() nativo usa UTC: en Ecuador (UTC-5) eso adelanta la fecha un día
// a partir de las 19:00. Usar siempre esta función para fechas "de hoy" en inputs.
window.CMG_fechaLocal = function(d) {
    d = d || new Date();
    return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().split('T')[0];
};

// Dígito verificador de cédula y RUC ecuatorianos. Espejo de
// App\Helpers\DigitoVerificador (si cambia el algoritmo, cambiarlo en los dos).
// Es SOLO un aviso: hay números reales que no superan el algoritmo, así que nunca
// se usa para impedir guardar. Si el SRI encuentra el número, el aviso se retira.
window.CMG_Identificacion = (function () {
    function provinciaValida(n) {
        var p = parseInt(n.substr(0, 2), 10);
        return p >= 1 && (p <= 24 || p === 30);
    }

    function cedulaValida(c) {
        if (!/^\d{10}$/.test(c) || !provinciaValida(c) || +c[2] > 5) return false;
        var suma = 0;
        for (var i = 0; i < 9; i++) {
            var v = +c[i] * (i % 2 === 0 ? 2 : 1);
            suma += v > 9 ? v - 9 : v;
        }
        return (10 - (suma % 10)) % 10 === +c[9];
    }

    function modulo11(n, coef, pos) {
        var suma = 0;
        for (var i = 0; i < coef.length; i++) suma += +n[i] * coef[i];
        var r = suma % 11;
        return (r === 0 ? 0 : 11 - r) === +n[pos];
    }

    function rucValido(r) {
        if (!/^\d{13}$/.test(r) || !provinciaValida(r)) return false;
        var t = +r[2];
        if (t < 6) return cedulaValida(r.substr(0, 10));
        if (t === 6) return modulo11(r, [3, 2, 7, 6, 5, 4, 3, 2], 8);
        if (t === 9) return modulo11(r, [4, 3, 2, 7, 6, 5, 4, 3, 2], 9);
        return false;
    }

    // tipo: 'CEDULA' | 'RUC' (cualquier otro no tiene algoritmo). Devuelve el mensaje o null.
    function aviso(tipo, valor) {
        tipo = String(tipo || '').toUpperCase();
        valor = String(valor || '').trim();
        if (tipo === 'CEDULA' && valor.length === 10 && !cedulaValida(valor)) {
            return 'Cédula incorrecta, revisar.';
        }
        if (tipo === 'RUC' && valor.length === 13 && !rucValido(valor)) {
            return 'RUC incorrecto, revisar.';
        }
        return null;
    }

    // Muestra (o retira, con mensaje null) el aviso ámbar debajo del campo.
    // Crea el contenedor la primera vez: `{id del input}_aviso`.
    function pintarAviso(input, mensaje) {
        if (!input) return;
        var id = input.id + '_aviso';
        var el = document.getElementById(id);
        if (!mensaje) {
            if (el) el.classList.add('d-none');
            return;
        }
        if (!el) {
            el = document.createElement('div');
            el.id = id;
            el.className = 'small text-warning-emphasis mt-1';
            var ancla = input.closest('.input-group') || input;
            ancla.insertAdjacentElement('afterend', el);
        }
        el.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i>';
        el.appendChild(document.createTextNode(mensaje));
        el.classList.remove('d-none');
    }

    // Aplica el aviso tras la respuesta de consultarSri: si el SRI confirmó el
    // número (ok y sin `source` local) se retira; si respondió "No encontrado", se
    // refuerza. Si el servicio falló (sin conexión, HTTP de error) queda el aviso simple.
    function avisoTrasSri(input, tipo, respuesta) {
        var base = aviso(tipo, input ? input.value : '');
        if (!base) { pintarAviso(input, null); return; }
        if (respuesta && respuesta.ok && !respuesta.source) { pintarAviso(input, null); return; }
        if (respuesta && !respuesta.ok && /no encontrado/i.test(respuesta.error || '')) {
            base += ' El SRI no encontró este número.';
        }
        pintarAviso(input, base);
    }

    return { cedulaValida: cedulaValida, rucValido: rucValido, aviso: aviso, pintarAviso: pintarAviso, avisoTrasSri: avisoTrasSri };
})();

(function() {
    'use strict';
    document.addEventListener('DOMContentLoaded', function() {
        var form = document.getElementById('form-cambiar-empresa');
        var input = document.getElementById('input-empresas');
        var dropdown = document.getElementById('dropdown-empresas');
        if (!form || !input || !dropdown) return;
        var idInput = form.querySelector('input[name="id_empresa"]');
        var rucInput = form.querySelector('input[name="ruc_empresa"]');
        var items = dropdown.querySelectorAll('.cmg-empresas-dropdown-item');

        // PRIMERO: Restaurar empresa desde sessionStorage INMEDIATAMENTE
        // (antes de cualquier otro código que pueda interferir)
        var storedEmpresaId = sessionStorage.getItem('cmg-empresa-id');
        var storedEmpresaText = sessionStorage.getItem('cmg-empresa-text');

        // Si el input está vacío pero hay un valor guardado en sessionStorage, restaurar
        if (input.value.trim() === '' && storedEmpresaText && storedEmpresaId) {
            input.value = storedEmpresaText;
            if (idInput) idInput.value = storedEmpresaId;
            // Buscar el ruc también
            for (var i = 0; i < items.length; i++) {
                if (items[i].getAttribute('data-id') === storedEmpresaId) {
                    if (rucInput && items[i].getAttribute('data-ruc')) {
                        rucInput.value = items[i].getAttribute('data-ruc');
                    }
                    break;
                }
            }
        }

        // FALLBACK AGRESIVO: Si el dropdown está vacío O el input vacío, cargar empresas vía AJAX
        function cargarEmpresasAjax() {
            var baseUrl = (window.CMS_CONFIG && window.CMS_CONFIG.baseUrl) ? window.CMS_CONFIG.baseUrl : '/';
            var url = baseUrl + '/empresa/getEmpresasAsignadasAjax';

            console.log('[CaMaGaRe] Cargando empresas desde AJAX: ' + url);

            fetch(url, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
                .then(function(r) {
                    console.log('[CaMaGaRe] Respuesta AJAX status: ' + r.status);
                    return r.json();
                })
                .then(function(res) {
                    console.log('[CaMaGaRe] Datos AJAX recibidos:', res);

                    if (res.ok && res.empresas && res.empresas.length > 0) {
                        // Limpiar items existentes
                        var itemsExistentes = dropdown.querySelectorAll('.cmg-empresas-dropdown-item');
                        itemsExistentes.forEach(function(item) { item.remove(); });

                        // Reconstruir el dropdown con los datos AJAX
                        res.empresas.forEach(function(emp) {
                            var div = document.createElement('div');
                            div.className = 'cmg-empresas-dropdown-item';
                            div.setAttribute('data-id', emp.id_empresa);
                            div.setAttribute('data-text', emp.texto);
                            div.setAttribute('data-ruc', emp.ruc || '');
                            div.setAttribute('data-razon', emp.razon || '');
                            div.textContent = emp.texto;
                            dropdown.appendChild(div);
                        });

                        console.log('[CaMaGaRe] Dropdown reconstruido con ' + res.empresas.length + ' empresas');

                        // Agregar event listeners a los nuevos items
                        dropdown.querySelectorAll('.cmg-empresas-dropdown-item').forEach(function(item) {
                            item.addEventListener('mousedown', function(e) {
                                e.preventDefault();
                                selectItem(item);
                            });
                        });

                        // Actualizar la referencia a items
                        items = dropdown.querySelectorAll('.cmg-empresas-dropdown-item');

                        // Actualizar botón de favorito después de cargar empresas
                        if (typeof actualizarBtnFavorito === 'function') {
                            actualizarBtnFavorito();
                        }
                    } else {
                        console.warn('[CaMaGaRe] Respuesta AJAX inválida:', res);
                    }
                })
                .catch(function(err) {
                    console.error('[CaMaGaRe] Error al cargar empresas AJAX:', err);
                });
        }

        // Si no hay items en el dropdown, cargar vía AJAX (independientemente de sessionStorage)
        if (items.length === 0) {
            console.log('[CaMaGaRe] Dropdown vacío, cargando empresas vía AJAX');
            cargarEmpresasAjax();
        }
        // Si el input está vacío pero hay sesión guardada, restaurar y cargar AJAX si es necesario
        else if (input.value.trim() === '' && storedEmpresaId) {
            console.log('[CaMaGaRe] Input vacío pero hay sesión, restaurando...');
            input.value = storedEmpresaText;
            if (idInput) idInput.value = storedEmpresaId;
        }

        var isOpening = false;

        function showDropdown() {
            isOpening = true;
            var portal = document.getElementById('cmg-dropdown-portal');
            (portal || document.body).appendChild(dropdown);
            var rect = input.getBoundingClientRect();
            dropdown.style.left = rect.left + 'px';
            dropdown.style.top = (rect.bottom + 2) + 'px';
            dropdown.style.width = rect.width + 'px';
            // Asegurar que el dropdown tenga el z-index más alto
            dropdown.style.zIndex = '99999';
            dropdown.style.position = 'fixed';
            dropdown.classList.add('cmg-empresas-dropdown-open');

            filterItems(true);
            input.select();

            setTimeout(function() {
                isOpening = false;
            }, 300);
        }
        function hideDropdown() {
            dropdown.classList.remove('cmg-empresas-dropdown-open');
            form.appendChild(dropdown);
        }
        function filterItems(ignoreQuery) {
            var query = (input.value || '').trim().toLowerCase();
            var q = ignoreQuery ? '' : query;

            for (var i = 0; i < items.length; i++) {
                var item = items[i];
                var text = (item.getAttribute('data-text') || '').toLowerCase();
                var ruc = (item.getAttribute('data-ruc') || '').toLowerCase();
                var razon = (item.getAttribute('data-razon') || '').toLowerCase();

                // Sin filtro: mostrar todas las empresas. Con filtro: todas las que
                // coincidan en texto (nombre comercial mostrado), RUC, o razón social
                // (aunque el nombre comercial la tape en el texto visible). El alto lo
                // limita el CSS (max-height + overflow-y), que activa la barra de
                // scroll cuando hay muchas.
                var isMatch = (q === '') || (text.indexOf(q) !== -1 || ruc.indexOf(q) !== -1 || razon.indexOf(q) !== -1);

                if (isMatch) {
                    item.style.setProperty('display', 'block', 'important');
                } else {
                    item.style.setProperty('display', 'none', 'important');
                }
            }
        }
        function selectItem(item) {
            var id = item.getAttribute('data-id');
            var text = item.getAttribute('data-text');
            var ruc = item.getAttribute('data-ruc') || '';
            var idInput = form.querySelector('input[name="id_empresa"]');
            var rucInput = form.querySelector('input[name="ruc_empresa"]');
            if (idInput) idInput.value = id;
            if (rucInput) rucInput.value = ruc;
            input.value = text || '';

            // Guardar en sessionStorage para preservar en navegación
            sessionStorage.setItem('cmg-empresa-id', id);
            sessionStorage.setItem('cmg-empresa-text', text);

            hideDropdown();
            form.submit();
        }

        // Guardar el estado del input antes de navegar a cualquier página
        window.addEventListener('beforeunload', function() {
            if (input && input.value && idInput && idInput.value) {
                sessionStorage.setItem('cmg-empresa-id', idInput.value);
                sessionStorage.setItem('cmg-empresa-text', input.value);
            }
        });

        // Backspace/Delete limpian TODO el input de una vez para empezar una
        // búsqueda nueva (el monitor ya no lo rellena mientras está enfocado).
        // Escape cierra el dropdown.
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' || e.key === 'Delete') {
                e.preventDefault();
                input.value = '';
                filterItems(false);
            } else if (e.key === 'Escape') {
                hideDropdown();
                input.blur();
            }
        });
        input.addEventListener('focus', showDropdown);
        input.addEventListener('input', function() {
            if (!isOpening) filterItems(false);
        });
        input.addEventListener('click', showDropdown);
        
        items.forEach(function(item) {
            item.addEventListener('mousedown', function(e) {
                e.preventDefault();
                selectItem(item);
            });
        });
        document.addEventListener('click', function(e) {
            // No cerrar dropdown si se hace clic en links de navegación del navbar
            if (e.target.closest('[data-navbar-link]')) return;
            if (!form.contains(e.target) && !dropdown.contains(e.target)) hideDropdown();
        });

        // MONITOR CRÍTICO: El input NUNCA debe estar vacío si hay una empresa en sessionStorage
        // Restaurar cada 50ms si se borra accidentalmente
        var monitorInterval = setInterval(function() {
            var storedId = sessionStorage.getItem('cmg-empresa-id');
            var storedText = sessionStorage.getItem('cmg-empresa-text');
            var currentInputValue = (input.value || '').trim();
            var currentIdValue = (idInput && idInput.value) || '';

            // Si el input está vacío pero hay un valor guardado, RESTAURAR.
            // Excepción: NO restaurar mientras el usuario está escribiendo/buscando
            // (input enfocado), para que pueda limpiar el campo y filtrar sin que
            // el monitor lo vuelva a llenar solo.
            if (currentInputValue === '' && storedText && storedId && document.activeElement !== input) {
                input.value = storedText;
                if (idInput) idInput.value = storedId;
                console.log('[CaMaGaRe] Empresa restaurada desde sessionStorage: ' + storedText);
            }
            // Si hay valor en input pero NO en sessionStorage, GUARDAR
            else if (currentInputValue !== '' && currentIdValue !== '' && (!storedId || !storedText)) {
                sessionStorage.setItem('cmg-empresa-id', currentIdValue);
                sessionStorage.setItem('cmg-empresa-text', currentInputValue);
            }
        }, 50); // Cada 50ms

        // Limpiar el monitor cuando se descarga la página
        window.addEventListener('beforeunload', function() {
            clearInterval(monitorInterval);
        });

        // =====================================================================
        // GLOBAL: Actualizar tabla de listado al cerrar modales principales
        // =====================================================================
        document.addEventListener('hidden.bs.modal', function(e) {
            var modalId = e.target.id;
            // Solo actuar si el modal tiene ID y parece ser un modal principal
            if (!modalId || modalId.toLowerCase().indexOf('modal') === -1) return;

            // Evitar re-render innecesario si es un modal pequeño genérico
            var ignoredModals = ['modalFavoritos', 'modalAlert', 'modalConfirm', 'modalColumnas'];
            if (ignoredModals.includes(modalId)) return;

            // Mapa de Modal IDs a sus respectivas funciones de búsqueda globales
            var modalFetchMap = {
                'modalCompra': 'CMG_fetchSearch',
                'modalVenta': 'VT_fetchSearch',
                'modalLiquidacion': 'LC_fetchSearch',
                'modalNC': 'NC_fetchSearch',
                'modalPedido': 'PED_fetchSearch',
                'modalProforma': 'PF_fetchSearch',
                'modalIngreso': 'ING_fetchSearch',
                'modalEgreso': 'EGR_fetchSearch',
                'modalRetencion': 'RET_fetchSearch',
                'modalGuiaRemision': 'GR_fetchSearch',
                'modalCategoria': 'fetchSearchCat',
                'modalMarca': 'fetchSearchMar',
                'modalFirma': 'fetchSearchFirmas'
            };

            // Determinar qué función llamar
            var funcToCall = 'fetchSearch'; // Por defecto la mayoría usan fetchSearch
            if (modalFetchMap[modalId]) {
                funcToCall = modalFetchMap[modalId];
            }

            // Ejecutar la función si existe
            if (typeof window[funcToCall] === 'function') {
                var page = 1;
                if (funcToCall === 'CMG_fetchSearch' && typeof window.CMG_currentPage !== 'undefined') page = window.CMG_currentPage;
                else if (funcToCall === 'VT_fetchSearch' && typeof window.VT_currentPage !== 'undefined') page = window.VT_currentPage;
                else if (funcToCall === 'LC_fetchSearch' && typeof window.LC_currentPage !== 'undefined') page = window.LC_currentPage;
                else if (typeof window.currentPage !== 'undefined') page = window.currentPage;

                window[funcToCall](page);
            }
        });
    });
})();
