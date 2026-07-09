/* Control de Asistencia — Puntos de servicio (gestión + QR). */
(function () {
    'use strict';

    const urlBase = window.CASIS_URL_BASE;
    const perm = window.CASIS_PERM || {};
    let modalPunto = null;
    let modalQr = null;
    let qrPuntoActual = null; // { id, nombre, qr_token }

    const swalOk = (msg) => window.Swal
        ? Swal.fire({ icon: 'success', title: msg, timer: 1300, showConfirmButton: false })
        : alert(msg);
    const swalErr = (msg) => window.Swal
        ? Swal.fire({ icon: 'error', title: 'Error', text: msg })
        : alert(msg);

    function getModalPunto() {
        if (!modalPunto && typeof bootstrap !== 'undefined') modalPunto = new bootstrap.Modal(document.getElementById('modalPunto'));
        return modalPunto;
    }
    function getModalQr() {
        if (!modalQr && typeof bootstrap !== 'undefined') modalQr = new bootstrap.Modal(document.getElementById('modalCasisQr'));
        return modalQr;
    }

    function setForm(d) {
        document.getElementById('punto_id').value        = d.id || '';
        document.getElementById('punto_nombre').value    = d.nombre || '';
        document.getElementById('punto_direccion').value = d.direccion || '';
        document.getElementById('punto_latitud').value   = d.latitud || '';
        document.getElementById('punto_longitud').value  = d.longitud || '';
        document.getElementById('punto_radio_m').value   = d.radio_m || 150;
        document.getElementById('punto_estado').value    = d.estado || 'activo';
        document.getElementById('punto_exige_gps').checked = (d.exige_gps === undefined) ? true : (d.exige_gps === true || d.exige_gps === 't' || d.exige_gps === '1' || d.exige_gps === 1);
        document.getElementById('punto_geo_msg').textContent = '';
    }

    window.abrirModalCrearPunto = function () {
        document.getElementById('puntoModalTitulo').textContent = 'Nuevo punto de servicio';
        document.getElementById('btnEliminarPunto').style.display = 'none';
        setForm({});
        getModalPunto()?.show();
    };

    window.abrirModalEditarPunto = function (tr) {
        let d = {};
        try { d = JSON.parse(tr.getAttribute('data-row')); } catch (e) {}
        document.getElementById('puntoModalTitulo').textContent = 'Editar punto de servicio';
        document.getElementById('btnEliminarPunto').style.display = perm.eliminar ? '' : 'none';
        setForm(d);
        getModalPunto()?.show();
    };

    window.usarMiUbicacionPunto = function () {
        const msg = document.getElementById('punto_geo_msg');
        if (!navigator.geolocation) { msg.textContent = 'GPS no disponible en este dispositivo.'; return; }
        msg.textContent = 'Obteniendo ubicación...';
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                document.getElementById('punto_latitud').value = pos.coords.latitude.toFixed(7);
                document.getElementById('punto_longitud').value = pos.coords.longitude.toFixed(7);
                msg.textContent = 'Ubicación tomada (±' + Math.round(pos.coords.accuracy) + ' m).';
            },
            () => { msg.textContent = 'No se pudo obtener la ubicación.'; },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    };

    window.guardarPunto = function () {
        const id = document.getElementById('punto_id').value.trim();
        const fd = new FormData();
        if (id) fd.append('id', id);
        fd.append('nombre',      document.getElementById('punto_nombre').value.trim());
        fd.append('direccion',   document.getElementById('punto_direccion').value.trim());
        fd.append('latitud',     document.getElementById('punto_latitud').value.trim());
        fd.append('longitud',    document.getElementById('punto_longitud').value.trim());
        fd.append('radio_m',     document.getElementById('punto_radio_m').value.trim());
        fd.append('estado',      document.getElementById('punto_estado').value);
        if (document.getElementById('punto_exige_gps').checked) fd.append('exige_gps', '1');

        const url = id ? `${urlBase}/update` : `${urlBase}/store`;
        fetch(url, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(j => {
                if (j.ok) {
                    getModalPunto()?.hide();
                    swalOk(j.msg);
                    window.dispatchEvent(new CustomEvent('puntoGuardado'));
                } else swalErr(j.error);
            })
            .catch(() => swalErr('Error de red.'));
    };

    window.eliminarPunto = function (id) {
        const doDelete = () => {
            const fd = new FormData(); fd.append('id_eliminar', id);
            fetch(`${urlBase}/delete`, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(j => {
                    if (j.ok) { swalOk(j.msg); window.dispatchEvent(new CustomEvent('puntoGuardado')); }
                    else swalErr(j.error);
                });
        };
        if (window.Swal) {
            Swal.fire({ icon: 'warning', title: '¿Eliminar punto?', text: 'El QR asociado dejará de funcionar.', showCancelButton: true, confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545' })
                .then(res => { if (res.isConfirmed) doDelete(); });
        } else if (confirm('¿Eliminar punto?')) doDelete();
    };

    window.eliminarPuntoDesdeModal = function () {
        const id = document.getElementById('punto_id').value.trim();
        if (id) { getModalPunto()?.hide(); window.eliminarPunto(id); }
    };

    // ---- QR ----------------------------------------------------------
    function pintarQr(nombre, token) {
        const contenido = `${window.BASE_URL.replace(/\/$/, '')}/asistencia/marcar?p=${encodeURIComponent(token)}`;
        const img = `https://api.qrserver.com/v1/create-qr-code/?data=${encodeURIComponent(contenido)}&size=300x300&margin=10`;

        document.getElementById('casisQrNombre').textContent = nombre;
        document.getElementById('casisQrTexto').textContent = contenido;
        document.getElementById('casisPrintNombre').textContent = nombre;

        const spinner = document.getElementById('casisQrSpinner');
        const el = document.getElementById('casisQrImg');
        spinner.classList.remove('d-none');
        el.classList.add('d-none');
        el.onload = () => { spinner.classList.add('d-none'); el.classList.remove('d-none'); };
        el.src = img;
        document.getElementById('casisPrintImg').src = img;
    }

    window.verQrPunto = function (id) {
        fetch(`${urlBase}/getDetalleAjax?id=${id}`)
            .then(r => r.json())
            .then(j => {
                if (!j.ok) { swalErr(j.error || 'No encontrado'); return; }
                qrPuntoActual = { id: j.data.id, nombre: j.data.nombre, qr_token: j.data.qr_token };
                pintarQr(j.data.nombre, j.data.qr_token);
                getModalQr()?.show();
            })
            .catch(() => swalErr('Error de red.'));
    };

    window.casisCopiarQr = function () {
        const txt = document.getElementById('casisQrTexto').textContent;
        if (navigator.clipboard) navigator.clipboard.writeText(txt).then(() => swalOk('Copiado'));
    };

    window.casisRegenerarQr = function () {
        if (!qrPuntoActual) return;
        const run = () => {
            const fd = new FormData(); fd.append('id', qrPuntoActual.id);
            fetch(`${urlBase}/regenerarQr`, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(j => {
                    if (j.ok) { qrPuntoActual.qr_token = j.qr_token; pintarQr(qrPuntoActual.nombre, j.qr_token); swalOk(j.msg); }
                    else swalErr(j.error);
                });
        };
        if (window.Swal) {
            Swal.fire({ icon: 'warning', title: '¿Regenerar QR?', text: 'El QR impreso anterior dejará de funcionar.', showCancelButton: true, confirmButtonText: 'Regenerar', cancelButtonText: 'Cancelar' })
                .then(res => { if (res.isConfirmed) run(); });
        } else if (confirm('¿Regenerar QR?')) run();
    };
})();
