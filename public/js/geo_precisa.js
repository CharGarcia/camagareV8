/**
 * CMG_Geo — ubicación precisa del navegador (compartido por Entregas de consignaciones,
 * Clientes y Proveedores). Cargado globalmente desde partials/head.php.
 *
 * Por qué no getCurrentPosition(): devuelve la PRIMERA lectura disponible, que casi
 * siempre es la aproximada por red/WiFi/IP (cientos de metros) porque el GPS todavía no
 * fija satélites; con maximumAge > 0 incluso puede devolver una lectura vieja en caché.
 * Aquí se muestrea con watchPosition (alta precisión, sin caché) hasta lograr la precisión
 * objetivo o agotar el tiempo máximo, y se devuelve la lectura MÁS PRECISA recibida.
 *
 * Uso:
 *   const { ubic, errorCode } = await CMG_Geo.obtener({ onLectura: (u) => … });
 *   // ubic: { lat, lon, precision } | null   ·   errorCode: 1 permiso, 2 no disponible,
 *   // 3 tiempo agotado, 0 navegador sin geolocalización; null si hubo ubicación.
 *   if (ubic && CMG_Geo.esAproximada(ubic)) { …avisar… }
 */
(function () {
    const PRECISION_OBJETIVO = 20;    // m: con esta precisión se deja de muestrear
    const TIEMPO_MAX_MS      = 20000; // tope de espera
    const PRECISION_AVISO    = 100;   // m: por encima se considera "aproximada"

    function obtener(opts) {
        opts = opts || {};
        const objetivo  = opts.objetivo  || PRECISION_OBJETIVO;
        const tiempoMax = opts.tiempoMax || TIEMPO_MAX_MS;
        const onLectura = typeof opts.onLectura === 'function' ? opts.onLectura : null;

        return new Promise((resolve) => {
            if (!navigator.geolocation) return resolve({ ubic: null, errorCode: 0 });

            let mejor = null;
            let ultimoError = null;
            let terminado = false;
            let watchId = null;
            let timer = null;

            const terminar = () => {
                if (terminado) return;
                terminado = true;
                if (watchId !== null) navigator.geolocation.clearWatch(watchId);
                clearTimeout(timer);
                resolve({ ubic: mejor, errorCode: mejor ? null : (ultimoError ?? 3) });
            };

            watchId = navigator.geolocation.watchPosition(
                (pos) => {
                    const precision = (pos.coords.accuracy != null) ? Math.round(pos.coords.accuracy) : null;
                    if (!mejor || (precision != null && (mejor.precision == null || precision < mejor.precision))) {
                        mejor = { lat: pos.coords.latitude, lon: pos.coords.longitude, precision };
                        if (onLectura) {
                            try { onLectura(mejor); } catch (e) { console.error(e); }
                        }
                    }
                    if (precision != null && precision <= objetivo) terminar();
                },
                (err) => {
                    ultimoError = err ? err.code : 2;
                    // Permiso denegado: no tiene sentido seguir esperando. Los demás errores
                    // (timeout/no disponible) pueden recuperarse en la siguiente lectura.
                    if (ultimoError === 1) terminar();
                },
                { enableHighAccuracy: true, timeout: tiempoMax, maximumAge: 0 }
            );
            timer = setTimeout(terminar, tiempoMax);
        });
    }

    function esAproximada(ubic) {
        return !!ubic && ubic.precision != null && ubic.precision > PRECISION_AVISO;
    }

    function mensajeError(code) {
        return ({
            0: 'Su navegador no soporta geolocalización.',
            1: 'Permiso de ubicación denegado.',
            2: 'Ubicación no disponible.',
            3: 'Tiempo agotado al obtener la ubicación.',
        })[code] || 'Error al obtener GPS.';
    }

    window.CMG_Geo = { obtener, esAproximada, mensajeError, PRECISION_OBJETIVO, TIEMPO_MAX_MS, PRECISION_AVISO };
})();
