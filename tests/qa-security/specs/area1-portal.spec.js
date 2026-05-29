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

async function getTokenViaHttp(url, body) {
  const http = require('http');
  return new Promise((resolve, reject) => {
    const bodyStr = JSON.stringify(body);
    const req = http.request(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(bodyStr) },
    }, res => {
      let data = '';
      res.on('data', d => data += d);
      res.on('end', () => {
        try { resolve(JSON.parse(data)); }
        catch { reject(new Error('JSON parse error: ' + data.substring(0, 200))); }
      });
    });
    req.on('error', reject);
    req.setTimeout(10000, () => { req.destroy(); reject(new Error('HTTP timeout')); });
    req.write(bodyStr);
    req.end();
  });
}

async function loginAsAdmin(page) {
  const apiBase = 'http://localhost/automatiza-tech/api-omnichannel.php';
  const tokenData = await getTokenViaHttp(`${apiBase}?route=admin/login`, {
    username: CFG.wpAdminUser, password: CFG.wpAdminPass,
  });
  if (!tokenData.token) throw new Error('Admin login failed: ' + JSON.stringify(tokenData));
  console.log(`  [login] token obtained via Node HTTP: ${tokenData.token.substring(0, 20)}...`);

  // First load: no auth, no API calls from browser
  await page.goto(CFG.portalUrl, { waitUntil: 'domcontentloaded', timeout: 20_000 });
  console.log('  [login] portal loaded');

  // Inject auth flags: isAuthenticated()=true without any browser API call
  await page.evaluate(({ token, user }) => {
    localStorage.setItem('omni_admin_token', token);
    localStorage.setItem('omni_admin_user', user);
    localStorage.setItem('omni_is_admin', 'true');
  }, { token: tokenData.token, user: tokenData.user || CFG.wpAdminUser });

  // Reload: dashboard renders immediately (no session-check API call)
  await page.reload({ waitUntil: 'domcontentloaded', timeout: 20_000 });
  await page.waitForTimeout(1_000);
  console.log('  → Admin logueado');
}

async function loginAsAgent(page) {
  const apiBase = 'http://localhost/automatiza-tech/api-omnichannel.php';
  const tokenData = await getTokenViaHttp(`${apiBase}?route=agent/login`, {
    email: CFG.agentEmail, password: CFG.agentPass,
  });
  if (!tokenData.token) throw new Error('Agent login failed: ' + JSON.stringify(tokenData));
  console.log(`  [login] agent token obtained: ${tokenData.token.substring(0, 20)}...`);

  await page.goto(CFG.portalUrl, { waitUntil: 'domcontentloaded', timeout: 20_000 });
  await page.evaluate(({ token, agent }) => {
    localStorage.setItem('omni_agent_token', token);
    localStorage.setItem('omni_is_agent', 'true');
    if (agent) localStorage.setItem('omni_agent_data', JSON.stringify(agent));
  }, { token: tokenData.token, agent: tokenData.agent || null });
  await page.reload({ waitUntil: 'domcontentloaded', timeout: 20_000 });
  await page.waitForTimeout(1_000);
  console.log('  → Agente logueado');
}

// ── mock helper ──────────────────────────────────────────────────────────────
const MOCK_CHANNEL = {
  id: 999, name: 'Canal QA Test', active: true,
  webhook_secret: 'supersecret_qa_12345', channel_type: 'whatsapp',
  channel_type_id: 1, phone_number: '+56900000000', client_id: 1,
};

async function mockApiRoutes(page) {
  // Intercept ALL api-omnichannel calls so none hang the browser
  await page.route('**/api-omnichannel.php**', async route => {
    const url = route.request().url();
    const method = route.request().method();

    if (url.includes('route=channels') && !url.includes('channels%2F') && !url.includes('channels/') && method === 'GET') {
      await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify([MOCK_CHANNEL]) });
    } else if (url.includes('route=channel-types') || url.includes('route=clients')) {
      await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify([]) });
    } else if (url.includes('route=agent/session-check') || url.includes('route=admin/session-check')) {
      // Already bypassed via localStorage flags — but abort if it sneaks through
      await route.abort();
    } else {
      // All other calls: return generic 200 to prevent network-idle hanging
      await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true }) });
    }
  });
}

// ── A5.2 — Webhook secret masking ────────────────────────────────────────────

async function testA52(browser) {
  console.log('\n=== A5.2 — Webhook secret enmascarado en Canales ===');
  if (!CFG.wpAdminPass) { console.log('  ⚠️  SKIP — WP_ADMIN_PASSWORD vacío en .env.test'); return; }

  const ctx  = await browser.newContext();
  const page = await ctx.newPage();

  // Mock API calls BEFORE loading the page (prevents browser fetch hangs)
  await mockApiRoutes(page);
  await loginAsAdmin(page);

  // Navegar a Canales — sidebar renders <button> with text "Canales"
  await page.locator('button').filter({ hasText: /^Canales$/ }).first()
    .click({ timeout: 8_000 }).catch(() =>
      page.locator('button:has-text("Canales")').first().click({ timeout: 5_000 }).catch(() => {})
    );
  await page.waitForTimeout(2_000);

  await page.screenshot({ path: '/tmp/area1-a52-canales.png' });
  console.log('  📸 /tmp/area1-a52-canales.png');
  console.log('  → En sección Canales Conectados');

  // Buscar texto enmascarado ••••••••
  const maskedEl = page.locator('text=••••••••').first();
  const hasMasked = await maskedEl.isVisible({ timeout: 6_000 }).catch(() => false);
  assert(hasMasked, 'Secret aparece enmascarado (••••••••)');

  if (hasMasked) {
    // Clic en botón ojo (Eye/EyeOff) para revelar — cerca del masked text
    const cardWithSecret = page.locator('text=••••••••').locator('../..');
    const eyeBtn = cardWithSecret.locator('button:has(svg)').last();
    await eyeBtn.click({ timeout: 5_000 }).catch(() => {
      // Fallback: cualquier botón con SVG cerca del masked text
      page.locator('button:has(svg)').nth(1).click().catch(() => {});
    });
    await page.waitForTimeout(600);
    const stillMasked = await maskedEl.isVisible({ timeout: 2_000 }).catch(() => true);
    assert(!stillMasked, 'Clic en ojo revela el secret (desaparece ••••••••)');

    // Volver a ocultar
    await eyeBtn.click({ timeout: 5_000 }).catch(() => {});
    await page.waitForTimeout(600);
    const maskedAgain = await maskedEl.isVisible({ timeout: 2_000 }).catch(() => false);
    assert(maskedAgain, 'Segundo clic en ojo vuelve a ocultar (••••••••)');

    // Copiar URL con secret real
    const copyBtn = page.locator('button:has-text("Copiar"), button[title*="Copiar" i]').first();
    const hasCopy = await copyBtn.isVisible({ timeout: 3_000 }).catch(() => false);
    if (hasCopy) {
      await ctx.grantPermissions(['clipboard-read', 'clipboard-write']);
      await copyBtn.click();
      await page.waitForTimeout(400);
      const clipboard = await page.evaluate(() => navigator.clipboard.readText()).catch(() => '');
      assert(!clipboard.includes('••••••••'), 'URL copiada contiene secret real (no ••••••••)');
      assert(clipboard.includes('supersecret_qa_12345') || clipboard.includes('secret='),
        'URL copiada incluye el secret correcto');
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

  await mockApiRoutes(page1);
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

  // Mock API calls in new context too
  await mockApiRoutes(page2);
  await page2.goto(CFG.portalUrl);
  await page2.waitForLoadState('domcontentloaded'); // don't wait for networkidle (API calls mocked)

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
  const browser = await chromium.launch({ headless: true });

  try {
    await testA52(browser);
    await testA53(browser);
    await testA54(browser);
  } finally {
    await browser.close();
  }

  console.log('\n' + (process.exitCode ? '❌ Algunos tests fallaron' : '✅ Área 1 completa'));
})();
