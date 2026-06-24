'use strict';

/**
 * Agente de descargas SRI — corre en la PC del operador (IP residencial, navegador real).
 *
 * Flujo:
 *   1. Pide al servidor las credenciales SRI de la empresa (autenticado por token).
 *   2. Abre el navegador local en modo asistido (reutiliza ../sri_scraper.js): login
 *      automático + el operador hace clic en CONSULTAR (el captcha pasa: es su IP y su navegador).
 *   3. Recolecta las claves del listado y las envía al servidor.
 *   4. El servidor baja los XML por el webservice y los registra. Muestra el resumen.
 *
 * config.json (junto al ejecutable): { "servidorUrl": "...", "agenteToken": "...", "navegador": "chrome" }
 */

const fs       = require('fs');
const path     = require('path');
const https    = require('https');
const http     = require('http');
const readline = require('readline');
const { spawn } = require('child_process');

// Cuando el .exe se re-ejecuta a sí mismo para actuar como el scraper (ver lanzarScraper).
if (process.argv.includes('__scraper__')) {
    require(path.join(__dirname, '..', 'sri_scraper.js'));
    return;
}

// Junto al .exe en producción; junto al script en desarrollo.
const BASE_DIR    = process.pkg ? path.dirname(process.execPath) : __dirname;
const CONFIG_PATH = path.join(BASE_DIR, 'config.json');

function leerConfig() {
    if (!fs.existsSync(CONFIG_PATH)) {
        console.error('No se encontró config.json junto al agente.');
        console.error('Copia config.example.json a config.json y complétalo (servidorUrl y agenteToken).');
        process.exit(1);
    }
    try { return JSON.parse(fs.readFileSync(CONFIG_PATH, 'utf8')); }
    catch (e) { console.error('config.json inválido: ' + e.message); process.exit(1); }
}

function pedir(pregunta, def) {
    const rl = readline.createInterface({ input: process.stdin, output: process.stdout });
    return new Promise(res => rl.question(`${pregunta}${def !== undefined ? ` [${def}]` : ''}: `,
        ans => { rl.close(); res((ans || '').trim() || def); }));
}

// POST x-www-form-urlencoded → respuesta JSON
function postForm(urlStr, params) {
    return new Promise((resolve, reject) => {
        let u; try { u = new URL(urlStr); } catch (e) { return reject(new Error('URL inválida: ' + urlStr)); }
        const lib  = u.protocol === 'https:' ? https : http;
        const body = new URLSearchParams(params).toString();
        const req  = lib.request(u, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Content-Length': Buffer.byteLength(body) },
        }, res => {
            let data = '';
            res.on('data', d => data += d);
            res.on('end', () => {
                try { resolve(JSON.parse(data)); }
                catch (e) { reject(new Error('Respuesta no JSON del servidor (HTTP ' + res.statusCode + '): ' + data.slice(0, 200))); }
            });
        });
        req.on('error', reject);
        req.write(body);
        req.end();
    });
}

// Lanza el scraper (modo asistido) y devuelve las claves recolectadas.
function lanzarScraper(config) {
    return new Promise((resolve, reject) => {
        const scraperPath = path.join(__dirname, '..', 'sri_scraper.js');
        const child = process.pkg
            ? spawn(process.execPath, ['__scraper__'], { stdio: ['pipe', 'pipe', 'inherit'] })
            : spawn(process.execPath, [scraperPath],   { stdio: ['pipe', 'pipe', 'inherit'] });

        let claves = null, buffer = '';
        child.stdout.on('data', chunk => {
            buffer += chunk.toString();
            let idx;
            while ((idx = buffer.indexOf('\n')) >= 0) {
                const line = buffer.slice(0, idx).trim();
                buffer = buffer.slice(idx + 1);
                if (!line) continue;
                let msg; try { msg = JSON.parse(line); } catch (_) { continue; }
                if (msg.type === 'progress')              process.stdout.write(`  ${msg.pct || 0}%  ${msg.message || ''}\n`);
                else if (msg.type === 'esperando_humano') process.stdout.write(`\n>>> ${msg.message}\n`);
                else if (msg.type === 'claves')           claves = Array.isArray(msg.data) ? msg.data : [];
            }
        });
        child.on('error', reject);
        child.on('close', () => {
            if (claves !== null) resolve(claves);
            else reject(new Error('El navegador se cerró sin obtener el listado. ¿Hiciste clic en CONSULTAR?'));
        });

        child.stdin.write(JSON.stringify(config));
        child.stdin.end();
    });
}

(async () => {
    console.log('==============================================');
    console.log('   Agente de descargas SRI (desde tu PC)');
    console.log('==============================================\n');

    const cfg = leerConfig();
    if (!cfg.servidorUrl || !cfg.agenteToken) {
        console.error('config.json debe incluir servidorUrl y agenteToken.');
        process.exit(1);
    }
    const servidor = cfg.servidorUrl.replace(/\/+$/, '');

    // 1) Credenciales desde el servidor
    console.log('Conectando con el servidor...');
    const conf = await postForm(`${servidor}/modulos/DescargasSri/agenteConfigAjax`, { agente_token: cfg.agenteToken });
    if (!conf.ok) { console.error('Error: ' + (conf.error || 'token inválido')); process.exit(1); }
    console.log(`Empresa #${conf.id_empresa} — RUC ${conf.usuario}\n`);

    // 2) Período (Enter para los valores por defecto)
    const hoy  = new Date();
    const ano  = await pedir('Año', String(hoy.getFullYear()));
    const mes  = await pedir('Mes (1-12, 0 = todos)', String(hoy.getMonth() + 1));
    const dia  = await pedir('Día (0 = todos)', '0');
    const tipo = await pedir('Tipo (todos/facturas/retenciones/notas_credito/notas_debito/liquidaciones)', 'todos');

    // 3) Navegador local en modo asistido
    console.log('\nAbriendo el navegador. Inicia sesión automática...');
    console.log('Cuando veas el portal del SRI, HAZ CLIC EN "CONSULTAR". El agente seguirá solo.\n');
    const claves = await lanzarScraper({
        usuario:      conf.usuario,
        clave:        conf.clave,
        ano:          Number(ano) || hoy.getFullYear(),
        mes:          Number(mes),
        dia:          Number(dia),
        tipo,
        modoAsistido: true,
        channel:      cfg.navegador || 'chrome',
        profileDir:   path.join(BASE_DIR, '.perfil_agente'),
        timeoutMs:    600000,
    });

    console.log(`\nClaves recolectadas: ${claves.length}`);
    if (claves.length === 0) { console.log('No hay comprobantes para el período. Listo.'); process.exit(0); }

    // 4) Enviar al servidor para descargar los XML y registrarlos
    console.log('Enviando al servidor para descargar los XML y registrarlos...');
    const reg = await postForm(`${servidor}/modulos/DescargasSri/agenteRegistrarClavesAjax`, {
        agente_token: cfg.agenteToken,
        claves:       JSON.stringify(claves),
    });
    if (!reg.ok) { console.error('Error al registrar: ' + (reg.error || 'desconocido')); process.exit(1); }

    console.log('\n================ RESULTADO ================');
    console.log(`  En el listado:        ${reg.total_encontrados}`);
    console.log(`  Nuevos registrados:   ${reg.total_nuevos}`);
    console.log(`  Ya existían:          ${reg.total_existentes}`);
    console.log(`  Errores:              ${reg.total_errores}`);
    console.log('===========================================\n');
    console.log('Listo. Puedes cerrar esta ventana.');
    process.exit(0);
})().catch(err => {
    console.error('\nFallo: ' + err.message);
    process.exit(1);
});
