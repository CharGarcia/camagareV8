/**
 * Clientes → «QR de registro»: muestra el QR fijo de la empresa, deja personalizar el
 * título y el mensaje del impreso, activarlo/desactivarlo e imprimirlo.
 * Vista: app/views/modulos/clientes/modal_qr_registro.php
 */
(function () {
    'use strict';
    if (window.__CMG_clientesQr) return;
    window.__CMG_clientesQr = true;

    const url = () => window.CLIQR_URL;
    const $ = (id) => document.getElementById(id);
    let modal = null;
    let guardando = false;

    function mostrar(estado, msg) {
        $('cliqrCargando').classList.toggle('d-none', estado !== 'cargando');
        $('cliqrError').classList.toggle('d-none', estado !== 'error');
        $('cliqrContenido').classList.toggle('d-none', estado !== 'ok');
        if (estado === 'error') $('cliqrError').textContent = msg || 'No se pudo cargar el QR.';
    }

    function pintar(d) {
        if (!d.existe) {
            mostrar('error', 'Aún no se ha generado el QR de registro de esta empresa. Pida a un usuario con permiso de crear o modificar clientes que lo abra.');
            $('cliqrImprimir').classList.add('d-none');
            $('cliqrGuardar').classList.add('d-none');
            return;
        }
        $('cliqrImg').src = `${url()}/qrRegistroSvg?t=${Date.now()}`;
        $('cliqrUrl').value = d.url;
        $('cliqrTitulo').value = d.titulo || '';
        $('cliqrMensaje').value = d.mensaje || '';
        $('cliqrActivo').checked = !!d.activo;

        const badge = $('cliqrBadge');
        badge.className = 'badge border ' + (d.activo
            ? 'bg-success bg-opacity-10 text-success border-success border-opacity-25'
            : 'bg-secondary bg-opacity-10 text-secondary border-secondary border-opacity-25');
        badge.textContent = d.activo ? 'Activo' : 'Inactivo';

        const r = d.resumen || {};
        $('cliqrResumen').textContent = `Registros por QR: ${r.total || 0} (hoy ${r.hoy || 0}) · nuevos ${r.creados || 0} · actualizados ${r.actualizados || 0}`;

        ['cliqrTitulo', 'cliqrMensaje', 'cliqrActivo'].forEach(id => { $(id).disabled = !d.puedeEditar; });
        $('cliqrGuardar').classList.toggle('d-none', !d.puedeEditar);
        $('cliqrImprimir').classList.remove('d-none');
        mostrar('ok');
    }

    window.CLIQR_abrir = async function () {
        modal = modal || new bootstrap.Modal($('modalClientesQr'));
        mostrar('cargando');
        $('cliqrImprimir').classList.add('d-none');
        $('cliqrGuardar').classList.add('d-none');
        modal.show();
        try {
            const r = await fetch(`${url()}/qrRegistroAjax`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const d = await r.json();
            if (!d.ok) { mostrar('error', d.error); return; }
            pintar(d);
        } catch (e) {
            mostrar('error', 'Error de conexión al cargar el QR.');
        }
    };

    async function guardar() {
        if (guardando) return;
        guardando = true;
        const btn = $('cliqrGuardar');
        btn.disabled = true;
        try {
            const fd = new FormData();
            fd.append('titulo', $('cliqrTitulo').value);
            fd.append('mensaje', $('cliqrMensaje').value);
            fd.append('activo', $('cliqrActivo').checked ? '1' : '0');
            const r = await fetch(`${url()}/guardarQrRegistroAjax`, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const d = await r.json();
            if (!d.ok) {
                Swal.fire({ icon: 'error', title: 'No se guardó', text: d.error || 'Error al guardar.' });
                return;
            }
            pintar(d);
            Swal.fire({ icon: 'success', title: 'Guardado', text: d.msg, timer: 1300, showConfirmButton: false });
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Error de conexión.' });
        } finally {
            guardando = false;
            btn.disabled = false;
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        $('cliqrGuardar')?.addEventListener('click', guardar);
        $('cliqrImprimir')?.addEventListener('click', () => window.CMG_pdfDocumento(`${url()}/qrRegistroPdf`));
        $('cliqrCopiar')?.addEventListener('click', async () => {
            const v = $('cliqrUrl').value;
            try { await navigator.clipboard.writeText(v); } catch (e) { $('cliqrUrl').select(); document.execCommand('copy'); }
            const b = $('cliqrCopiar');
            b.innerHTML = '<i class="bi bi-check2"></i>';
            setTimeout(() => { b.innerHTML = '<i class="bi bi-clipboard"></i>'; }, 1500);
        });
    });
})();
