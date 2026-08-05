/**
 * Bloqueo de edición concurrente entre módulos (genérico, reutilizable).
 *
 * Uso típico al abrir un modal de edición/uso de un registro compartido:
 *
 *   CMG_Bloqueo.iniciar({
 *       urlBase: RUTA_MODULO_PEDIDOS,      // ej. window.CMG_urlBase del módulo dueño de la tabla
 *       idRegistro: id,
 *       moduloContexto: 'pedidos',          // opcional: qué módulo está tomando el lock
 *       onBloqueado: (info) => { ... mostrar aviso de solo-lectura ... },
 *       onPerdido: () => { ... avisar que se perdió el control (expiró) ... }
 *   });
 *
 * Al cerrar el modal (Cancelar, Guardar, X, Escape):
 *
 *   CMG_Bloqueo.detener();
 *
 * Cada `iniciar()` reemplaza el bloqueo activo anterior (un solo lock a la vez por vista).
 */
const CMG_Bloqueo = (() => {
    let activo = null; // { urlBase, idRegistro, intervalId }

    const PING_MS = 30000;

    async function iniciar({ urlBase, idRegistro, moduloContexto = null, onBloqueado = null, onPerdido = null }) {
        // Ya se tiene EXACTAMENTE este lock (mismo módulo + registro): no soltarlo y volver
        // a tomarlo. Re-tomar aquí (p. ej. al cambiar la bodega en "Cargar desde Pedido", que
        // vuelve a llamar iniciar() para el mismo pedido) disparaba un "liberar" viejo sin
        // esperar en paralelo con el "tomar" nuevo; si el liberar llegaba después al servidor,
        // borraba el lock recién tomado y el ping siguiente reportaba "perdiste el control"
        // aunque ningún otro usuario lo hubiera tocado. El ping periódico ya mantiene vivo este.
        if (activo && activo.urlBase === urlBase && String(activo.idRegistro) === String(idRegistro)) {
            return { tomado: true, propio: true };
        }

        await detener(); // libera cualquier lock previo de esta vista antes de tomar uno nuevo

        const body = new URLSearchParams({ id_registro: idRegistro });
        if (moduloContexto) body.append('modulo_contexto', moduloContexto);

        let res;
        try {
            const resp = await fetch(`${urlBase}/bloquearAjax`, { method: 'POST', body });
            res = await resp.json();
        } catch (e) {
            console.error('CMG_Bloqueo.iniciar: error de red', e);
            return { tomado: false, propio: false };
        }

        if (res.tomado) {
            const intervalId = setInterval(async () => {
                try {
                    const pingBody = new URLSearchParams({ id_registro: idRegistro });
                    const respPing = await fetch(`${urlBase}/renovarBloqueoAjax`, { method: 'POST', body: pingBody });
                    const dataPing = await respPing.json();
                    if (dataPing.ok && dataPing.vigente === false) {
                        detener(false);
                        if (typeof onPerdido === 'function') onPerdido();
                    }
                } catch (e) {
                    // Fallo de red puntual: no se libera de inmediato, se reintenta en el próximo ping.
                }
            }, PING_MS);

            activo = { urlBase, idRegistro, intervalId };
        } else if (typeof onBloqueado === 'function') {
            onBloqueado(res);
        }

        return res;
    }

    function detener(liberarEnServidor = true) {
        if (!activo) return Promise.resolve();
        clearInterval(activo.intervalId);

        let promesa = Promise.resolve();
        if (liberarEnServidor) {
            const body = new URLSearchParams({ id_registro: activo.idRegistro });
            // Se espera (desde iniciar()) para garantizar que este DELETE llegue al servidor
            // ANTES del próximo "tomar": si no, puede llegar tarde y borrar el lock recién
            // tomado (ver comentario en iniciar()). Sigue sin bloquear a quien la llama sin
            // esperar (p. ej. el cierre del modal), gracias al valor de retorno opcional.
            promesa = fetch(`${activo.urlBase}/liberarBloqueoAjax`, { method: 'POST', body }).catch(() => {});
        }
        activo = null;
        return promesa;
    }

    return { iniciar, detener };
})();

window.CMG_Bloqueo = CMG_Bloqueo;
