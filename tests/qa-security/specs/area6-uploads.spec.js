/**
 * ÁREA 6 — Upload de archivos (E3)
 * Verifica que el endpoint de upload:
 *   - Acepta imágenes válidas (jpg, png < 2MB)
 *   - Rechaza tipos peligrosos (php, exe)
 *   - Rechaza tipos no permitidos (pdf)
 *   - Rechaza archivos > tamaño máximo (10MB)
 *
 * Ejecutar via skill:
 *   cd C:\Users\luis_\.claude\skills\playwright-skill
 *   node run.js C:\wamp64\www\automatiza-tech\tests\qa-security\specs\area6-uploads.spec.js
 */

require('dotenv').config({
  path: 'C:\\wamp64\\www\\automatiza-tech\\tests\\qa-security\\.env.test',
});

const { chromium } = require('playwright');
const fs   = require('fs');
const path = require('path');
const os   = require('os');

const CFG = {
  portalUrl:  process.env.PORTAL_URL    || 'http://localhost/automatiza-tech/omnicliente/',
  agentEmail: process.env.AGENT_EMAIL   || '',
  agentPass:  process.env.AGENT_PASSWORD || '',
};

function assert(cond, msg) {
  if (cond) { console.log(`  ✅ ${msg}`); }
  else       { console.error(`  ❌ FALLO: ${msg}`); process.exitCode = 1; }
}

// Crea archivo temporal con tamaño y contenido específico
function createTempFile(name, content, sizeBytes) {
  const fpath = path.join(os.tmpdir(), name);
  if (sizeBytes) {
    const buf = Buffer.alloc(sizeBytes, 'A');
    fs.writeFileSync(fpath, buf);
  } else {
    fs.writeFileSync(fpath, content);
  }
  return fpath;
}

async function loginAsAgent(page) {
  await page.goto(CFG.portalUrl);
  // exact:true — sin esto "Agente" también matchea "Entrar como Agente" (strict mode violation)
  await page.getByRole('button', { name: 'Agente', exact: true }).click();
  await page.getByPlaceholder('tu@email.com').fill(CFG.agentEmail);
  await page.getByPlaceholder('Tu contraseña...').fill(CFG.agentPass);
  await page.getByRole('button', { name: 'Entrar como Agente' }).click();
  await page.waitForURL(/omnicliente/, { timeout: 15_000 });
  await page.waitForLoadState('networkidle');
  console.log('  → Agente logueado');
}

async function testArea6(browser) {
  console.log('\n=== ÁREA 6 — Upload de archivos ===');
  if (!CFG.agentPass) { console.log('  ⚠️  SKIP — AGENT_PASSWORD vacío en .env.test'); return; }

  const ctx  = await browser.newContext();
  const page = await ctx.newPage();

  await loginAsAgent(page);

  // Navegar a perfil → sección avatar
  const profileBtn = page.locator('[aria-label*="perfil" i], a[href*="perfil"], button:has-text("Perfil"), .profile-link, img.avatar').first();
  await profileBtn.click({ timeout: 8_000 }).catch(async () => {
    await page.goto(CFG.portalUrl + '#perfil');
  });
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(800);

  // Buscar input file de avatar
  const fileInput = page.locator('input[type="file"]').first();
  const hasInput  = await fileInput.isVisible({ timeout: 5_000 }).catch(() => false)
    || (await fileInput.count().catch(() => 0)) > 0;

  if (!hasInput) {
    console.log('  ⚠️  input[type="file"] no encontrado en perfil — verificar ruta manualmente');
    await page.screenshot({ path: '/tmp/area6-perfil.png' });
    console.log('  📸 Screenshot: /tmp/area6-perfil.png');
    await ctx.close();
    return;
  }

  // Archivos de prueba temporales
  const files = [
    { name: 'foto_valida.jpg',   mime: 'image/jpeg',       size: null,     content: Buffer.from('/9j/4AAQ', 'base64'), expect: 'accept' },
    { name: 'foto_valida.png',   mime: 'image/png',        size: null,     content: Buffer.from('iVBORw0KGgo=', 'base64'), expect: 'accept' },
    { name: 'virus.php',         mime: 'application/x-php', size: null,    content: '<?php echo "pwned"; ?>', expect: 'reject' },
    { name: 'script.exe',        mime: 'application/x-msdownload', size: null, content: 'MZ', expect: 'reject' },
    { name: 'documento.pdf',     mime: 'application/pdf',  size: null,     content: '%PDF-1.4', expect: 'reject' },
    { name: 'imagen_grande.jpg', mime: 'image/jpeg',       size: 11*1024*1024, content: null, expect: 'reject' },
  ];

  for (const f of files) {
    const fpath = createTempFile(f.name, f.content, f.size);

    // Interceptar respuesta de red del upload
    const [response] = await Promise.all([
      page.waitForResponse(
        r => r.url().includes('upload') || r.url().includes('avatar'),
        { timeout: 8_000 }
      ).catch(() => null),
      fileInput.setInputFiles({ name: f.name, mimeType: f.mime, buffer: fs.readFileSync(fpath) }),
    ]);

    if (response) {
      const status = response.status();
      const body   = await response.text().catch(() => '');
      const ok     = status >= 200 && status < 300 && !body.toLowerCase().includes('error');

      if (f.expect === 'accept') {
        assert(ok, `${f.name} aceptado (HTTP ${status})`);
      } else {
        assert(!ok || status === 400 || status === 415 || status === 422,
          `${f.name} rechazado (HTTP ${status})`);
      }
    } else {
      // Sin respuesta de red — buscar mensaje de error en UI
      await page.waitForTimeout(1_200);
      const errorMsg = await page.locator('.error, .alert-error, [role="alert"]').first()
        .textContent({ timeout: 2_000 }).catch(() => '');

      if (f.expect === 'reject') {
        assert(errorMsg.length > 0, `${f.name} rechazado (error UI visible)`);
      } else {
        const successMsg = await page.locator('.success, .alert-success').first()
          .textContent({ timeout: 2_000 }).catch(() => '');
        assert(successMsg.length > 0 || !errorMsg, `${f.name} aceptado (sin error UI)`);
      }
    }

    fs.unlinkSync(fpath);
  }

  await ctx.close();
}

(async () => {
  const browser = await chromium.launch({ headless: false, slowMo: 200 });
  try {
    await testArea6(browser);
  } finally {
    await browser.close();
  }
  console.log('\n' + (process.exitCode ? '❌ Algunos tests fallaron' : '✅ Área 6 completa'));
})();
