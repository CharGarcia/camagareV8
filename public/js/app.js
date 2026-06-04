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

        // Restaurar empresa desde sessionStorage si el servidor no la renderizó correctamente
        var storedEmpresaId = sessionStorage.getItem('cmg-empresa-id');
        var storedEmpresaText = sessionStorage.getItem('cmg-empresa-text');
        var currentId = idInput ? idInput.value : '';

        // Si el input está vacío pero hay un idInput válido, restaurar desde data-text del item
        if (input.value.trim() === '' && (currentId || storedEmpresaId)) {
            var selectedId = currentId || storedEmpresaId;
            for (var i = 0; i < items.length; i++) {
                if (items[i].getAttribute('data-id') === selectedId) {
                    var text = items[i].getAttribute('data-text');
                    if (text) {
                        input.value = text;
                        if (idInput) idInput.value = selectedId;
                        if (rucInput && items[i].getAttribute('data-ruc')) {
                            rucInput.value = items[i].getAttribute('data-ruc');
                        }
                    }
                    break;
                }
            }
        }

        // Si todavía hay valor en input, guardar en sessionStorage para recuperar en navegación
        if (input.value.trim() !== '' && idInput && idInput.value) {
            sessionStorage.setItem('cmg-empresa-id', idInput.value);
            sessionStorage.setItem('cmg-empresa-text', input.value);
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
    });
})();
