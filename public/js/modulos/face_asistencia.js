/*
 * Helper de reconocimiento facial para Control de Asistencia (face-api.js).
 *
 * Requiere que la página cargue antes la librería face-api.js (global `faceapi`).
 * Los modelos (~6.8 MB: tinyFaceDetector, faceLandmark68Net y faceRecognitionNet) están
 * AUTOALOJADOS en public/models/face y la ruta se deriva del src de este mismo script,
 * así ninguna vista tiene que configurarla ni el celular depende de un CDN externo.
 * Para apuntar a otro sitio, define antes de cargar este archivo:
 *   window.CASIS_FACE_MODELS = '<BASE_URL>/models/face';
 * (ver public/models/face/README.txt)
 */
window.CASIS_FACE = (function () {
    'use strict';

    /**
     * Carpeta de los pesos autoalojados: se saca del src de este script
     * (<base>/js/modulos/face_asistencia.js → <base>/models/face) para no depender de
     * BASE_URL en cada vista. Si el src no se puede leer, cae al CDN público.
     */
    function rutaModelos() {
        try {
            const src = (document.currentScript && document.currentScript.src) || '';
            const m = src.match(/^(.*)\/js\/modulos\/face_asistencia\.js(?:[?#].*)?$/);
            if (m) return m[1] + '/models/face';
        } catch (e) {}
        return 'https://cdn.jsdelivr.net/gh/vladmandic/face-api/model';
    }

    const MODELS = window.CASIS_FACE_MODELS || rutaModelos();
    const THRESHOLD = 0.55; // distancia euclídea máxima para considerar "misma persona"
    let loaded = false;

    async function loadModels() {
        if (loaded) return;
        if (typeof faceapi === 'undefined') throw new Error('Librería facial no disponible (face-api.js).');
        await faceapi.nets.tinyFaceDetector.loadFromUri(MODELS);
        await faceapi.nets.faceLandmark68Net.loadFromUri(MODELS);
        await faceapi.nets.faceRecognitionNet.loadFromUri(MODELS);
        loaded = true;
    }

    /** Devuelve el descriptor (array de 128 números) del rostro en el elemento, o null. */
    async function descriptor(el) {
        const opts = new faceapi.TinyFaceDetectorOptions({ inputSize: 320, scoreThreshold: 0.5 });
        const r = await faceapi.detectSingleFace(el, opts).withFaceLandmarks().withFaceDescriptor();
        return r ? Array.from(r.descriptor) : null;
    }

    /** Promedia varios descriptores (mejora la robustez del enrolamiento). */
    function promediar(lista) {
        if (!lista.length) return null;
        const n = lista[0].length;
        const out = new Array(n).fill(0);
        lista.forEach(d => { for (let i = 0; i < n; i++) out[i] += d[i]; });
        for (let i = 0; i < n; i++) out[i] /= lista.length;
        return out;
    }

    function distancia(a, b) {
        return faceapi.euclideanDistance(a, b);
    }

    return { loadModels, descriptor, promediar, distancia, THRESHOLD };
})();
