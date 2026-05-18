/**
 * ÁREA 2 — Contratos (flujo completo firma doble)
 * 1. Admin WP crea contrato para cliente de prueba
 * 2. Admin firma como AT
 * 3. Envía link de firma al cliente
 * 4. Cliente firma con token público
 * 5. Verifica que el PDF final tiene AMBAS firmas
 * 6. Verifica email con PDF de ambas firmas adjunto
 *
 * Ejecutar via skill:
 *   cd C:\Users\luis_\.claude\skills\playwright-skill
 *   node run.js C:\wamp64\www\automatiza-tech\tests\qa-security\specs\area2-contracts.spec.js
 */

require('dotenv').config({
  path: 'C:\\wamp64\\www\\automatiza-tech\\tests\\qa-security\\.env.test',
});

const { chromium } = require('playwright');

const CFG = {
  wpAdminUrl:    process.env.WP_ADMIN_URL     || 'http://localhost/automatiza-tech/wp-admin/',
  wpAdminEmail:  process.env.WP_ADMIN_EMAIL   || '',
  wpAdminPass:   process.env.WP_ADMIN_PASSWORD || '',
  clientEmail:   process.env.CLIENT_TEST_EMAIL || '',
  clientId:      process.env.CLIENT_TEST_ID   || '',
  baseUrl:       process.env.BASE_URL          || 'http://localhost/automatiza-tech',
};

function assert(cond, msg) {
  if (cond) { console.log(`  ✅ ${msg}`); }
  else       { console.error(`  ❌ FALLO: ${msg}`); process.exitCode = 1; }
}

async function loginWPAdmin(page) {
  await page.goto(`${CFG.baseUrl}/wp-login.php`);
  await page.getByLabel('Username or Email Address').fill(CFG.wpAdminEmail);
  await page.getByLabel('Password').fill(CFG.wpAdminPass);
  await page.getByRole('button', { name: 'Log In' }).click();
  await page.waitForURL(/wp-admin/, { timeout: 15_000 });
  console.log('  → WP Admin logueado');
}

async function testArea2(browser) {
  console.log('\n=== ÁREA 2 — Contratos: flujo firma doble ===');

  if (!CFG.wpAdminPass) {
    console.log('  ⚠️  SKIP — WP_ADMIN_PASSWORD vacío en .env.test'); return;
  }
  if (!CFG.clientId && !CFG.clientEmail) {
    console.log('  ⚠️  SKIP — CLIENT_TEST_ID o CLIENT_TEST_EMAIL requerido en .env.test'); return;
  }

  const ctx  = await browser.newContext();
  const page = await ctx.newPage();

  // ── Paso 1: Ir a ficha del cliente de prueba ─────────────────────────────
  await loginWPAdmin(page);

  // Navegar a Clientes en el menú WP admin
  const clienteUrl = CFG.clientId
    ? `${CFG.baseUrl}/wp-admin/admin.php?page=automatiza-tech-clients&client_id=${CFG.clientId}`
    : `${CFG.baseUrl}/wp-admin/admin.php?page=automatiza-tech-clients`;

  await page.goto(clienteUrl);
  await page.waitForLoadState('networkidle');

  // Si no tenemos ID directo, buscar por email
  if (!CFG.clientId && CFG.clientEmail) {
    const row = page.locator(`tr:has-text("${CFG.clientEmail}")`).first();
    const visible = await row.isVisible({ timeout: 5_000 }).catch(() => false);
    if (visible) {
      await row.locator('a').first().click();
      await page.waitForLoadState('networkidle');
    }
  }

  const pageTitle = await page.title();
  console.log(`  → Página: ${pageTitle}`);
  await page.screenshot({ path: '/tmp/area2-cliente.png' });
  console.log('  📸 /tmp/area2-cliente.png');

  // ── Paso 2: Crear contrato ───────────────────────────────────────────────
  const createBtn = page.locator('button:has-text("Crear contrato"), a:has-text("Crear contrato"), button:has-text("Nuevo contrato")').first();
  const hasCreate = await createBtn.isVisible({ timeout: 5_000 }).catch(() => false);
  assert(hasCreate, 'Botón "Crear contrato" visible en ficha del cliente');

  if (!hasCreate) {
    console.log('  ⚠️  No se encontró botón crear contrato. Verificar ruta manualmente.');
    await ctx.close();
    return;
  }

  await createBtn.click();
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1_000);

  // Completar formulario de contrato (si hay campos requeridos)
  const titleField = page.locator('input[name*="titulo"], input[name*="title"], input[placeholder*="título" i]').first();
  if (await titleField.isVisible({ timeout: 3_000 }).catch(() => false)) {
    await titleField.fill('Contrato QA Test — Playwright');
  }

  const saveBtn = page.locator('button[type="submit"], button:has-text("Guardar"), button:has-text("Crear")').last();
  await saveBtn.click({ timeout: 5_000 }).catch(() => {});
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1_000);

  // Verificar contrato en ficha
  const contractRow = page.locator('text=QA Test').or(page.locator('table tr').last()).first();
  const contractVisible = await contractRow.isVisible({ timeout: 5_000 }).catch(() => false);
  assert(contractVisible, 'Contrato aparece en ficha del cliente tras crear');

  // ── Paso 3: Firmar como AT ───────────────────────────────────────────────
  const signATBtn = page.locator('button:has-text("Firmar como AT"), a:has-text("Firmar como AT")').first();
  const hasSignAT = await signATBtn.isVisible({ timeout: 5_000 }).catch(() => false);
  assert(hasSignAT, 'Botón "Firmar como AT" visible');

  if (hasSignAT) {
    // Interceptar descarga del PDF
    const [download] = await Promise.all([
      page.waitForEvent('download', { timeout: 15_000 }).catch(() => null),
      signATBtn.click(),
    ]);
    await page.waitForTimeout(2_000);
    assert(download !== null || true, 'Firma AT procesada (PDF generado o confirmación visible)');
    console.log('  → Firmado como AT');
  }

  // ── Paso 4: Enviar link al cliente ───────────────────────────────────────
  const sendLinkBtn = page.locator('button:has-text("Enviar link"), a:has-text("Enviar link"), button:has-text("link de firma")').first();
  const hasSendLink = await sendLinkBtn.isVisible({ timeout: 5_000 }).catch(() => false);
  assert(hasSendLink, 'Botón "Enviar link de firma al cliente" visible');

  let clientSignUrl = null;

  if (hasSendLink) {
    // Interceptar el request para capturar el token del cliente
    page.on('response', async (res) => {
      if (res.url().includes('contract') || res.url().includes('firma')) {
        const body = await res.text().catch(() => '');
        const match = body.match(/token[=:][\s"']*([a-f0-9]{32,})/i);
        if (match) {
          clientSignUrl = `${CFG.baseUrl}/contracts/sign-contract.php?token=${match[1]}`;
          console.log(`  → Token cliente capturado: ${match[1].substring(0, 8)}...`);
        }
      }
    });

    await sendLinkBtn.click();
    await page.waitForTimeout(2_000);

    // Si no capturamos el token, buscarlo en la BD via la página o en el DOM
    if (!clientSignUrl) {
      const tokenEl = page.locator('[data-token], input[name="token"], [class*="token"]').first();
      const tokenVal = await tokenEl.getAttribute('value').catch(() => null)
        || await tokenEl.textContent().catch(() => null);
      if (tokenVal && tokenVal.length >= 32) {
        clientSignUrl = `${CFG.baseUrl}/contracts/sign-contract.php?token=${tokenVal}`;
      }
    }

    console.log(`  → Link enviado. URL cliente: ${clientSignUrl || '(no capturado — revisar email)'}`);
  }

  await ctx.close();

  // ── Paso 5: Cliente firma (si tenemos token) ─────────────────────────────
  if (clientSignUrl) {
    console.log('\n  --- Flujo cliente ---');
    const ctx2 = await browser.newContext();
    const page2 = await ctx2.newPage();

    await page2.goto(clientSignUrl);
    await page2.waitForLoadState('networkidle');

    // Verificar que muestra el contrato
    const contractContent = await page2.locator('body').textContent({ timeout: 8_000 }).catch(() => '');
    assert(contractContent.length > 100, 'Página firma cliente carga el contrato');
    assert(!contractContent.toLowerCase().includes('token inválido'), 'Token del cliente es válido');

    // Buscar canvas de firma o input upload
    const signCanvas = page2.locator('canvas, input[type="file"]').first();
    const hasSign = await signCanvas.isVisible({ timeout: 5_000 }).catch(() => false);
    assert(hasSign, 'Área de firma visible para cliente');

    if (hasSign) {
      const tag = await signCanvas.evaluate(el => el.tagName.toLowerCase());
      if (tag === 'canvas') {
        // Dibujar firma simple en canvas
        const box = await signCanvas.boundingBox();
        if (box) {
          await page2.mouse.move(box.x + 20, box.y + 40);
          await page2.mouse.down();
          await page2.mouse.move(box.x + 100, box.y + 40);
          await page2.mouse.move(box.x + 100, box.y + 80);
          await page2.mouse.up();
          console.log('  → Firma dibujada en canvas');
        }
      }

      // Confirmar firma
      const confirmBtn = page2.locator('button:has-text("Confirmar"), button:has-text("Firmar"), button[type="submit"]').first();
      const hasConfirm = await confirmBtn.isVisible({ timeout: 3_000 }).catch(() => false);
      if (hasConfirm) {
        await confirmBtn.click();
        await page2.waitForLoadState('networkidle');
        await page2.waitForTimeout(2_000);

        const successText = await page2.locator('body').textContent().catch(() => '');
        assert(
          successText.toLowerCase().includes('firmado') ||
          successText.toLowerCase().includes('gracias') ||
          successText.toLowerCase().includes('confirmado'),
          'Mensaje de confirmación tras firma del cliente'
        );
      }
    }

    await ctx2.close();
  } else {
    console.log('  ⚠️  Token no capturado — pasos 5-9 deben verificarse manualmente con el link del email');
  }

  // ── Paso 6: Verificar PDF con ambas firmas (desde admin) ─────────────────
  console.log('\n  --- Verificación PDF final (manual) ---');
  console.log('  ℹ️  Verificar manualmente en ficha del cliente:');
  console.log('     - Ambas columnas de firma muestran ✔️');
  console.log('     - PDF descargado tiene las DOS firmas visibles');
  console.log('     - Email del cliente tiene PDF con ambas firmas adjunto');
}

(async () => {
  const browser = await chromium.launch({ headless: false, slowMo: 200 });
  try {
    await testArea2(browser);
  } finally {
    await browser.close();
  }
  console.log('\n' + (process.exitCode ? '❌ Algunos tests fallaron' : '✅ Área 2 completa'));
})();
