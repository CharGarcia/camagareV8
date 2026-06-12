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

try {
    fs.readdirSync(DEBUG_DIR)
      .filter(f => f.endsWith('.png') || f.endsWith('.html'))
      .forEach(f => fs.unlinkSync(path.join(DEBUG_DIR, f)));
} catch (_) {}

let _screenshotIdx = 0;

async function screenshot(pageOrFrame, nombre, ctxFrame = null) {
    try {
        _screenshotIdx++;
        const n    = String(_screenshotIdx).padStart(2, '0');
        const ts   = new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19);
        const base = `${n}_${nombre}_${ts}`;
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
const PORTAL_URL     = BASE + '/sri-en-linea/inicio/NAT';
const COMP_PATH      = '/comprobantes-electronicos-internet/pages/consultas/recibidos/comprobantesRecibidos.jsf';
const MAX_PAGINAS    = 10;

const RECAPTCHA_SITEKEY = '6LdukTQsAAAAAIcciM4GZq4ibeyplUhmWvlScuQE';
const RECAPTCHA_ACTION  = 'consulta_cel_recibidos';

const TEXTOS_CRED_INVALIDAS = [
    'credenciales inv', 'usuario o contrase', 'acceso no autorizado',
    'invalid credentials', 'contraseña incorrecta', 'usuario incorrecto',
    'authentication failed', 'invalid_grant',
];

let _browser = null;
process.on('exit', () => { if (_browser) _browser.close().catch(() => {}); });

async function main(config) {
    const { usuario, clave, ano, mes, dia, tipo, apiKey2captcha, clavesExcluir = [] } = config;
    const setExcluir = new Set(clavesExcluir);
    if (!apiKey2captcha) throw new Error('Falta apiKey2captcha en la configuración.');

    const browser = await chromium.launch({
        headless: true,
        args: [
            '--no-sandbox', '--disable-setuid-sandbox',
            '--disable-dev-shm-usage', '--disable-gpu',
            '--disable-blink-features=AutomationControlled'
        ]
    });
    _browser = browser;

    try {
        reportarProgreso(5, 'Iniciando sesión en el portal SRI...');

        const context = await browser.newContext({
            acceptDownloads: true,
            viewport: { width: 1366, height: 768 },
            extraHTTPHeaders: { 'Accept-Language': 'es-EC,es;q=0.9,en;q=0.8' }
        });

        await context.route('**/*', (route) => {
            const req = route.request();
            const tipoRes = req.resourceType();
            const url = req.url();
            if (tipoRes === 'image' && !url.includes('srienlinea.sri.gob.ec')) { route.abort(); return; }
            if (tipoRes === 'media') { route.abort(); return; }
            route.continue();
        });

        const page = await context.newPage();

        debugLog('Abriendo portal...');
        await ir(page, PORTAL_URL, 30000);
        await pausa(1000);

        reportarProgreso(10, 'Navegando al módulo de comprobantes...');
        await navegarMenuComprobantes(page);

        await esperarURLContiene(page, '/auth/realms/', 15000);

        reportarProgreso(15, 'Ingresando credenciales...');
        await loginKeycloak(page, usuario, clave);

        await esperarURLContiene(page, 'comprobantes-electronicos-internet', 25000);

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

        reportarProgreso(50, 'Recolectando enlaces de documentos...');
        const todosLosLinks = await recolectarTodosLosLinks(page, ctx);

        const linksFiltrados = todosLosLinks.filter(l => !setExcluir.has(l.clave));
        const yaExistentes   = todosLosLinks.length - linksFiltrados.length;
        
        if (linksFiltrados.length === 0) {
            reportarProgreso(100, 'Todos los comprobantes ya existen en el sistema.');
            emitir({ ok: true, xmls: [], total: 0, ya_existentes: yaExistentes, mensaje: 'Todos los comprobantes del período ya están registrados.' });
            return;
        }

        reportarProgreso(60, `Preparando descarga de ${linksFiltrados.length} archivos XML...`);
        const xmlsDescargados = await descargarEnParalelo(page, ctx, linksFiltrados);

        reportarProgreso(95, 'Descarga completada. Registrando en la base de datos...');
        await screenshot(page, 'resultado_descarga_completada');

        const resultado = xmlsDescargados.filter(x => x.xml);
        emitir({ ok: true, xmls: resultado, total: resultado.length, ya_existentes: yaExistentes });

    } finally {
        await browser.close().catch(() => {});
        _browser = null;
    }
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
                const linkClave = row.querySelector(`a[id*=":j_idt58"]`);
                const clave = linkClave ? linkClave.textContent.trim() : null;
                const linkXml = row.querySelector(`a[id*=":lnkXml"]`);
                const xmlId   = linkXml ? linkXml.id : null;
                return { ri, clave, xmlId };
            }).filter(f => f.clave && f.xmlId);
        }).catch(() => []);

        todos.push(...filasPagina);

        const hayMas = await avanzarPagina(ctx, page);
        if (!hayMas) break;
        pagina++;
    }
    return todos;
}

async function avanzarPagina(ctx, page) {
    const hayNext = await ctx.evaluate(() => {
        const btn = document.querySelector('.ui-paginator-next');
        return btn && !btn.classList.contains('ui-state-disabled');
    }).catch(() => false);

    if (!hayNext) return false;

    await ctx.evaluate(() => document.querySelector('.ui-paginator-next').click());
    try { await page.waitForLoadState('networkidle', { timeout: 6000 }); } catch (_) { await pausa(2000); }
    return true;
}

async function descargarEnParalelo(page, ctx, links) {
    const resultados = [];
    const basePct = 60;
    const maxPct = 95;
    
    for (let i = 0; i < links.length; i++) {
        const item = links[i];
        
        const pct = Math.floor(basePct + ((i / links.length) * (maxPct - basePct)));
        reportarProgreso(pct, `Descargando XML ${i + 1}/${links.length}...`);
        
        const xml = await descargarXmlPorClick(page, ctx, item);
        resultados.push({ clave: item.clave, xml });
        await pausa(200);
    }
    return resultados;
}

async function descargarXmlPorClick(page, ctx, item) {
    try {
        const [download] = await Promise.all([
            page.waitForEvent('download', { timeout: 15000 }),
            ctx.evaluate((xmlId) => {
                const el = document.getElementById(xmlId);
                if (el) el.click();
            }, item.xmlId)
        ]);

        const filePath = await download.path();
        const xml = fs.readFileSync(filePath, 'utf8');
        return xml;
    } catch (e) {
        debugLog(`Error descargando ${item.clave}: ${e.message}`);
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
    const token = await resolverRecaptcha2captcha(apiKey2captcha);

    await ctx.evaluate((tk) => {
        if (!window.grecaptcha) window.grecaptcha = {};
        if (!window.grecaptcha.enterprise) window.grecaptcha.enterprise = {};
        window.grecaptcha.enterprise.execute = function() { return Promise.resolve(tk); };
        window.grecaptcha.enterprise.reset = function() {};
        const textarea = document.querySelector('textarea[name="g-recaptcha-response"]');
        if (textarea) textarea.value = tk;
    }, token);

    await ctx.evaluate(() => {
        if (typeof executeRecaptcha === 'function') {
            executeRecaptcha('consulta_cel_recibidos');
        } else if (typeof rcBuscar === 'function') {
            const tk = document.querySelector('textarea[name="g-recaptcha-response"]')?.value || '';
            rcBuscar([{ name: 'g-recaptcha-response', value: tk }]);
        }
    });

    try { await page.waitForLoadState('networkidle', { timeout: 15000 }); } catch (_) {}
    await pausa(1500);
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

async function loginKeycloak(page, usuario, clave) {
    await page.waitForSelector('#usuario', { timeout: 15000, state: 'visible' }).catch(() => {});

    const existe = await page.evaluate(() => ({
        ruc: !!document.querySelector('#usuario'),
        clave: !!document.querySelector('#password'),
        btn: !!document.querySelector('#kc-login'),
    }));

    if (!existe.ruc || !existe.clave || !existe.btn) throw new Error('Formulario incompleto.');

    await page.evaluate(() => { document.querySelector('#usuario').focus(); document.querySelector('#usuario').value = ''; });
    await page.keyboard.type(usuario, { delay: 40 });
    await pausa(100);

    await page.evaluate(() => { document.querySelector('#password').focus(); document.querySelector('#password').value = ''; });
    await page.keyboard.type(clave, { delay: 40 });
    await pausa(100);

    await page.evaluate(() => document.querySelector('#kc-login').click());

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
        emitir({ ok: false, error: `Timeout: el scraper superó ${Math.round(config.timeoutMs / 1000)}s.` });
        process.exit(1);
    }, config.timeoutMs);
    if (tid.unref) tid.unref();

    main(config)
        .then(() => clearTimeout(tid))
        .catch(err => { clearTimeout(tid); emitir({ ok: false, error: err.message }); process.exit(1); });
});
