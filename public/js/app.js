/**
 * JavaScript propio del MVC
 */

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
            fetch(baseUrl + '/empresa/getEmpresasAsignadasAjax')
                .then(r => r.json())
                .then(res => {
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
                            div.textContent = emp.texto;
                            dropdown.appendChild(div);
                        });

                        // Agregar event listeners a los nuevos items
                        dropdown.querySelectorAll('.cmg-empresas-dropdown-item').forEach(function(item) {
                            item.addEventListener('mousedown', function(e) {
                                e.preventDefault();
                                selectItem(item);
                            });
                        });

                        // Actualizar la referencia a items
                        items = dropdown.querySelectorAll('.cmg-empresas-dropdown-item');
                    }
                });
        }

        // Si no hay items O el input está vacío pero hay sesión guardada, cargar AJAX
        if ((items.length === 0 || input.value.trim() === '') && storedEmpresaId) {
            cargarEmpresasAjax();
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
            var count = 0;
            
            for (var i = 0; i < items.length; i++) {
                var item = items[i];
                var text = (item.getAttribute('data-text') || '').toLowerCase();
                var ruc = (item.getAttribute('data-ruc') || '').toLowerCase();
                var isMatch = false;

                if (q === '') {
                    // Si no hay filtro o se ignora, mostramos los 10 primeros
                    isMatch = (count < 10);
                } else {
                    // Si hay filtro, buscamos en texto o RUC y limitamos a 10
                    if (text.indexOf(q) !== -1 || ruc.indexOf(q) !== -1) {
                        isMatch = (count < 10);
                    }
                }

                if (isMatch) {
                    item.style.setProperty('display', 'block', 'important');
                    count++;
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

        input.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' || e.key === 'Delete') {
                input.value = '';
                filterItems();
                e.preventDefault();
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

            // Si el input está vacío pero hay un valor guardado, RESTAURAR INMEDIATAMENTE
            if (currentInputValue === '' && storedText && storedId) {
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
    });
})();
