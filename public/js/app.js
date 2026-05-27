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
            if (!form.contains(e.target) && !dropdown.contains(e.target)) hideDropdown();
        });
    });
})();
