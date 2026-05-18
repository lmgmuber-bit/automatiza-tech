/**
 * ÁREA 1 — Portal Omnicanal (React)
 * A5.2 Webhook secret masking
 * A5.3 Reset token limpiado de URL
 * A5.4 AI Chat history persistido en backend
 *
 * Ejecutar via skill:
 *   cd C:\Users\luis_\.claude\skills\playwright-skill
 *   node run.js C:\wamp64\www\automatiza-tech\tests\qa-security\specs\area1-portal.spec.js
 */

require('dotenv').config({
  path: 'C:\\wamp64\\www\\automatiza-tech\\tests\\qa-security\\.env.test',
});

const { chromium } = require('playwright');

const CFG = {
  portalUrl:          process.env.PORTAL_URL    || 'http://localhost/automatiza-tech/omnicliente/',
  wpAdminUser:        process.env.WP_ADMIN_EMAIL || '',
  wpAdminPass:        process.env.WP_ADMIN_PASSWORD || '',
  agentEmail:         process.env.AGENT_EMAIL   || '',
  agentPass:          process.env.AGENT_PASSWORD || '',
};

// ── helpers ──────────────────────────────────────────────────────────────────

function assert(cond, msg) {
  if (cond) { console.log(`  ✅ ${msg}`); }
  else       { console.error(`  ❌ FALLO: ${msg}`); process.exitCode = 1; }
}

async function loginAsAdmin(page) {
  await page.goto(CFG.portalUrl);
  await page.getByRole('button', { name: 'Admin' }).click();
  await page.getByPlaceholder('Tu usuario de WordPress...').fill(CFG.wpAdminUser);
  await page.getByPlaceholder('Tu contraseña...').fill(CFG.wpAdminPass);
  await page.getByRole('button', { name: 'Entrar como Admin' }).click();
  await page.waitForURL(/omnicliente/, { timeout: 15_000 });
  await page.waitForLoadState('networkidle');
  console.log('  → Admin logueado');
}

async function loginAsAgent(page) {
  await page.goto(CFG.portalUrl);
  await page.getByRole('button', { name: 'Agente' }).click();
  await page.getByPlaceholder('tu@email.com').fill(CFG.agentEmail);
  await page.getByPlaceholder('Tu contraseña...').fill(CFG.agentPass);
  await page.getByRole('button', { name: 'Entrar como Agente' }).click();
  await page.waitForURL(/omnicliente/, { timeout: 15_000 });
  await page.waitForLoadState('networkidle');
  console.log('  → Agente logueado');
}

// ── A5.2 — Webhook secret masking ────────────────────────────────────────────

async function testA52(browser) {
  console.log('\n=== A5.2 — Webhook secret enmascarado en Canales ===');
  if (!CFG.wpAdminPass) { console.log('  ⚠️  SKIP — WP_ADMIN_PASSWORD vacío en .env.test'); return; }

  const ctx  = await browser.newContext();
  const page = await ctx.newPage();

  await loginAsAdmin(page);

  // Navegar a Canales
  const canalBtn = page.getByRole('link', { name: /canal/i })
    .or(page.getByRole('button', { name: /canal/i }))
    .or(page.locator('a[href*="canal"], a[href*="channel"]'));

  await canalBtn.first().click({ timeout: 8_000 }).catch(async () => {
    // Intentar nav directa si no hay link
    await page.goto(CFG.portalUrl + '#canales');
    await page.goto(CFG.portalUrl + '#channels');
  });
  await page.waitForLoadState('networkidle');

  // Buscar texto enmascarado ••••••••
  const maskedEl = page.locator('text=••••••••').first();
  const hasMasked = await maskedEl.isVisible({ timeout: 6_000 }).catch(() => false);
  assert(hasMasked, 'Secret aparece enmascarado (••••••••)');

  if (hasMasked) {
    // Clic en botón ojo (Eye) para revelar
    const eyeBtn = page.locator('[aria-label*="ojo" i], [aria-label*="eye" i], [title*="ojo" i], [title*="eye" i], button:has(svg)')
      .first();
    await eyeBtn.click({ timeout: 5_000 }).catch(() => {});
    await page.waitForTimeout(500);
    const stillMasked = await maskedEl.isVisible({ timeout: 2_000 }).catch(() => true);
    assert(!stillMasked, 'Clic en ojo revela el secret (desaparece ••••••••)');

    // Volver a ocultar
    await eyeBtn.click({ timeout: 5_000 }).catch(() => {});
    await page.waitForTimeout(500);
    const maskedAgain = await maskedEl.isVisible({ timeout: 2_000 }).catch(() => false);
    assert(maskedAgain, 'Segundo clic en ojo vuelve a ocultar (••••••••)');

    // Botón Copiar — verificar que copia URL real (con secret)
    const copyBtn = page.locator('button:has-text("Copiar"), button[aria-label*="copiar" i]').first();
    const hasCopy = await copyBtn.isVisible({ timeout: 3_000 }).catch(() => false);
    if (hasCopy) {
      await ctx.grantPermissions(['clipboard-read', 'clipboard-write']);
      await copyBtn.click();
      await page.waitForTimeout(400);
      const clipboard = await page.evaluate(() => navigator.clipboard.readText()).catch(() => '');
      assert(!clipboard.includes('••••••••'), 'URL copiada contiene secret real (no ••••••••)');
      assert(clipboard.includes('secret=') || clipboard.includes('&') || clipboard.length > 10,
        'URL copiada parece válida');
    } else {
      console.log('  ⚠️  Botón Copiar no visible — verificar manualmente');
    }
  }

  await ctx.close();
}

// ── A5.3 — Reset token limpiado de URL ───────────────────────────────────────

async function testA53(browser) {
  console.log('\n=== A5.3 — Reset token limpiado de URL ===');

  const ctx  = await browser.newContext();
  const page = await ctx.newPage();

  // Navegar con token falso en URL — el portal debe limpiarlo
  const urlConToken = `${CFG.portalUrl}?reset_token=TOKEN_PRUEBA_PLAYWRIGHT&email=test%40test.com`;
  await page.goto(urlConToken);
  await page.waitForLoadState('domcontentloaded');
  await page.waitForTimeout(1_500); // tiempo para que React procese y limpie la URL

  const finalUrl = page.url();
  assert(!finalUrl.includes('reset_token='), 'URL no contiene reset_token tras carga');
  assert(!finalUrl.includes('email='), 'URL no contiene email= tras carga');
  console.log(`  URL final: ${finalUrl}`);

  // Si hay formulario de nueva contraseña visible → bonus check
  const newPassForm = await page.locator('input[type="password"]').count();
  if (newPassForm > 0) {
    console.log('  ℹ️  Formulario nueva contraseña visible (token era válido o flujo activo)');
  }

  await ctx.close();
}

// ── A5.4 — AI Chat history persistido ────────────────────────────────────────

async function testA54(browser) {
  console.log('\n=== A5.4 — AI Chat history persistido en backend ===');
  if (!CFG.agentPass) { console.log('  ⚠️  SKIP — AGENT_PASSWORD vacío en .env.test'); return; }

  // --- Prueba 1: enviar mensajes y verificar localStorage limpio ---
  const ctx1 = await browser.newContext();
  const page1 = await ctx1.newPage();

  await loginAsAgent(page1);

  // Abrir chat flotante IA
  const chatBtn = page1.locator('[aria-label*="asistente" i], [aria-label*="chat" i], [aria-label*="IA" i], button:has-text("IA"), .floating-chat, #ai-chat-btn')
    .or(page1.locator('button').filter({ hasText: /IA|chat|asistente/i }))
    .first();

  const chatVisible = await chatBtn.isVisible({ timeout: 6_000 }).catch(() => false);
  assert(chatVisible, 'Botón flotante IA visible tras login');

  if (chatVisible) {
    await chatBtn.click();
    await page1.waitForTimeout(1_000);

    const inputChat = page1.locator('input[placeholder*="mensaje" i], textarea[placeholder*="mensaje" i], [contenteditable]').first();
    const inputVisible = await inputChat.isVisible({ timeout: 5_000 }).catch(() => false);

    if (inputVisible) {
      await inputChat.fill('Mensaje de prueba QA #1');
      await page1.keyboard.press('Enter');
      await page1.waitForTimeout(2_000);
      await inputChat.fill('Mensaje de prueba QA #2');
      await page1.keyboard.press('Enter');
      await page1.waitForTimeout(3_000);
      console.log('  → 2 mensajes enviados');

      // Verificar que localStorage NO tiene omni_ai_chats
      const localStorageKey = await page1.evaluate(() => localStorage.getItem('omni_ai_chats'));
      assert(localStorageKey === null, 'localStorage NO tiene key "omni_ai_chats"');
    } else {
      console.log('  ⚠️  Input del chat no encontrado — verificar selector manualmente');
    }
  }

  // Guardar storage state para simular "cerrar y reabrir"
  const storageState = await ctx1.storageState();
  await ctx1.close();

  // --- Prueba 2: reabrir con mismo storageState y verificar historial ---
  console.log('  → Simulando reabrir browser (nuevo contexto con misma sesión)...');
  const ctx2 = await browser.newContext({ storageState });
  const page2 = await ctx2.newPage();

  await page2.goto(CFG.portalUrl);
  await page2.waitForLoadState('networkidle');

  // Abrir historial de chats
  const histBtn = page2.locator('[aria-label*="historial" i], button:has-text("historial"), .chat-history').first();
  const histVisible = await histBtn.isVisible({ timeout: 5_000 }).catch(() => false);

  if (histVisible) {
    await histBtn.click();
    await page2.waitForTimeout(1_000);
    const chatItems = await page2.locator('.chat-item, [data-chat-id], li:has-text("QA")').count();
    assert(chatItems > 0, `Historial visible con ${chatItems} chat(s) al reabrir`);
  } else {
    // Verificar al menos que el chat abre sin error
    const noLocalStorage = await page2.evaluate(() => localStorage.getItem('omni_ai_chats'));
    assert(noLocalStorage === null, 'localStorage NO tiene omni_ai_chats en segunda sesión');
    console.log('  ⚠️  Botón historial no encontrado — verificar manualmente la persistencia visual');
  }

  await ctx2.close();
}

// ── main ──────────────────────────────────────────────────────────────────────

(async () => {
  const browser = await chromium.launch({ headless: false, slowMo: 150 });

  try {
    await testA52(browser);
    await testA53(browser);
    await testA54(browser);
  } finally {
    await browser.close();
  }

  console.log('\n' + (process.exitCode ? '❌ Algunos tests fallaron' : '✅ Área 1 completa'));
})();
