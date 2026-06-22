/**
 * sri_seed_profile.js — Siembra (una sola vez) el perfil persistente que usa el scraper.
 *
 * Objetivo: abrir Chrome real con el MISMO perfil que usará sri_scraper.js para que
 * inicies sesión en tu cuenta de Google. Esa sesión + cookies dan reputación al
 * navegador y hacen que reCAPTCHA v3 del SRI asigne un score alto (el navegador
 * automatizado "vacío" obtiene score bajo y el SRI lo rechaza con "captcha incorrecta").
 *
 * Uso:
 *   node scripts/sri_seed_profile.js
 *   (opcional) node scripts/sri_seed_profile.js "C:\\ruta\\perfil"
 *
 * Pasos:
 *   1. Se abre una ventana de Chrome.
 *   2. Inicia sesión en tu cuenta de Google (y, si quieres, navega un poco).
 *   3. Cierra la ventana. El perfil queda guardado y listo para el scraper.
 */

'use strict';

const { chromium } = require('playwright-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
chromium.use(StealthPlugin());

const path = require('path');

const profileDir = process.argv[2] || path.join(__dirname, '.sri_profile');

(async () => {
    console.log('Perfil a sembrar:', profileDir);
    console.log('Abriendo Chrome... inicia sesión en tu cuenta de Google y luego CIERRA la ventana.');

    // Chrome real en Windows; Chromium de Playwright en Linux (donde no hay Chrome).
    const channel = (process.platform === 'win32') ? 'chrome' : undefined;

    const context = await chromium.launchPersistentContext(profileDir, {
        headless: false,
        ...(channel ? { channel } : {}),
        viewport: { width: 1366, height: 768 },
        args: [
            '--no-sandbox', '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-blink-features=AutomationControlled'
        ],
    });

    const page = context.pages()[0] || await context.newPage();
    await page.goto('https://accounts.google.com/', { waitUntil: 'domcontentloaded' }).catch(() => {});

    // Esperar a que el usuario termine y cierre la ventana.
    await new Promise((resolve) => context.on('close', resolve));

    console.log('Perfil sembrado y guardado en:', profileDir);
    process.exit(0);
})().catch((e) => {
    console.error('Error sembrando el perfil:', e.message);
    process.exit(1);
});
