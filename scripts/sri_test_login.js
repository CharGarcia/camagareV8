/**
 * sri_test_login.js — Prueba SOLO el login Keycloak del SRI
 * Uso: node sri_test_login.js RUC CLAVE
 * Muestra exactamente qué pasa en cada paso del login.
 */
'use strict';

const puppeteerExtra = require('puppeteer-extra');
const StealthPlugin   = require('puppeteer-extra-plugin-stealth');
puppeteerExtra.use(StealthPlugin());

const usuario = process.argv[2];
const clave   = process.argv[3];

if (!usuario || !clave) {
    console.error('Uso: node sri_test_login.js RUC CLAVE');
    process.exit(1);
}

const URL_LOGIN = 'https://srienlinea.sri.gob.ec/comprobantes-electronicos-internet/pages/consultas/recibidos/comprobantesRecibidos.jsf';

(async () => {
    const browser = await puppeteerExtra.launch({
        headless: 'new',
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--disable-gpu'],
    });

    const page = await browser.newPage();
    await page.setViewport({ width: 1366, height: 768 });
    await page.setExtraHTTPHeaders({ 'Accept-Language': 'es-EC,es;q=0.9' });

    try {
        console.log('Navegando a:', URL_LOGIN);
        await page.goto(URL_LOGIN, { waitUntil: 'domcontentloaded', timeout: 20000 }).catch(() => {});
        await new Promise(r => setTimeout(r, 2000));

        console.log('URL después de navegar:', page.url());

        // ── Inspeccionar el formulario de login ──────────────────────────────
        const formInfo = await page.evaluate(() => {
            const inputs  = Array.from(document.querySelectorAll('input')).map(i =>
                `${i.tagName}[id="${i.id}" name="${i.name}" type="${i.type}" visible=${i.offsetParent !== null}]`
            );
            const buttons = Array.from(document.querySelectorAll('button, input[type=submit]')).map(b =>
                `${b.tagName}[id="${b.id}" type="${b.type}" text="${b.textContent?.trim().substring(0,30)}"]`
            );
            const form = document.querySelector('form');
            return {
                title:   document.title,
                formId:  form?.id || 'sin id',
                action:  form?.action || 'sin action',
                inputs,
                buttons,
            };
        });

        console.log('\n=== FORMULARIO KEYCLOAK ===');
        console.log('Title:', formInfo.title);
        console.log('Form id:', formInfo.formId);
        console.log('Form action:', formInfo.action);
        console.log('Inputs:');
        formInfo.inputs.forEach(i => console.log(' ', i));
        console.log('Buttons:');
        formInfo.buttons.forEach(b => console.log(' ', b));

        // ── Detectar campo usuario ───────────────────────────────────────────
        const selUsuario = await page.evaluate(() => {
            const candidatos = ['#usuario', '#username', 'input[name="username"]', 'input[name="usuario"]', 'input[type="text"]'];
            for (const sel of candidatos) {
                if (document.querySelector(sel)) return sel;
            }
            return null;
        });
        const selClave = await page.evaluate(() => {
            const candidatos = ['#password', 'input[name="password"]', 'input[type="password"]'];
            for (const sel of candidatos) {
                if (document.querySelector(sel)) return sel;
            }
            return null;
        });

        console.log('\nSelector usuario detectado:', selUsuario);
        console.log('Selector clave detectado:', selClave);

        if (!selUsuario || !selClave) {
            console.log('\nERROR: No se encontraron los campos del formulario.');
            await browser.close();
            return;
        }

        // ── Enfocar y escribir usuario ───────────────────────────────────────
        console.log('\nEscribiendo usuario...');
        await page.evaluate((sel) => document.querySelector(sel)?.focus(), selUsuario);
        await new Promise(r => setTimeout(r, 200));
        await page.keyboard.type(usuario, { delay: 100 });
        await new Promise(r => setTimeout(r, 300));

        // ── Escribir clave ───────────────────────────────────────────────────
        console.log('Escribiendo clave...');
        await page.evaluate((sel) => document.querySelector(sel)?.focus(), selClave);
        await new Promise(r => setTimeout(r, 200));
        await page.keyboard.type(clave, { delay: 100 });
        await new Promise(r => setTimeout(r, 300));

        // ── Verificar valores escritos ───────────────────────────────────────
        const escritos = await page.evaluate((su, sc) => ({
            usuario: document.querySelector(su)?.value || '',
            clave:   document.querySelector(sc)?.value?.replace(/./g, '*') || '',
        }), selUsuario, selClave);
        console.log('Valor usuario escrito:', escritos.usuario);
        console.log('Valor clave escrito:  ', escritos.clave);

        // ── Enviar formulario ────────────────────────────────────────────────
        const urlAntes = page.url();
        console.log('\nEnviando con Enter...');
        await page.keyboard.press('Enter');

        // Esperar cambio de URL
        const fin = Date.now() + 15000;
        while (Date.now() < fin) {
            await new Promise(r => setTimeout(r, 500));
            if (page.url() !== urlAntes) break;
        }

        console.log('URL después del login:', page.url());

        await new Promise(r => setTimeout(r, 3000));
        console.log('URL final (3s después):', page.url());

        // ── Resultado ────────────────────────────────────────────────────────
        const textoFinal = await page.$eval('body', el => el.innerText.substring(0, 400)).catch(() => '');
        console.log('\nTexto de la página final:');
        console.log(textoFinal);

        if (page.url().includes('/auth/realms/') || page.url().includes('login-actions')) {
            console.log('\n❌ LOGIN FALLIDO — todavía en Keycloak');
        } else {
            console.log('\n✅ LOGIN EXITOSO — URL fuera de Keycloak');
        }

    } finally {
        await browser.close();
    }
})();
