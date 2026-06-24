/**
 * sri_scraper.js — Descarga de comprobantes recibidos SRI via Playwright + Stealth
 * Emite eventos de progreso en tiempo real mediante JSON.
 */

'use strict';

const { chromium } = require('playwright-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
chromium.use(StealthPlugin());

const path = require('path');
const fs = require('fs');

const DEBUG_DIR = path.join(__dirname, '..', 'public', 'sri_debug');
if (!fs.existsSync(DEBUG_DIR)) fs.mkdirSync(DEBUG_DIR, { recursive: true });

let _screenshotIdx = 0;
let _currentRuc = 'default';
let globalResultados = [];
// Cuando el llamador PHP pide streaming, cada XML se emite apenas se descarga
// (type:'xml') para que se registre de inmediato en la BD; así, si el proceso se
// interrumpe en 30/56, esos 30 quedan guardados. En este modo el 'finish' NO
// reenvía los XML (solo conteos) para no transferir el payload dos veces.
let _streamXml = false;

async function screenshot(pageOrFrame, nombre, ctxFrame = null) {
    try {
        _screenshotIdx++;
        const n    = String(_screenshotIdx).padStart(2, '0');
        const base = `${_currentRuc}_${n}_${nombre}`;
        const file = path.join(DEBUG_DIR, `${base}.png`);

        await pageOrFrame.screenshot({ path: file, fullPage: true });
        debugLog(`📸 Screenshot: ${path.basename(file)}`);

        if (ctxFrame && ctxFrame !== pageOrFrame) {
            try {
                const html = await ctxFrame.content();
                fs.writeFileSync(path.join(DEBUG_DIR, `${base}_iframe.html`), html, 'utf8');
            } catch (_) {}
        }
    } catch (e) {
        debugLog(`Screenshot error (${nombre}): ${e.message}`);
    }
}

const BASE           = 'https://srienlinea.sri.gob.ec';
const COMP_PATH      = '/comprobantes-electronicos-internet/pages/consultas/recibidos/comprobantesRecibidos.jsf';
const PORTAL_URL     = BASE + '/sri-en-linea/inicio/NAT';
const MAX_PAGINAS    = 10;

const RECAPTCHA_SITEKEY = '6LdukTQsAAAAAIcciM4GZq4ibeyplUhmWvlScuQE';
const RECAPTCHA_ACTION  = 'consulta_cel_recibidos';

const TEXTOS_CRED_INVALIDAS = [
    'credenciales inv', 'usuario o contrase', 'acceso no autorizado',
    'invalid credentials', 'contraseña incorrecta', 'usuario incorrecto',
    'authentication failed', 'invalid_grant',
];

let _browser = null;
let _context = null;
process.on('exit', () => {
    if (_context) _context.close().catch(() => {});
    if (_browser) _browser.close().catch(() => {});
});

async function main(config) {
    _currentRuc = config.usuario;

    // Limpiar capturas previas SOLO de esta empresa
    try {
        fs.readdirSync(DEBUG_DIR)
          .filter(f => f.startsWith(_currentRuc) && (f.endsWith('.png') || f.endsWith('.html')))
          .forEach(f => fs.unlinkSync(path.join(DEBUG_DIR, f)));
    } catch (_) {}

    debugLog(`Iniciando descarga auto/manual SRI para ${config.usuario} - Periodo: ${config.ano}-${config.mes}-${config.dia}`);
    
    const { usuario, clave, ano, mes, dia, tipo, apiKey2captcha, clavesExcluir = [] } = config;
    _streamXml = config.streamXml === true;
    const setExcluir = new Set(clavesExcluir);

    // Modo ASISTIDO: el sistema loguea y aplica filtros, pero NO resuelve el captcha.
    // El humano hace clic en "Consultar" sobre el portal real (visto por pantalla remota/noVNC),
    // y el scraper solo recolecta las claves de acceso del listado (los XML los baja PHP por
    // webservice, sin captcha). Es lo que sube el score de reCAPTCHA: interacción humana real.
    const modoAsistido = config.modoAsistido === true;

    // 2captcha solo se usa en el modo automático (resolución sintética del captcha).
    if (!modoAsistido && !apiKey2captcha) throw new Error('Falta apiKey2captcha en la configuración.');

    // Headful por defecto: es lo que pasa el captcha del SRI (el headless penaliza el score).
    //   - Local (Windows, con pantalla): ventana visible.
    //   - Producción (Linux, sin pantalla): también headful, pero lanzado bajo xvfb-run
    //     (display virtual) — ver SriDescargaAutomaticaService. xvfb conserva el score real.
    // Se puede forzar headless pasando config.headless=true (no recomendado: baja el score).
    const headless = (config.headless === true);
    debugLog(`Lanzando navegador (headless=${headless})...`);

    const launchArgs = [
        '--no-sandbox', '--disable-setuid-sandbox',
        '--disable-dev-shm-usage', '--disable-gpu',
        '--disable-blink-features=AutomationControlled'
    ];

    // Perfil persistente: reutiliza cookies/reputación (idealmente con sesión de Google
    // iniciada) para que reCAPTCHA v3 asigne un score alto. Es lo que diferencia a un
    // navegador real (que pasa la validación) de uno automatizado vacío (que el SRI
    // rechaza por score bajo). Se siembra una vez con scripts/sri_seed_profile.js.
    const profileDir  = config.profileDir || path.join(__dirname, '.sri_profile');
    const usarPerfil  = config.usarPerfil !== false; // por defecto, usar perfil persistente

    const contextOpts = {
        acceptDownloads: true,
        viewport: { width: 1366, height: 768 },
        extraHTTPHeaders: { 'Accept-Language': 'es-EC,es;q=0.9,en;q=0.8' },
    };

    // Canal del navegador: en Windows usamos Chrome real (instalado en el equipo);
    // en Linux (servidor) la Chromium que trae Playwright, ya que ahí no hay Chrome.
    // Configurable con config.channel (cadena vacía = Chromium empaquetada).
    let channel;
    if (typeof config.channel === 'string') channel = config.channel || undefined;
    else channel = (process.platform === 'win32') ? 'chrome' : undefined;

    const launchOpts = { headless, args: launchArgs };
    if (channel) launchOpts.channel = channel;

    let browser, context;
    if (usarPerfil) {
        debugLog(`Usando perfil persistente: ${profileDir} (channel=${channel || 'chromium'})`);
        liberarPerfil(profileDir);
        context = await chromium.launchPersistentContext(profileDir, {
            ...launchOpts,
            ...contextOpts,
        });
        browser = context.browser();
    } else {
        browser = await chromium.launch(launchOpts);
        context = await browser.newContext(contextOpts);
    }
    _browser = browser;
    _context = context;

    try {
        reportarProgreso(5, 'Iniciando sesión en el portal SRI...');

        // En modo asistido NO se bloquea ningún recurso: el portal debe verse completo para el
        // humano y el reCAPTCHA debe comportarse de forma natural (mejor score).
        if (!modoAsistido) {
            await context.route('**/*', (route) => {
                const req = route.request();
                const tipoRes = req.resourceType();
                const url = req.url();
                if (tipoRes === 'image' && !url.includes('srienlinea.sri.gob.ec')) { route.abort(); return; }
                if (tipoRes === 'media') { route.abort(); return; }
                route.continue();
            });
        }

        const page = context.pages()[0] || await context.newPage();

        // En perfil persistente, eliminar SOLO las cookies del SRI para forzar un login
        // fresco con las credenciales de esta empresa (evita contaminación multiempresa).
        // Se conservan las cookies de Google, que son las que aportan reputación al score.
        if (usarPerfil) {
            await limpiarCookiesSri(context);
        }

        debugLog('Abriendo portal en la página de inicio...');
        await ir(page, PORTAL_URL, 30000);
        await pausa(1000);

        reportarProgreso(10, 'Analizando estado de sesión en el portal...');
        let enKeycloak = false;
        let enHome = false;

        const finAnalisis = Date.now() + 15000;
        while (Date.now() < finAnalisis) {
            const url = page.url();
            if (url.includes('/auth/realms/')) {
                enKeycloak = true;
                break;
            }
            const menuPresente = await page.evaluate(() => {
                return !!document.querySelector('.sri-menu-icon-facturacion-electronica');
            }).catch(() => false);
            if (menuPresente) {
                enHome = true;
                break;
            }
            await pausa(500);
        }

        if (!enKeycloak && !enHome) {
            await screenshot(page, 'ERROR_estado_inicial_no_reconocido');
            throw new Error('No se pudo determinar el estado de sesión inicial en el portal.');
        }

        if (enHome) {
            debugLog('Portal cargado en página de inicio. Navegando al menú...');
            reportarProgreso(12, 'Navegando al módulo de comprobantes...');
            await navegarMenuComprobantes(page);

            enKeycloak = false;
            let enComprobantes = false;
            const finEsperaMenu = Date.now() + 15000;
            while (Date.now() < finEsperaMenu) {
                const url = page.url();
                if (url.includes('/auth/realms/')) {
                    enKeycloak = true;
                    break;
                }
                if (url.includes('comprobantes-electronicos-internet')) {
                    enComprobantes = true;
                    break;
                }
                await pausa(500);
            }
        }

        if (enKeycloak) {
            reportarProgreso(15, 'Ingresando credenciales de acceso...');
            await loginKeycloak(page, usuario, clave);

            const finRedir = Date.now() + 20000;
            let redirigido = false;
            while (Date.now() < finRedir) {
                const url = page.url();
                if (url.includes('comprobantes-electronicos-internet') || url.includes('inicio/NAT') || url.includes('/sri-en-linea/')) {
                    redirigido = true;
                    break;
                }
                await pausa(500);
            }
        }

        if (!page.url().includes('comprobantes-electronicos-internet')) {
            debugLog('Navegando directamente a la URL de comprobantes recibidos...');
            await ir(page, BASE + COMP_PATH, 30000);
        }

        await esperarURLContiene(page, 'comprobantes-electronicos-internet', 20000);

        reportarProgreso(25, 'Aplicando filtros de búsqueda...');
        const ctx = await resolverContexto(page);

        const selectorAno = await esperarCualquiera(ctx, [
            '#frmPrincipal\\:ano', '[id="frmPrincipal:ano"]', 'select[id*=":ano"]',
        ], 25000);

        if (!selectorAno) {
            await screenshot(page, 'ERROR_formulario_no_encontrado');
            throw new Error('Formulario de filtros no encontrado.');
        }

        await seleccionarOpcion(ctx, selectorAno, String(ano));
        await pausa(400);

        const selectorMes = await encontrarSelector(ctx, ['[id="frmPrincipal:mes"]', 'select[id*=":mes"]']);
        if (selectorMes) { await seleccionarOpcion(ctx, selectorMes, String(mes)); await pausa(300); }

        const selectorDia = await encontrarSelector(ctx, ['[id="frmPrincipal:dia"]', 'select[id*=":dia"]']);
        if (selectorDia) { await seleccionarOpcion(ctx, selectorDia, String(dia)); await pausa(200); }

        if (String(tipo) !== '0') {
            const selectorTipo = await encontrarSelector(ctx, [
                '[id="frmPrincipal:cmbTipoComprobante"]',
                'select[id*="TipoComprobante"]',
            ]);
            if (selectorTipo) { await seleccionarOpcion(ctx, selectorTipo, String(tipo)); await pausa(200); }
        }

        // ── MODO ASISTIDO ────────────────────────────────────────────────────────
        // No se resuelve el captcha automáticamente. El humano hace clic en "Consultar"
        // sobre el portal real (vía pantalla remota). El scraper solo recolecta las claves.
        if (modoAsistido) {
            reportarProgreso(40, 'Portal listo. Esperando que hagas clic en CONSULTAR...');
            emitirEsperandoHumano('Cuando veas el portal del SRI, haz clic en el botón CONSULTAR para continuar.');

            const timeoutHumano = (config.timeoutHumanoMs ?? 240000); // 4 min para que el humano consulte
            const estado = await esperarConsultaAsistida(ctx, timeoutHumano);

            if (estado === 'timeout') {
                await screenshot(page, 'ASISTIDO_timeout_sin_consulta');
                throw new Error('No se detectó la consulta. ¿Hiciste clic en CONSULTAR? Intenta de nuevo.');
            }

            if (estado === 'vacio') {
                await screenshot(page, 'ASISTIDO_cero_documentos');
                reportarProgreso(100, 'Consulta realizada: sin documentos para el período.');
                emitirClaves([]);
                emitir({ ok: true, modo: 'asistido', total: 0, mensaje: 'Sin documentos para el período.' });
                return;
            }

            reportarProgreso(80, 'Consulta detectada. Recolectando claves de acceso...');
            const links  = await recolectarTodosLosLinks(page, ctx);
            const claves = [...new Set(links.map(l => l.clave).filter(c => c && c.length === 49))];

            await screenshot(page, 'ASISTIDO_listado_recolectado');
            emitirClaves(claves);
            reportarProgreso(100, `Listado obtenido: ${claves.length} comprobantes.`);
            emitir({ ok: true, modo: 'asistido', total: claves.length });
            return;
        }

        reportarProgreso(35, 'Resolviendo verificación de seguridad (CAPTCHA)...');
        await ejecutarConsultar(ctx, page, apiKey2captcha);

        reportarProgreso(45, 'Consultando comprobantes recibidos...');
        const hayResultados = await esperarTablaResultados(ctx);

        if (!hayResultados) {
            await screenshot(page, 'resultado_cero_documentos_encontrados');
            reportarProgreso(100, 'Búsqueda completada sin resultados.');
            emitir({ ok: true, xmls: [], total: 0, mensaje: 'Sin documentos para el período.' });
            return;
        }

        // Descarga incremental: cada página se procesa (recolecta + descarga) antes de avanzar
        // a la siguiente. Si el timeout ocurre en la página N, las páginas 1…N-1 ya quedaron
        // guardadas en la BD. Esto evita el problema de "descarga larga = timeout = 0 guardados".
        reportarProgreso(50, 'Descargando documentos por página...');
        const { yaExistentes } = await procesarPorPaginas(page, ctx, setExcluir);

        reportarProgreso(95, 'Descarga completada. Registrando en la base de datos...');
        await screenshot(page, 'resultado_descarga_completada');

        const resultado = globalResultados.filter(x => x.xml);
        // En modo streaming los XML ya se emitieron uno a uno; el finish solo lleva conteos.
        emitir({ ok: true, xmls: _streamXml ? [] : resultado, total: resultado.length, ya_existentes: yaExistentes });

    } finally {
        // En contexto persistente, cerrar el contexto libera el perfil y el navegador.
        await context.close().catch(() => {});
        await browser?.close().catch(() => {});
        _context = null;
        _browser = null;
    }
}

/**
 * Elimina únicamente las cookies del dominio del SRI, conservando el resto (Google, etc.).
 * Compatible con cualquier versión de Playwright: lee todas, filtra y reescribe.
 */
/**
 * Elimina los archivos de bloqueo que Chrome deja en el perfil (SingletonLock/Cookie/Socket).
 * Si quedaron de una corrida anterior que no cerró bien, Chrome reenviaría la orden a una
 * "sesión existente" (handoff) y Playwright perdería el navegador al instante.
 * Solo elimina locks; no toca cookies ni datos de sesión (la reputación se conserva).
 */
function liberarPerfil(profileDir) {
    // En Windows, un Chrome real previo que use ESTE perfil provoca el "handoff"
    // ("Se está abriendo en una sesión de navegador existente"): el nuevo Chrome cede el
    // control al viejo y termina, y Playwright pierde el navegador. Matar esas instancias
    // huérfanas del perfil SRI antes de lanzar. NO afecta al Chrome personal del usuario
    // (que usa otro user-data-dir): se filtra por la ruta del perfil en la línea de comandos.
    if (process.platform === 'win32') {
        try {
            const { execSync } = require('child_process');
            const marcador = path.basename(profileDir); // p.ej. ".sri_profile"
            execSync(
                'powershell -NoProfile -Command "Get-CimInstance Win32_Process | ' +
                "Where-Object { $_.Name -eq 'chrome.exe' -and $_.CommandLine -like '*" + marcador + "*' } | " +
                'ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }"',
                { stdio: 'ignore', timeout: 8000 }
            );
        } catch (_) {}
    }

    for (const f of ['SingletonLock', 'SingletonCookie', 'SingletonSocket']) {
        try { fs.rmSync(path.join(profileDir, f), { force: true }); } catch (_) {}
    }
}

async function limpiarCookiesSri(context) {
    try {
        const cookies = await context.cookies();
        const conservar = cookies.filter(c => !(c.domain || '').includes('sri.gob.ec'));
        await context.clearCookies();
        if (conservar.length) await context.addCookies(conservar);
        debugLog(`Cookies SRI eliminadas (${cookies.length - conservar.length}); conservadas ${conservar.length}.`);
    } catch (e) {
        debugLog('limpiarCookiesSri error: ' + e.message);
    }
}

/**
 * Descarga incremental página a página: recolecta los enlaces de la página actual,
 * descarga sus XMLs inmediatamente y luego avanza a la siguiente página.
 * Así, si el proceso se interrumpe por timeout durante la página N, las páginas
 * anteriores ya tienen sus documentos guardados en la BD.
 */
async function procesarPorPaginas(page, ctx, setExcluir) {
    globalResultados = [];
    let yaExistentes = 0;
    let pagina = 0;
    const clavesVistas = new Set();

    while (pagina < MAX_PAGINAS) {
        reportarProgreso(
            50 + Math.floor((pagina / MAX_PAGINAS) * 43),
            `Página ${pagina + 1}: recolectando y descargando...`
        );

        const filasPagina = await ctx.evaluate(() => {
            const tbody = document.querySelector('#frmPrincipal\\:tablaCompRecibidos_data') ||
                          document.querySelector('[id*="tablaCompRecibidos_data"]');
            if (!tbody) return [];
            return Array.from(tbody.querySelectorAll('tr[data-ri]')).map(row => {
                const ri = row.getAttribute('data-ri');
                const textContent = row.textContent || '';
                // La clave de acceso vive en su propia celda (49 dígitos). Buscar la <td> cuyos
                // dígitos sumen exactamente 49 evita pegar dígitos de otras columnas (p. ej. el
                // número de comprobante), que produciría una clave corrupta y un falso RECHAZADA.
                let clave = null;
                for (const td of row.querySelectorAll('td')) {
                    const soloDig = (td.textContent || '').replace(/\D/g, '');
                    if (soloDig.length === 49) { clave = soloDig; break; }
                }
                if (!clave) {
                    const m = textContent.match(/(?<!\d)\d{49}(?!\d)/); // 49 dígitos no rodeados de más dígitos
                    if (m) clave = m[0];
                }
                const linkXml = row.querySelector('a[id*=":lnkXml"]');
                const xmlId   = linkXml ? linkXml.id : null;
                return { ri, clave, xmlId, _textDebug: textContent.substring(0, 100) };
            });
        }).catch(() => []);

        if (filasPagina.length > 0) {
            debugLog(`Página ${pagina + 1} - Fila 0: clave=${filasPagina[0].clave}, xmlId=${filasPagina[0].xmlId}`);
        }

        const validos = filasPagina.filter(f => f.clave && f.xmlId);

        // Detectar paginación atascada (mismas claves que ya procesamos)
        const noVistas = validos.filter(f => !clavesVistas.has(f.clave));
        if (noVistas.length === 0 && validos.length > 0) {
            debugLog(`Página ${pagina + 1}: sin claves nuevas, paginación detenida.`);
            break;
        }
        noVistas.forEach(f => clavesVistas.add(f.clave));

        // Separar documentos ya en BD (excluir) de los que hay que descargar
        const nuevos   = noVistas.filter(f => !setExcluir.has(f.clave));
        yaExistentes  += noVistas.length - nuevos.length;

        debugLog(`Página ${pagina + 1}: ${nuevos.length} nuevos, ${noVistas.length - nuevos.length} ya existentes.`);

        // Descargar XMLs de esta página antes de avanzar
        for (let i = 0; i < nuevos.length; i++) {
            const item = nuevos[i];
            debugLog(`Descargando ${i + 1}/${nuevos.length} de pág. ${pagina + 1}: ${item.clave}`);
            const xml = await descargarXmlPorClick(page, ctx, item);
            if (xml) {
                globalResultados.push({ clave: item.clave, xml });
                if (_streamXml) emitirXml(item.clave, xml);
            }
            await pausa(200);
        }

        const hayMas = await avanzarPagina(ctx, page);
        if (!hayMas) break;
        pagina++;
    }

    return { yaExistentes };
}

async function recolectarTodosLosLinks(page, ctx) {
    const todos  = [];
    let pagina   = 0;

    while (pagina < MAX_PAGINAS) {
        const filasPagina = await ctx.evaluate(() => {
            const tbody = document.querySelector('#frmPrincipal\\:tablaCompRecibidos_data') || document.querySelector('[id*="tablaCompRecibidos_data"]');
            if (!tbody) return [];

            return Array.from(tbody.querySelectorAll('tr[data-ri]')).map(row => {
                const ri = row.getAttribute('data-ri');
                const textContent = row.textContent || '';
                // La clave de acceso vive en su propia celda (49 dígitos). Buscar la <td> cuyos
                // dígitos sumen exactamente 49 evita pegar dígitos de otras columnas (p. ej. el
                // número de comprobante), que produciría una clave corrupta y un falso RECHAZADA.
                let clave = null;
                for (const td of row.querySelectorAll('td')) {
                    const soloDig = (td.textContent || '').replace(/\D/g, '');
                    if (soloDig.length === 49) { clave = soloDig; break; }
                }
                if (!clave) {
                    const m = textContent.match(/(?<!\d)\d{49}(?!\d)/); // 49 dígitos no rodeados de más dígitos
                    if (m) clave = m[0];
                }
                const linkXml = row.querySelector(`a[id*=":lnkXml"]`);
                const xmlId   = linkXml ? linkXml.id : null;
                return { ri, clave, xmlId, _textDebug: textContent.substring(0, 100) };
            });
        }).catch(() => []);

        // Debug log temporal
        if (filasPagina.length > 0) {
            debugLog(`Fila 0 clave: ${filasPagina[0].clave}, xmlId: ${filasPagina[0].xmlId}`);
        }

        const validos = filasPagina.filter(f => f.clave && f.xmlId);

        const nuevas = validos.filter(v => !todos.some(t => t.clave === v.clave));

        if (nuevas.length === 0 && validos.length > 0) {
            break;
        }

        todos.push(...nuevas);

        const hayMas = await avanzarPagina(ctx, page);
        if (!hayMas) break;
        pagina++;
    }
    return todos;
}

async function avanzarPagina(ctx, page) {
    const estadoAntes = await ctx.evaluate(() => {
        const btn = document.querySelector('.ui-paginator-next');
        const firstRow = document.querySelector('tr[data-ri]');
        return {
            hayNext: btn && !btn.classList.contains('ui-state-disabled'),
            firstRowText: firstRow ? firstRow.textContent : ''
        };
    }).catch(() => ({ hayNext: false, firstRowText: '' }));

    if (!estadoAntes.hayNext) return false;

    await ctx.evaluate(() => document.querySelector('.ui-paginator-next').click());
    
    try {
        await page.waitForFunction((textoAnterior) => {
            const firstRow = document.querySelector('tr[data-ri]');
            const textActual = firstRow ? firstRow.textContent : '';
            return textActual !== textoAnterior;
        }, estadoAntes.firstRowText, { timeout: 10000 });
    } catch (e) {
        await pausa(2000);
    }
    
    return true;
}

async function descargarEnParalelo(page, ctx, links) {
    globalResultados = [];
    const basePct = 60;
    const maxPct = 95;
    
    for (let i = 0; i < links.length; i++) {
        const item = links[i];
        
        const pct = Math.floor(basePct + ((i / links.length) * (maxPct - basePct)));
        reportarProgreso(pct, `Descargando XML ${i + 1}/${links.length}...`);

        const xml = await descargarXmlPorClick(page, ctx, item);
        if (xml) {
            globalResultados.push({ clave: item.clave, xml });
            // Emitir el XML apenas se descarga para registro incremental en BD.
            if (_streamXml) emitirXml(item.clave, xml);
        }
        await pausa(200);
    }
    return globalResultados;
}

async function descargarXmlPorClick(page, ctx, item) {
    let download = null;
    try {
        [download] = await Promise.all([
            page.waitForEvent('download', { timeout: 15000 }),
            ctx.evaluate((xmlId) => {
                const el = document.getElementById(xmlId);
                if (el) el.click();
            }, item.xmlId)
        ]);

        // download.path() espera a que la descarga TERMINE y no tiene timeout propio:
        // si una descarga arranca pero se cuelga, bloquearía todo el proceso. Lo acotamos.
        const filePath = await Promise.race([
            download.path(),
            new Promise((_, rej) => setTimeout(() => rej(new Error('La descarga no finalizó a tiempo')), 20000)),
        ]);

        if (!filePath) throw new Error('No se obtuvo la ruta del archivo descargado.');
        const xml = fs.readFileSync(filePath, 'utf8');
        return xml;
    } catch (e) {
        debugLog(`Error descargando ${item.clave}: ${e.message}`);
        // Cancelar la descarga trabada para liberar recursos y poder continuar.
        if (download) { try { await download.cancel(); } catch (_) {} }
        return null;
    }
}

async function resolverRecaptcha2captcha(apiKey) {
    const https = require('https');
    debugLog('2captcha: enviando tarea...');
    const taskId = await new Promise((resolve, reject) => {
        const body = new URLSearchParams({
            key: apiKey, method: 'userrecaptcha', version: 'v3', enterprise: '1',
            googlekey: RECAPTCHA_SITEKEY, pageurl: BASE + COMP_PATH,
            action: RECAPTCHA_ACTION, min_score: '0.3', json: '1',
        }).toString();

        const req = https.request({
            hostname: '2captcha.com', path: '/in.php', method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        }, res => {
            let data = '';
            res.on('data', d => data += d);
            res.on('end', () => {
                try {
                    const json = JSON.parse(data);
                    if (json.status === 1) resolve(json.request);
                    else reject(new Error('2captcha envío: ' + json.request));
                } catch (e) { reject(new Error('2captcha respuesta inválida: ' + data)); }
            });
        });
        req.on('error', reject);
        req.write(body);
        req.end();
    });

    await pausa(8000);

    const fin = Date.now() + 120_000;
    while (Date.now() < fin) {
        const token = await new Promise((resolve, reject) => {
            https.get('https://2captcha.com/res.php?key=' + apiKey + '&action=get&id=' + taskId + '&json=1', res => {
                let data = '';
                res.on('data', d => data += d);
                res.on('end', () => {
                    try {
                        const json = JSON.parse(data);
                        if (json.status === 1) resolve(json.request);
                        else if (json.request === 'CAPCHA_NOT_READY') resolve(null);
                        else reject(new Error('2captcha poll: ' + json.request));
                    } catch (e) { reject(new Error('2captcha respuesta: ' + data)); }
                });
            }).on('error', reject);
        });

        if (token) return token;
        await pausa(5000);
    }
    throw new Error('2captcha: timeout esperando token.');
}

async function ejecutarConsultar(ctx, page, apiKey2captcha) {
    // 1) Preferir el token generado por el propio navegador ya logueado en el SRI.
    //    Al ser una sesión real (cookies de autenticación + stealth), Google le asigna
    //    un score más alto que el token de la granja de 2captcha, que el SRI rechazaba
    //    con "captcha incorrecta".
    let token  = await obtenerTokenEnSesion(ctx);
    let origen = 'sesion';

    // 2) Respaldo: si por alguna razón no se pudo generar en sesión, usar 2captcha.
    if (!token) {
        debugLog('Token en sesión no disponible; usando 2captcha como respaldo...');
        token  = await resolverRecaptcha2captcha(apiKey2captcha);
        origen = '2captcha';
    }
    debugLog(`Token reCAPTCHA obtenido vía ${origen}.`);

    // 3) Inyectar el token y disparar la consulta igual que el portal:
    //    rcBuscar() ejecuta PrimeFaces.ab sobre el form 'frmPrincipal', que serializa
    //    el textarea g-recaptcha-response; además lo pasamos como parámetro explícito.
    await ctx.evaluate((tk) => {
        const textarea = document.querySelector('textarea[name="g-recaptcha-response"]');
        if (textarea) textarea.value = tk;
        if (typeof rcBuscar === 'function') {
            rcBuscar([{ name: 'g-recaptcha-response', value: tk }]);
        } else if (typeof executeRecaptcha === 'function') {
            executeRecaptcha('consulta_cel_recibidos', 'NO');
        }
    }, token);

    try { await page.waitForLoadState('networkidle', { timeout: 15000 }); } catch (_) {}
    await pausa(1500);
}

/**
 * Genera un token reCAPTCHA v3 Enterprise usando el grecaptcha real de la página
 * (sesión ya autenticada). Devuelve null si no está disponible o falla.
 */
async function obtenerTokenEnSesion(ctx) {
    try {
        return await ctx.evaluate(({ sitekey, action }) => new Promise((resolve) => {
            try {
                if (!window.grecaptcha || !window.grecaptcha.enterprise) return resolve(null);
                // Salvaguarda: si ready/execute nunca responden, no bloquear.
                const tid = setTimeout(() => resolve(null), 12000);
                grecaptcha.enterprise.ready(() => {
                    grecaptcha.enterprise.execute(sitekey, { action })
                        .then(t => { clearTimeout(tid); resolve(t || null); })
                        .catch(() => { clearTimeout(tid); resolve(null); });
                });
            } catch (e) { resolve(null); }
        }), { sitekey: RECAPTCHA_SITEKEY, action: RECAPTCHA_ACTION });
    } catch (e) {
        debugLog('obtenerTokenEnSesion error: ' + e.message);
        return null;
    }
}

async function esperarTablaResultados(ctx) {
    const fin = Date.now() + 12000;
    while (Date.now() < fin) {
        const filas = await ctx.evaluate(() => {
            const tabla = document.querySelector('[id="frmPrincipal:tablaCompRecibidos_data"]') ||
                          document.querySelector('[id*="tablaCompRecibidos"] tbody');
            if (!tabla) return 0;
            return tabla.querySelectorAll('tr[data-ri]:not(.ui-datatable-empty-message), tr.ui-widget-content:not(.ui-datatable-empty-message)').length;
        }).catch(() => 0);

        if (filas > 0) return true;

        const sinResultados = await ctx.evaluate(() => {
            const vacio = document.querySelector('.ui-datatable-empty-message');
            return vacio ? vacio.textContent.trim() : null;
        }).catch(() => null);

        if (sinResultados) return false;
        await pausa(800);
    }
    return false;
}

/**
 * Modo asistido: espera a que el HUMANO haga clic en "Consultar" en el portal real.
 * Devuelve 'resultados' (aparecieron filas con clave), 'vacio' (mensaje de sin registros)
 * o 'timeout' (no consultó dentro del tiempo). Solo cuenta como consulta una fila con clave
 * de 49 dígitos o el mensaje vacío de PrimeFaces, para no detectar la consulta antes de tiempo.
 */
async function esperarConsultaAsistida(ctx, timeoutMs) {
    const fin = Date.now() + timeoutMs;
    while (Date.now() < fin) {
        const estado = await ctx.evaluate(() => {
            const tbody = document.querySelector('#frmPrincipal\\:tablaCompRecibidos_data') ||
                          document.querySelector('[id*="tablaCompRecibidos_data"]');
            if (tbody) {
                const filas = Array.from(tbody.querySelectorAll('tr[data-ri]'));
                if (filas.some(r => /\d{49}/.test(r.textContent || ''))) return 'resultados';
            }
            const vacio = document.querySelector('.ui-datatable-empty-message');
            if (vacio && vacio.textContent.trim()) return 'vacio';
            return null;
        }).catch(() => null);

        if (estado) return estado;
        await pausa(1000);
    }
    return 'timeout';
}

async function loginKeycloak(page, usuario, clave) {
    await page.waitForSelector('#usuario', { timeout: 15000, state: 'visible' }).catch(() => {});

    const existe = await page.evaluate(() => ({
        ruc: !!document.querySelector('#usuario'),
        clave: !!document.querySelector('#password'),
        btn: !!document.querySelector('#kc-login'),
    }));

    if (!existe.ruc || !existe.clave || !existe.btn) throw new Error('Formulario incompleto.');

    await page.locator('#usuario').fill('');
    await page.locator('#usuario').pressSequentially(usuario, { delay: 40 });
    await pausa(100);

    await page.locator('#password').fill('');
    await page.locator('#password').pressSequentially(clave, { delay: 40 });
    await pausa(100);

    await page.locator('#kc-login').click();

    const finEspera = Date.now() + 20000;
    while (Date.now() < finEspera) {
        await pausa(600);
        const txt = await page.evaluate(() => document.body.innerText.toLowerCase()).catch(() => '');
        if (detectaCredencialesInvalidas(txt)) {
            await screenshot(page, 'ERROR_credenciales_invalidas');
            emitirCredencialesInvalidas(txt.substring(0, 300));
        }

        const formularioVisible = await page.evaluate(() => {
            const btn = document.querySelector('#kc-login');
            return btn ? (btn.offsetParent !== null) : false;
        }).catch(() => false);

        if (!formularioVisible) return;
    }
    await screenshot(page, 'ERROR_login_timeout');
    throw new Error('Login timeout.');
}

async function resolverContexto(page) {
    await pausa(500);
    const enPrincipal = await Promise.race([
        page.$('select[id*="ano"], [id*="frmPrincipal"]').catch(() => null),
        pausa(2000).then(() => null),
    ]);
    if (enPrincipal) return page;

    for (const frame of page.frames()) {
        if (frame === page.mainFrame()) continue;
        const ok = await frame.evaluate(() =>
            document.querySelector('select[id*="ano"]') !== null ||
            document.querySelector('[id*="frmPrincipal"]') !== null
        ).catch(() => false);
        if (ok) return frame;
    }
    return page;
}

async function navegarMenuComprobantes(page) {
    await page.waitForSelector('.sri-menu-icon-facturacion-electronica', { timeout: 20000 });
    await pausa(500);
    await page.evaluate(() => document.querySelector('.sri-menu-icon-facturacion-electronica').closest('a.ui-panelmenu-header-link').click());
    await page.waitForSelector('a[href*="redireccion=57"]', { timeout: 10000 });
    await pausa(500);
    await page.click('a[href*="redireccion=57"]');
}

async function esperarCualquiera(ctx, selectores, timeout) {
    const fin = Date.now() + timeout;
    while (Date.now() < fin) {
        for (const sel of selectores) {
            const found = await ctx.$(sel).catch(() => null);
            if (found) return sel;
        }
        await pausa(400);
    }
    return null;
}

async function encontrarSelector(ctx, selectores) {
    for (const sel of selectores) {
        try { if (await ctx.$(sel)) return sel; } catch (_) {}
    }
    return null;
}

async function seleccionarOpcion(ctx, selector, valor) {
    try {
        await ctx.evaluate(([sel, v]) => {
            const el = document.querySelector(sel);
            if (el) {
                el.value = v;
                el.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }, [selector, valor]);
    } catch (_) {}
}

async function ir(page, url, ms = 25000) {
    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: ms }).catch(() => {});
}

async function esperarURLContiene(page, patron, ms) {
    const fin = Date.now() + ms;
    while (Date.now() < fin) {
        if (page.url().includes(patron)) return true;
        await pausa(400);
    }
    return false;
}

function detectaCredencialesInvalidas(txt) {
    return TEXTOS_CRED_INVALIDAS.some(t => txt.includes(t));
}

function emitirCredencialesInvalidas(detalle) {
    emitir({ ok: false, error: 'Credenciales SRI incorrectas. Detalle: ' + detalle, credenciales_incorrectas: true });
    process.exit(1);
}

// Emisión estándar de resultados en JSON line por stdout
function emitir(obj) {
    process.stdout.write(JSON.stringify({ type: 'finish', data: obj }) + '\n');
}

// Emisión incremental de un XML descargado (modo streaming)
function emitirXml(clave, xml) {
    process.stdout.write(JSON.stringify({ type: 'xml', data: { clave, xml } }) + '\n');
}

// Modo asistido: avisa que el scraper está esperando el clic humano en "Consultar".
function emitirEsperandoHumano(mensaje) {
    process.stdout.write(JSON.stringify({ type: 'esperando_humano', message: mensaje }) + '\n');
}

// Modo asistido: emite la lista de claves de acceso recolectadas del listado.
// PHP las descarga una a una por el webservice (sin captcha) y las registra.
function emitirClaves(claves) {
    process.stdout.write(JSON.stringify({ type: 'claves', data: claves }) + '\n');
}

// Progreso a stdout en JSON line
function reportarProgreso(pct, msg) {
    process.stdout.write(JSON.stringify({ type: 'progress', pct, message: msg }) + '\n');
}

// Debug logs solo a stderr
function debugLog(msg) { 
    process.stderr.write('[SRI] ' + msg + '\n'); 
}

const pausa = ms => new Promise(r => setTimeout(r, ms));

let rawInput = '';
process.stdin.setEncoding('utf8');
process.stdin.on('data', chunk => { rawInput += chunk; });
process.stdin.on('end', () => {
    let config;
    try { config = JSON.parse(rawInput); }
    catch (e) { emitir({ ok: false, error: 'JSON inválido: ' + e.message }); process.exit(1); }

    if (!config.usuario || !config.clave) {
        emitir({ ok: false, error: 'Faltan usuario o clave.' });
        process.exit(1);
    }

    config.ano       = config.ano       ?? new Date().getFullYear();
    config.mes       = config.mes       ?? (new Date().getMonth() + 1);
    config.dia       = config.dia       ?? 0;
    config.tipo      = config.tipo      ?? '0';
    config.timeoutMs = config.timeoutMs ?? 120_000;

    const tid = setTimeout(() => {
        emitir({
            ok: false,
            error: `Timeout: el scraper superó ${Math.round(config.timeoutMs / 1000)}s.`,
            xmls: _streamXml ? [] : globalResultados,
            total: globalResultados.length,
            partial: true
        });
        process.exit(1);
    }, config.timeoutMs);
    if (tid.unref) tid.unref();

    main(config)
        .then(() => clearTimeout(tid))
        .catch(err => {
            clearTimeout(tid);
            emitir({
                ok: false,
                error: err.message,
                xmls: _streamXml ? [] : globalResultados,
                total: globalResultados.length,
                partial: true
            });
            process.exit(1);
        });
});
