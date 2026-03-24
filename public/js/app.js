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

        function showDropdown() {
            filterItems();
            var portal = document.getElementById('cmg-dropdown-portal');
            (portal || document.body).appendChild(dropdown);
            var rect = input.getBoundingClientRect();
            dropdown.style.left = rect.left + 'px';
            dropdown.style.top = (rect.bottom + 2) + 'px';
            dropdown.style.width = rect.width + 'px';
            dropdown.classList.add('cmg-empresas-dropdown-open');
        }
        function hideDropdown() {
            dropdown.classList.remove('cmg-empresas-dropdown-open');
            form.appendChild(dropdown);
        }
        function filterItems() {
            var q = (input.value || '').trim().toLowerCase();
            items.forEach(function(item) {
                var text = (item.getAttribute('data-text') || '').toLowerCase();
                item.style.display = !q || text.indexOf(q) !== -1 ? '' : 'none';
            });
        }
        function selectItem(item) {
            var id = item.getAttribute('data-id');
            var text = item.getAttribute('data-text');
            var ruc = item.getAttribute('data-ruc') || '';
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
        input.addEventListener('input', filterItems);
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
