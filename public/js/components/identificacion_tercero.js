/* ═══════════════════════════════════════════════════════════════════════════
   IdentificacionTercero — espejo en JavaScript de App\Helpers\IdentificacionTercero.

   El mismo contribuyente puede estar registrado dos veces: una con la CÉDULA
   (10 dígitos) y otra con el RUC (13 dígitos = esa cédula + '001'). Sirve para
   que las agrupaciones que se arman en el navegador (vista agrupada por cliente,
   envío masivo de correo, buscadores) traten esos dos registros como UNO.

   Si se cambia la regla aquí, cambiarla también en el helper PHP.
   ═══════════════════════════════════════════════════════════════════════════ */
(function (global) {
    'use strict';

    /** Clave con la que dos registros del mismo tercero se reconocen entre sí. */
    function claveBase(identificacion) {
        const ide = String(identificacion ?? '').trim();
        if (ide === '') return '';
        if (/^\d{13}$/.test(ide) && ide.slice(-3) === '001') return ide.slice(0, 10);
        return ide;
    }

    /**
     * Clave de agrupación de una fila: la clave base si hay identificación y, si no,
     * un valor propio de la fila (nombre o id) para no juntar a todos los terceros
     * sin identificación en un mismo grupo.
     */
    function claveGrupo(identificacion, respaldo) {
        const base = claveBase(identificacion);
        return base !== '' ? 'i:' + base : 'r:' + (respaldo || '?');
    }

    global.IdentificacionTercero = { claveBase, claveGrupo };
})(window);
