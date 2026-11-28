// Uso: NODE_PATH=<renderer>/node_modules node tests/plan/etapa2-e2e.js <url ver-plan.php?id=…&agendar=1> <carpeta capturas>
// Abre la página a 390 px y 1280 px, comprueba que el diálogo se abre solo, elige el primer horario libre de un día
// hábil de la semana siguiente y agenda; espera el mensaje «Listo: agendamos…». Sale con 1 si algo falla.
const { chromium } = require('playwright');
(async () => {
  const [url, carpeta] = process.argv.slice(2);
  if (!url || !carpeta) { console.error('faltan argumentos'); process.exit(2); }
  const nav = await chromium.launch();
  let fallas = 0;
  const ok = (c, m) => { console.log((c ? 'ok   ' : 'FALLA ') + m); if (!c) fallas++; };
  for (const [ancho, alto, agendar] of [[1280, 800, false], [390, 844, true]]) {
    const p = await nav.newPage({ viewport: { width: ancho, height: alto } });
    const errores = [];
    p.on('pageerror', e => errores.push(String(e)));
    await p.goto(url, { waitUntil: 'networkidle' });
    ok(await p.locator('dialog#at-pt-agenda[open]').count() === 1, ancho + ' px: el diálogo se abre solo con agendar=1');
    const dia = new Date(); dia.setDate(dia.getDate() + 7);
    while (dia.getDay() === 0 || dia.getDay() === 6) dia.setDate(dia.getDate() + 1);
    const ymd = dia.toISOString().slice(0, 10);
    await p.fill('#at-fecha', ymd);
    await p.dispatchEvent('#at-fecha', 'change');
    await p.waitForSelector('.at-slot-btn', { timeout: 10000 });
    ok(await p.locator('.at-slot-btn').count() > 0, ancho + ' px: muestra horarios libres');
    const anchoPagina = await p.evaluate(() => document.documentElement.scrollWidth);
    ok(anchoPagina <= ancho, ancho + ' px: sin desplazamiento horizontal');
    await p.screenshot({ path: carpeta + '/ver-plan-' + ancho + '.png' });
    if (agendar) {
      await p.locator('.at-slot-btn').first().click();
      await p.click('#at-pt-form-agenda button[type=submit]');
      await p.waitForSelector('.at-pt-msg--ok, .at-pt-msg--error', { timeout: 20000 });
      const texto = await p.textContent('#at-pt-agenda-msg');
      ok(/^Listo: agendamos tu llamada/.test(texto || ''), 'agenda y confirma: ' + texto);
    }
    ok(errores.length === 0, ancho + ' px: sin errores de JavaScript ' + errores.join(' | '));
    await p.close();
  }
  await nav.close();
  process.exit(fallas ? 1 : 0);
})();
