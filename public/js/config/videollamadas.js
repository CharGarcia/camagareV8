/**
 * Configuración de videollamadas (/config/videollamadas).
 *
 * Los secretos no se muestran nunca: si el campo va vacío, el servidor conserva
 * el valor guardado. Para quitarlo hay un botón de papelera por campo.
 *
 * El token CSRF lo adjunta public/js/csrf.js envolviendo fetch: aquí no hay que
 * hacer nada al respecto.
 */
(function () {
    'use strict';

    const URL_BASE = window.VCFG_URL;

    const val = (id) => document.getElementById(id)?.value ?? '';

    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => (
        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
    ));

    function avisar(icono, titulo, texto) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: icono, title: titulo, text: texto, confirmButtonColor: '#0d6efd' });
        } else {
            alert(titulo + (texto ? '\n\n' + texto : ''));
        }
    }

    async function enviar(accion, fd) {
        try {
            const res = await (await fetch(`${URL_BASE}?action=${accion}`, { method: 'POST', body: fd })).json();
            if (!res.ok) { avisar('error', 'No se pudo guardar', res.mensaje); return; }

            if (typeof Swal !== 'undefined') {
                await Swal.fire({ icon: 'success', title: res.mensaje, timer: 1200, showConfirmButton: false });
            }
            // Se recarga para que el resumen de "en uso" refleje la cascada.
            location.reload();
        } catch (e) {
            avisar('error', 'Error de conexión', 'No se pudo guardar la configuración.');
        }
    }

    window.VCFG_guardarGlobal = function (extra) {
        const fd = new FormData();
        fd.append('stun_urls', val('cfgg-stun').trim());
        fd.append('turn_urls', val('cfgg-turn-urls').trim());
        fd.append('turn_usuario', val('cfgg-turn-usuario').trim());
        fd.append('turn_credencial', val('cfgg-turn-credencial'));
        fd.append('turn_key_id', val('cfgg-turn-key-id').trim());
        fd.append('turn_api_token', val('cfgg-turn-api-token'));
        fd.append('max_participantes_defecto', val('cfgg-max-def'));
        fd.append('duracion_max_defecto', val('cfgg-dur-def'));
        if (document.getElementById('cfgg-override').checked) fd.append('permite_override_empresa', '1');
        if (extra) fd.append(extra, '1');

        enviar('guardarGlobal', fd);
    };

    window.VCFG_guardarEmpresa = function (extra) {
        const fd = new FormData();
        fd.append('max_participantes', val('cfg-max'));
        fd.append('duracion_max_minutos', val('cfg-duracion'));
        fd.append('umbral_proveedor_externo', val('cfg-umbral'));
        fd.append('stun_urls', val('cfg-stun').trim());
        fd.append('turn_urls', val('cfg-turn-urls').trim());
        fd.append('turn_usuario', val('cfg-turn-usuario').trim());
        fd.append('turn_credencial', val('cfg-turn-credencial'));
        fd.append('turn_key_id', val('cfg-turn-key-id').trim());
        fd.append('turn_api_token', val('cfg-turn-api-token'));
        if (extra) fd.append(extra, '1');

        enviar('guardar', fd);
    };

    window.VCFG_borrarSecreto = async function (campo, esGlobal) {
        if (typeof Swal !== 'undefined') {
            const r = await Swal.fire({
                icon: 'warning',
                title: '¿Borrar el dato guardado?',
                text: esGlobal
                    ? 'Afecta a todas las empresas que heredan esta configuración.'
                    : 'Esta empresa volverá a usar los servidores globales.',
                showCancelButton: true,
                confirmButtonText: 'Borrar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545',
            });
            if (!r.isConfirmed) return;
        }

        esGlobal
            ? window.VCFG_guardarGlobal('borrar_' + campo)
            : window.VCFG_guardarEmpresa('borrar_' + campo);
    };

    window.VCFG_probar = async function () {
        const caja = document.getElementById('cfg-resultado-prueba');
        caja.innerHTML = '<span class="text-muted"><span class="spinner-border spinner-border-sm me-1"></span>Consultando...</span>';

        try {
            const res = await (await fetch(`${URL_BASE}?action=probar`)).json();
            if (!res.ok) {
                caja.innerHTML = `<span class="text-danger">${esc(res.mensaje)}</span>`;
                return;
            }
            const clase = res.turn > 0 ? 'text-success' : 'text-warning';
            caja.innerHTML =
                `<span class="${clase}"><strong>${res.stun}</strong> STUN y <strong>${res.turn}</strong> TURN disponibles.` +
                (res.aviso ? ' ' + esc(res.aviso) : '') + '</span>';
        } catch (e) {
            caja.innerHTML = '<span class="text-danger">No se pudo consultar.</span>';
        }
    };

})();
