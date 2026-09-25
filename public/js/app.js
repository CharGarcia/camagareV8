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

/* ============================================================================
 * Descargas de archivos (Excel, PDF, XML, CSV, ZIP) con aviso "Generando…"
 * ----------------------------------------------------------------------------
 * CMG_descargar(url, opciones) pide el archivo con fetch en vez de abrir una
 * pestaña: mientras el servidor lo arma se ve un aviso con spinner y, si falla
 * (sin permiso, sesión vencida, demasiados datos, error del servidor), el motivo
 * sale en un SweetAlert en lugar de una pestaña en blanco o con texto suelto.
 * El servidor recibe X-Requested-With, así que los controladores que distinguen
 * peticiones AJAX responden sus errores en JSON ({ error | mensaje | msg }).
 *
 * Opciones: { nombre: 'Excel' | 'PDF' | … (texto del aviso; si falta se deduce
 * de la URL), archivo: nombre por defecto si el servidor no manda uno }.
 *
 * Además, los enlaces de exportación de los listados (href con /export-pdf o
 * /export-excel) pasan solos por aquí: no hay que tocar cada vista. Un enlace
 * se excluye con data-descarga-directa, y se respeta el clic con Ctrl/Cmd/
 * Shift/rueda (abrir en otra pestaña) y cualquier handler del módulo que ya
 * haya hecho preventDefault (p. ej. el tope de filas de Pedidos).
 * ========================================================================== */
(function () {
    function nombreDesdeUrl(url) {
        var u = String(url).toLowerCase();
        if (/excel|xlsx|\.xls/.test(u)) return 'Excel';
        if (/pdf/.test(u)) return 'PDF';
        if (/xml/.test(u)) return 'XML';
        if (/csv/.test(u)) return 'CSV';
        if (/zip/.test(u)) return 'ZIP';
        return 'archivo';
    }

    function nombreArchivo(disposition, porDefecto) {
        var d = disposition || '';
        var m = d.match(/filename\*\s*=\s*(?:UTF-8'')?([^;]+)/i);
        if (m) {
            try { return decodeURIComponent(m[1].trim().replace(/^"|"$/g, '')); } catch (e) { /* sigue */ }
        }
        m = d.match(/filename\s*=\s*"?([^";]+)"?/i);
        if (m) {
            try { return decodeURIComponent(m[1].trim()); } catch (e) { return m[1].trim(); }
        }
        return porDefecto;
    }

    // Extensión para el nombre por defecto, cuando el servidor no manda Content-Disposition.
    function extensionDe(tipo) {
        var t = String(tipo).toLowerCase();
        if (t.indexOf('spreadsheetml') !== -1) return '.xlsx';
        if (t.indexOf('ms-excel') !== -1) return '.xls';
        if (t.indexOf('pdf') !== -1) return '.pdf';
        if (t.indexOf('xml') !== -1) return '.xml';
        if (t.indexOf('csv') !== -1) return '.csv';
        if (t.indexOf('zip') !== -1) return '.zip';
        return '';
    }

    function aviso(icon, title, text) {
        if (typeof Swal !== 'undefined') Swal.fire({ icon: icon, title: title, text: text });
        else alert(title + '\n\n' + text);
    }

    // Pide el archivo con el aviso "Generando…" y resuelve { blob, archivo } si
    // llegó bien, o null si falló (el motivo ya se mostró en un SweetAlert).
    function obtenerArchivo(url, opciones) {
        opciones = opciones || {};
        var nombre = opciones.nombre || nombreDesdeUrl(url);
        var titulo = nombre === 'archivo' ? 'el archivo' : 'el ' + nombre;

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Generando ' + (nombre === 'archivo' ? 'archivo' : nombre) + '…',
                text: 'Esto puede tardar si hay muchos datos.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: function () { Swal.showLoading(); }
            });
        }

        return fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (response) {
                var tipo = response.headers.get('Content-Type') || '';
                if (tipo.indexOf('application/json') !== -1) {
                    return response.json().then(function (res) {
                        var msg = (res && (res.error || res.mensaje || res.msg || res.message)) || 'Ocurrió un error.';
                        if (res && res.demasiadas_filas) aviso('warning', 'Demasiados datos para ' + nombre, msg);
                        else aviso('error', 'No se pudo generar ' + titulo, msg);
                        return null;
                    });
                }
                if (!response.ok || tipo.indexOf('text/html') !== -1) {
                    aviso('error', 'No se pudo generar ' + titulo,
                        'El servidor no pudo armar el archivo. Si el reporte tiene muchos datos, acota los filtros (por ejemplo por año) y vuelve a intentarlo.');
                    return null;
                }
                return response.blob().then(function (blob) {
                    return {
                        blob: blob,
                        archivo: nombreArchivo(response.headers.get('Content-Disposition'), opciones.archivo || ('descarga' + extensionDe(tipo)))
                    };
                });
            })
            .catch(function (err) {
                console.error(err);
                aviso('error', 'Error de conexión', 'No se pudo comunicar con el servidor.');
                return null;
            });
    }

    function guardarBlob(blob, archivo) {
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = archivo;
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(function () { URL.revokeObjectURL(a.href); }, 1000);
    }

    window.CMG_descargar = function (url, opciones) {
        return obtenerArchivo(url, opciones).then(function (r) {
            if (!r) return;
            guardarBlob(r.blob, r.archivo);
            if (typeof Swal !== 'undefined') Swal.close();
        });
    };

    /* ------------------------------------------------------------------------
     * CMG_pdfDocumento(url, opciones): PDF de UN documento (retención, factura…).
     * Genera el PDF igual que CMG_descargar y, en vez de guardarlo sin más,
     * pregunta qué hacer: Imprimir (abre el cuadro de impresión con el PDF ya
     * cargado en un iframe oculto), Descargar o Ver (en otra pestaña).
     * La pregunta sale DESPUÉS de generar el PDF para que el clic del usuario
     * abra la pestaña de "Ver" sin que la frene el bloqueador de ventanas.
     * El navegador no permite imprimir sin mostrar su cuadro de impresión.
     * En celulares la impresión desde un iframe no funciona: "Imprimir" abre el
     * PDF en otra pestaña para imprimir o compartir desde ahí.
     * ---------------------------------------------------------------------- */
    var esMovil = /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent || '');
    var urlImpresion = null;

    function escHtml(t) {
        return String(t).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function verBlob(blob) {
        var u = URL.createObjectURL(blob);
        var w = window.open(u, '_blank');
        if (!w) {
            aviso('warning', 'Ventana bloqueada',
                'El navegador bloqueó la pestaña del PDF. Permite las ventanas emergentes para este sitio o usa "Descargar".');
            URL.revokeObjectURL(u);
            return;
        }
        // Para entonces la pestaña ya cargó el PDF.
        setTimeout(function () { URL.revokeObjectURL(u); }, 5 * 60 * 1000);
    }

    function imprimirBlob(blob) {
        if (esMovil) { verBlob(blob); return; }
        if (urlImpresion) URL.revokeObjectURL(urlImpresion);
        var u = urlImpresion = URL.createObjectURL(blob);

        var viejo = document.getElementById('cmg-print-pdf');
        if (viejo) viejo.remove();
        var ifr = document.createElement('iframe');
        ifr.id = 'cmg-print-pdf';
        ifr.setAttribute('aria-hidden', 'true');
        ifr.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;';
        ifr.onload = function () {
            // Breve espera: el visor de PDF del navegador termina de montarse después del load.
            setTimeout(function () {
                try {
                    ifr.contentWindow.focus();
                    ifr.contentWindow.print();
                } catch (e) {
                    console.error(e);
                    window.open(u, '_blank');
                }
            }, 300);
        };
        ifr.src = u;
        document.body.appendChild(ifr);
    }

    window.CMG_pdfDocumento = function (url, opciones) {
        opciones = Object.assign({ nombre: 'PDF', archivo: 'documento.pdf' }, opciones || {});
        return obtenerArchivo(url, opciones).then(function (r) {
            if (!r) return null;
            // Un endpoint que respondió 200 con un texto de error (die('...')) sin
            // Content-Type text/html llegaría aquí como "PDF": se comprueba la firma.
            return r.blob.slice(0, 5).text().then(function (cab) {
                if (cab.indexOf('%PDF') !== 0) {
                    return r.blob.slice(0, 300).text().then(function (txt) {
                        aviso('error', 'No se pudo generar el PDF', txt.replace(/<[^>]*>/g, ' ').trim() || 'El servidor no devolvió un PDF.');
                        return null;
                    });
                }
                return r;
            });
        }).then(function (r) {
            if (!r) return;
            // TCPDF en modo 'D' manda Content-Type application/force-download: con ese
            // tipo el iframe y la pestaña descargarían en vez de mostrar el PDF.
            r.blob = new Blob([r.blob], { type: 'application/pdf' });
            if (typeof Swal === 'undefined') { guardarBlob(r.blob, r.archivo); return; }

            function boton(accion, clase, icono, texto) {
                return '<button type="button" data-pdf-accion="' + accion + '" class="btn ' + clase +
                    ' d-flex flex-column align-items-center justify-content-center gap-1" style="width:100px;height:74px;">' +
                    '<i class="bi ' + icono + ' fs-4"></i><span class="small">' + texto + '</span></button>';
            }

            Swal.fire({
                title: 'PDF listo',
                html:
                    '<div class="text-muted small mb-3 text-truncate" title="' + escHtml(r.archivo) + '">' + escHtml(r.archivo) + '</div>' +
                    '<div class="d-flex justify-content-center gap-2">' +
                        boton('imprimir', 'btn-primary', 'bi-printer', 'Imprimir') +
                        boton('descargar', 'btn-outline-secondary', 'bi-download', 'Descargar') +
                        boton('ver', 'btn-outline-secondary', 'bi-box-arrow-up-right', 'Ver') +
                    '</div>',
                showConfirmButton: false,
                showCloseButton: true,
                width: 420,
                didOpen: function (popup) {
                    popup.querySelector('[data-pdf-accion="imprimir"]').focus();
                    popup.querySelectorAll('[data-pdf-accion]').forEach(function (b) {
                        b.addEventListener('click', function () {
                            var accion = b.getAttribute('data-pdf-accion');
                            Swal.close();
                            // Dentro del mismo clic: window.open necesita el gesto del usuario.
                            if (accion === 'imprimir') imprimirBlob(r.blob);
                            else if (accion === 'ver') verBlob(r.blob);
                            else guardarBlob(r.blob, r.archivo);
                        });
                    });
                }
            });
        });
    };

    // Enlaces a un PDF de documento armados como HTML (filas de tablas dentro de
    // modales, pestañas de fichas, celdas que devuelve el servidor): basta marcarlos
    // con data-pdf-documento para que pregunten Imprimir / Descargar / Ver.
    // En fase de captura porque varios llevan onclick="event.stopPropagation()"
    // (para no disparar el clic de la fila) y así el aviso no llegaría al document.
    document.addEventListener('click', function (e) {
        if (e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
        var a = e.target.closest ? e.target.closest('a[data-pdf-documento][href]') : null;
        if (!a) return;
        e.preventDefault();
        window.CMG_pdfDocumento(a.href);
    }, true);

    document.addEventListener('click', function (e) {
        if (e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
        var a = e.target.closest ? e.target.closest('a[href]') : null;
        if (!a || a.hasAttribute('data-descarga-directa') || a.hasAttribute('download')) return;
        var href = a.getAttribute('href') || '';
        if (!/\/export-(pdf|excel)(\?|$)/i.test(href)) return;
        if (a.classList.contains('disabled') || a.getAttribute('aria-disabled') === 'true') return;
        e.preventDefault();
        window.CMG_descargar(a.href);
    });
})();
