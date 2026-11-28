# Estado del proyecto — Etapa 1 (plan: botón, cronograma y «Actualizar la vista») Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** El plan de actividades gana el botón «Ver detalle del avance», su cronograma deja de pisar las etiquetas de los hitos, Luis puede rehacer la vista de un plan ya enviado sin costo, y existe un `ver-estado.php` mínimo para que el botón nunca lleve a un error.

**Architecture:** Tres piezas que ya existen, cada una con un cambio acotado. Renderer (`renderer/src/template-plan.js`): botón en la lámina del Método AT y carriles de hitos dinámicos. n8n («Plan de trabajo · 3 Render», generado por `N8N/plan-trabajo/build_plan_3_render.py`): bandera `mantener` que reusa las fotos del propio plan, no revisa texto, no cambia el estado y no manda correo. WordPress (`inc/plan-trabajo/`): `estado_url` en el cuerpo del render, `/vista` con `mantener`, botón de panel y página `ver-estado.php`.

**Tech Stack:** Node 24 + `node:test` + Playwright 1.62.1 (renderer); Python 3 que genera JSON de n8n y lo prueba con `node` (flujo); PHP 8.4 + WordPress local de prueba (tema).

**Spec:** `Docs/superpowers/specs/2026-10-13-estado-del-proyecto-design.md` (secciones 6, 7 «ver-estado.php» y 11, etapa 1).

## Global Constraints

- Todo enlace que llega al cliente va por `https://automatizatech.cl/...`; nunca `*.easypanel.host` en correos ni en textos al cliente (SMTP de Hostinger 554).
- El `payload` del plan firmado no se modifica. «Actualizar la vista» renderiza el mismo contenido.
- «Actualizar la vista» no genera fotos nuevas, no consulta a OpenAI, no cambia el estado del plan y no manda correo.
- El HTML de las propuestas no cambia ni un byte: `renderer/test/template-propuesta-igual.test.js` sigue pasando sin tocar sus hashes.
- Textos en español de Chile, claros, en plural de equipo.
- `functions.php` no se toca. Rama `claude/estado-proyecto`; sin merge ni despliegue sin el OK de Luis.
- Repo público: nada de vulnerabilidades ni secretos en commits.
- Archivos del repo en CRLF: editar con la herramienta Edit, no con `sed` de patrones.

## Review Focus

1. **Un plan o una propuesta sin `estado_url`** (planes viejos ya renderizados, propuestas): el botón no aparece y nada más cambia. → prueba en Task 2.
2. **`estado_url` con un esquema raro** (`javascript:`, vacío, con comillas): no se dibuja el botón ni se rompe el HTML. → prueba en Task 2.
3. **Plan con 0, 1 o 2 hitos:** el cronograma queda exactamente como hoy (fila de 72 px); solo con choques se abre el tercer carril. → pruebas en Task 1.
4. **«Actualizar la vista» con una lámina que no está en el manifiesto del plan:** no se paga una foto nueva; la lámina sale sin foto y la nota lo dice. → prueba en Task 3.
5. **`/vista` con `mantener` sobre un plan que no está `enviado`** (o un `final` tardío): nunca cambia el estado; solo guarda enlaces si salió bien. → prueba en Task 4.

---

### Task 0: Preparar el sitio local de prueba

**Files:** ninguno del repo.

- [ ] **Step 1: Apuntar las uniones del WordPress local a este worktree**

El sitio de prueba vive en `C:/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad/wp-local-plan` (en adelante `$SCR/wp-local-plan`). Sus carpetas `wp-content/mu-plugins` y `wp-content/themes/automatiza-tech` son uniones (junctions). Rehacerlas hacia `C:\wamp64\www\automatiza-tech\.worktrees\estado-proyecto`:

```powershell
$S = "C:\Users\luis_\AppData\Local\Temp\claude\C--wamp64-www-automatiza-tech\be929449-8523-4c30-a49a-56a73bb785ab\scratchpad\wp-local-plan"
$W = "C:\wamp64\www\automatiza-tech\.worktrees\estado-proyecto"
cmd /c rmdir "$S\wp-content\mu-plugins"
cmd /c mklink /J "$S\wp-content\mu-plugins" "$W\wp-content\mu-plugins"
cmd /c rmdir "$S\wp-content\themes\automatiza-tech"
cmd /c mklink /J "$S\wp-content\themes\automatiza-tech" "$W\wp-content\themes\automatiza-tech"
```

(`rmdir` sobre una unión borra solo la unión, nunca su contenido.)

- [ ] **Step 2: Verificar que las suites de base pasan antes de tocar nada**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/estado-proyecto
(cd renderer && npm test 2>&1 | tail -5)
python N8N/plan-trabajo/probar_plan.py render_puras correos_render render 2>&1 | tail -3
/c/wamp64/bin/php/php8.4.15/php.exe tests/plan/render-test.php | tail -1
AT_WP_LOAD=$SCR/wp-local-plan/wp-load.php /c/wamp64/bin/php/php8.4.15/php.exe tests/plan/rest-render-wp-test.php | tail -1
AT_WP_LOAD=$SCR/wp-local-plan/wp-load.php /c/wamp64/bin/php/php8.4.15/php.exe tests/plan/ver-plan-wp-test.php | tail -1
```

Expected: todo en verde («pass», «TODO OK»). Si algo falla aquí, anotarlo en el ledger como falla previa y no seguir hasta entenderla. Si `renderer/node_modules` no existe, correr `npm ci` en `renderer/` primero.

---

### Task 1: Cronograma — tercer carril para las etiquetas de los hitos

**Files:**
- Modify: `renderer/src/template-plan.js` (constantes 117-122, `medidasGantt` 124-128, `carrilesHitos` 180-191, `renderGantt` 193-284, CSS `ESTILO_GANTT` 351-361, `module.exports` 938-959)
- Test: `renderer/test/template-plan.test.js` (pruebas unitarias nuevas), `renderer/test/template-plan-layout.test.js` (medición en Chromium nueva)

**Interfaces:**
- Produces: `carrilesHitos(hitos, xs) -> Array<{carril: 0|1|2, izq: boolean, corrido: number}>` (exportada); `medidasGantt(nFilas, nFases, conHitos, carriles = 2)`; constante `GANTT_ALTO_HITOS_3 = 104`.

Causa medida con el plan de Orly: hitos el 08-oct, 04-nov, 13-nov, 25-nov («Entrega estimada») y 02-dic. Con dos carriles, «Asistente operativo · 2 dic» va a la izquierda de su rombo (`is-izq`, no cabe a la derecha), no encuentra carril libre y cae sobre «Sitio publicado y campaña activa · 13 nov».

- [ ] **Step 1: Escribir las pruebas que fallan**

En `renderer/test/template-plan.test.js`, al final, agregar:

```js
const { carrilesHitos, medidasGantt, renderGantt } = require('../src/template-plan');

// Ancho que el algoritmo le asigna a cada etiqueta, en fracción de la pista (mismo cálculo que carrilesHitos).
function anchoEtiqueta(nombre) {
  return ((String(nombre).length + 9) * 9.5) / 1300;
}
// Tramo [desde, hasta] que ocupa cada etiqueta según el resultado de carrilesHitos.
function tramos(hitos, xs, carriles) {
  return hitos.map((h, i) => {
    const a = anchoEtiqueta(h.nombre);
    const { izq, corrido, carril } = carriles[i];
    const desde = izq ? xs[i] - a : xs[i] + corrido / 1300;
    return { carril, desde, hasta: desde + a };
  });
}

test('carrilesHitos: los 5 hitos de Orly no comparten tramo en el mismo carril', () => {
  const hitos = [
    { nombre: 'Sitio sin fotos del personal antiguo', fecha: '2026-10-08' },
    { nombre: 'Sitio rediseñado y campaña lista', fecha: '2026-11-04' },
    { nombre: 'Sitio publicado y campaña activa', fecha: '2026-11-13' },
    { nombre: 'Entrega estimada', fecha: '2026-11-25' },
    { nombre: 'Asistente operativo', fecha: '2026-12-02' },
  ];
  const xs = [0.14, 0.49, 0.61, 0.77, 0.86];
  const c = carrilesHitos(hitos, xs);
  const t = tramos(hitos, xs, c);
  for (let i = 0; i < t.length; i++) {
    for (let j = i + 1; j < t.length; j++) {
      if (t[i].carril !== t[j].carril) continue;
      assert.ok(t[i].hasta <= t[j].desde || t[j].hasta <= t[i].desde, `«${hitos[i].nombre}» y «${hitos[j].nombre}» se pisan en el carril ${t[i].carril}`);
    }
  }
  assert.ok(c.some((x) => x.carril === 2), 'con tres hitos pegados se abre el tercer carril');
});

test('carrilesHitos: con 1 o 2 hitos separados no se usa el tercer carril', () => {
  const dos = carrilesHitos([{ nombre: 'A', fecha: '2026-10-08' }, { nombre: 'B', fecha: '2026-12-01' }], [0.1, 0.8]);
  assert.ok(dos.every((x) => x.carril < 2));
  assert.deepEqual(carrilesHitos([], []), []);
});

test('medidasGantt: con tres carriles la fila de hitos crece y las barras ceden ese alto', () => {
  const dos = medidasGantt(10, 3, true);
  const tres = medidasGantt(10, 3, true, 3);
  assert.ok(tres.fila <= dos.fila);
  assert.equal(medidasGantt(10, 3, true, 2).fila, dos.fila, 'dos carriles = lo de siempre');
});

test('renderGantt: sin choques no aparece la marca de tres carriles', () => {
  const html = renderGantt({ inicio: '2026-10-05', fin: '2026-11-27', barras: [{ fase: 'diseno_desarrollo', etiqueta: 'x', tipo: 'trabajo', responsable: 'at', desde: '2026-10-05', hasta: '2026-10-09' }], hitos: [{ nombre: 'Entrega estimada', fecha: '2026-11-27' }] });
  assert.ok(!html.includes('is-3carriles'));
});
```

(Si el archivo ya define `test`/`assert` arriba, no repetir esos `require`; sí agregar el `require` de las tres funciones.)

- [ ] **Step 2: Correr y ver que fallan**

Run: `cd renderer && node --test test/template-plan.test.js 2>&1 | tail -15`
Expected: FAIL — `carrilesHitos is not a function` (no está exportada).

- [ ] **Step 3: Implementar**

En `renderer/src/template-plan.js`:

1. Junto a las constantes del Gantt (después de `GANTT_ALTO_HITOS = 72`), agregar:

```js
// Con tres hitos pegados (por ejemplo, entrega, revisión y un bloque después) dos carriles no alcanzan: la fila de
// hitos crece a tres carriles (dos abajo del rombo) y las barras ceden ese alto.
const GANTT_ALTO_HITOS_3 = 104;
```

2. Reemplazar `medidasGantt` por:

```js
function medidasGantt(nFilas, nFases, conHitos, carriles = 2) {
  const altoHitos = conHitos ? (carriles > 2 ? GANTT_ALTO_HITOS_3 : GANTT_ALTO_HITOS) : 0;
  const libre = GANTT_ALTO - nFases * GANTT_ALTO_FASE - altoHitos;
  const fila = Math.max(1, Math.min(52, Math.floor(libre / Math.max(1, nFilas))));
  return { fila, denso: fila < 34 };
}
```

3. Reemplazar `carrilesHitos` por (tres carriles; cada carril guarda los tramos ocupados, así una etiqueta a la izquierda de su rombo ve también las anteriores):

```js
// Reparte las etiquetas de los hitos en carriles para que no se pisen: 0 arriba del rombo, 1 abajo y, solo si hace
// falta, 2 más abajo. Cada etiqueta va al primer carril donde su tramo no toca a otra; si no cabe a la derecha del
// rombo, va a su izquierda. Si no queda carril libre ni en el tercero, se corre a la derecha hasta donde termina la
// anterior de su carril (solo las que van a la derecha; las de la izquierda quedan en el carril menos ocupado).
function carrilesHitos(hitos, xs) {
  const ocupado = [[], [], []];
  const libre = (carril, desde, hasta) => ocupado[carril].every(([a, b]) => hasta + 0.008 <= a || b + 0.008 <= desde);
  return hitos.map((h, i) => {
    const ancho = ((texto(h.nombre).length + 9) * GANTT_LETRA) / GANTT_PISTA;
    const izq = xs[i] + ancho + 0.012 > 1;
    const desde = izq ? xs[i] - ancho : xs[i];
    let carril = [0, 1, 2].find((c) => libre(c, desde, desde + ancho));
    let corrido = 0;
    if (carril === undefined) {
      const fin = ocupado.map((l) => (l.length ? Math.max(...l.map((t) => t[1])) : -Infinity));
      carril = fin.indexOf(Math.min(...fin));
      corrido = izq ? 0 : Math.max(0, Math.min(fin[carril] + 0.008 - desde, 1 - ancho - desde));
    }
    ocupado[carril].push([desde + corrido, desde + corrido + ancho]);
    return { carril, izq, corrido: Math.round(corrido * GANTT_PISTA) };
  });
}
```

4. En `renderGantt`, calcular los carriles **antes** de las medidas. Mover el bloque `const xs = hitos.map(hitoX); const carriles = carrilesHitos(hitos, xs);` justo después de definir `hitoX` (antes de la línea `const { fila, denso } = medidasGantt(...)`), y cambiar esa llamada a:

```js
  const nCarriles = carriles.reduce((m, c) => Math.max(m, c.carril + 1), 0);
  const { fila, denso } = medidasGantt(nFilas, grupos.length, hitos.length > 0, nCarriles);
```

(`hitoX` y `lunes0`/`totalDias` ya están definidos antes; si `hitoX` se define después de `medidasGantt`, moverlo también. No cambiar su fórmula.)

5. En el armado de `hitosHtml`, la clase de cada etiqueta según el carril y la marca en la fila:

```js
          const clases = ['gantt-hito', h.nombre === 'Entrega estimada' ? 'is-entrega' : '', ['is-arriba', 'is-abajo', 'is-abajo2'][carril], izq ? 'is-izq' : '']
```

y la fila:

```js
    ? `<div class="gantt-fila gantt-fila-hitos${nCarriles > 2 ? ' is-3carriles' : ''}"><div class="gantt-etiqueta"><b>Hitos</b></div><div class="gantt-pista">${hitos
```

6. En `ESTILO_GANTT`, junto a `.gantt-hito.is-abajo em { top: 14px; }`, agregar:

```css
  .gantt-fila-hitos.is-3carriles { height: 104px; }
  .gantt-fila-hitos.is-3carriles .gantt-hito { top: 40%; }
  .gantt-hito.is-abajo2 em { top: 40px; }
```

7. Agregar `carrilesHitos` al `module.exports` del archivo.

- [ ] **Step 4: Correr las pruebas unitarias**

Run: `cd renderer && node --test test/template-plan.test.js 2>&1 | tail -15`
Expected: PASS, incluidas las pruebas que ya había.

- [ ] **Step 5: Medición en Chromium con el cronograma de Orly**

En `renderer/test/template-plan-layout.test.js`, agregar una prueba que reutiliza su `medir()` existente pero mide las etiquetas de los hitos. Agregar al final:

```js
test('cronograma: las etiquetas de los 5 hitos de Orly no se intersectan y caben en la lámina', async () => {
  const plan = planLargo();
  plan.cronograma.hitos = [
    { nombre: 'Sitio sin fotos del personal antiguo', fecha: '2026-10-08' },
    { nombre: 'Sitio rediseñado y campaña lista', fecha: '2026-11-04' },
    { nombre: 'Sitio publicado y campaña activa', fecha: '2026-11-13' },
    { nombre: 'Entrega estimada', fecha: '2026-11-25' },
    { nombre: 'Asistente operativo', fecha: '2026-12-02' },
  ];
  plan.cronograma.inicio = '2026-10-02';
  plan.cronograma.fin = '2026-12-10';
  const { chromium } = require('playwright');
  const os = require('node:os');
  const fs = require('node:fs');
  const path = require('node:path');
  const { pathToFileURL } = require('node:url');
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'gantt-hitos-'));
  const archivo = path.join(dir, 'index.html');
  fs.writeFileSync(archivo, renderPlanHtml(plan, {}));
  const browser = await chromium.launch({ args: ['--disable-dev-shm-usage', '--no-sandbox'] });
  try {
    const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });
    await page.goto(pathToFileURL(archivo).href, { waitUntil: 'load' });
    await page.emulateMedia({ media: 'print' });
    const r = await page.evaluate(() => {
      const ems = [...document.querySelectorAll('.gantt-fila-hitos .gantt-hito em')].map((e) => e.getBoundingClientRect());
      const slide = document.querySelector('.gantt-fila-hitos').closest('.slide').getBoundingClientRect();
      let choques = 0;
      for (let i = 0; i < ems.length; i++) for (let j = i + 1; j < ems.length; j++) {
        const a = ems[i], b = ems[j];
        if (a.left < b.right && b.left < a.right && a.top < b.bottom && b.top < a.bottom) choques++;
      }
      const fuera = ems.filter((e) => e.left < slide.left || e.right > slide.right || e.bottom > slide.bottom - 20).length;
      return { n: ems.length, choques, fuera };
    });
    assert.equal(r.n, 5);
    assert.equal(r.choques, 0, 'ninguna etiqueta se monta sobre otra');
    assert.equal(r.fuera, 0, 'todas dentro de la lámina');
  } finally {
    await browser.close();
    fs.rmSync(dir, { recursive: true, force: true });
  }
});
```

(Si `planLargo`, `renderPlanHtml`, `test` o `assert` ya están importados arriba en ese archivo, no repetir; agregar solo lo que falte.)

Run: `cd renderer && node --test test/template-plan-layout.test.js 2>&1 | tail -15`
Expected: PASS, también las mediciones que ya existían (`gantt.fin <= 1050`, `gantt.letra >= 16`).

- [ ] **Step 6: Suite completa del renderer**

Run: `cd renderer && npm test 2>&1 | tail -8`
Expected: todas pasan, incluida `template-propuesta-igual.test.js` sin tocar sus hashes.

- [ ] **Step 7: Commit**

```bash
git add renderer/src/template-plan.js renderer/test/template-plan.test.js renderer/test/template-plan-layout.test.js
git commit -m "fix(renderer): el cronograma abre un tercer carril y las etiquetas de los hitos ya no se pisan"
```

---

### Task 2: Botón «Ver detalle del avance» en la lámina del Método AT

**Files:**
- Modify: `renderer/src/template-plan.js` (`renderMetodoSlide` 411-446; CSS `.metodo-aqui` en `ESTILO_FASES`, cerca de la línea 631)
- Test: `renderer/test/template-plan.test.js`

**Interfaces:**
- Consumes: `urlSegura(url)` (ya existe en el archivo, línea 85), `escapeHtml` (de `./escape`).
- Produces: el cuerpo de render del plan acepta `estado_url` (string). Con una URL `http(s)` válida, la lámina dibuja `<a class="metodo-avance" href="…" target="_top" rel="noopener">Ver detalle del avance</a>` junto a «Estás aquí»; sin ella, nada cambia.

- [ ] **Step 1: Escribir las pruebas que fallan**

Al final de `renderer/test/template-plan.test.js`:

```js
const { renderMetodoSlide } = require('../src/template-plan');

test('Método AT: con estado_url aparece «Ver detalle del avance» junto a «Estás aquí» y abre la página completa', () => {
  const html = renderMetodoSlide({ estado_url: 'https://automatizatech.cl/ver-estado.php?id=AbCdEf123456' }, 1, '');
  const i = html.indexOf('metodo-aqui');
  const j = html.indexOf('class="metodo-avance"');
  assert.ok(i > -1 && j > i, 'el botón va después de la etiqueta');
  assert.ok(html.includes('href="https://automatizatech.cl/ver-estado.php?id=AbCdEf123456"'));
  assert.ok(html.includes('target="_top"'));
  assert.ok(html.includes('Ver detalle del avance'));
});

test('Método AT: sin estado_url, o con uno que no es http(s), no hay botón', () => {
  for (const estado_url of [undefined, '', 'javascript:alert(1)', 'ftp://x', '"><script>']) {
    const html = renderMetodoSlide({ estado_url }, 1, '');
    assert.ok(!html.includes('metodo-avance'), `sin botón con ${String(estado_url)}`);
  }
});
```

- [ ] **Step 2: Correr y ver que fallan**

Run: `cd renderer && node --test test/template-plan.test.js 2>&1 | tail -10`
Expected: FAIL en la primera (no hay `metodo-avance`).

- [ ] **Step 3: Implementar**

En `renderMetodoSlide`, antes del `map` de los pasos, calcular el botón; y en la rama `estado === 'actual'`, insertarlo después del `<span class="metodo-aqui">`:

```js
  const avance = urlSegura(data && data.estado_url);
  const boton = avance
    ? `<a class="metodo-avance" href="${escapeHtml(avance)}" target="_top" rel="noopener">Ver detalle del avance</a>`
    : '';
```

```js
      ? `<span class="metodo-aqui">Estás aquí</span>${boton}<p class="metodo-firma">${
```

Antes de escribir, leer `urlSegura` (línea 85) y confirmar que devuelve `''` para todo lo que no sea `http:`/`https:`; si acepta otra cosa, usar en su lugar: `const avance = /^https?:\/\/[^\s"'<>]+$/i.test(String((data && data.estado_url) || '')) ? String(data.estado_url) : '';`.

En `ESTILO_FASES`, después de la regla `.metodo-aqui`, agregar:

```css
  .metodo-avance { display: inline-block; margin: 8px 0 0 10px; padding: 6px 14px; border-radius: 999px; background: #00d9c0; color: #0a1420; font-weight: 700; font-size: 15px; text-decoration: none; }
  .metodo-avance:hover { filter: brightness(1.08); }
```

- [ ] **Step 4: Correr pruebas**

Run: `cd renderer && npm test 2>&1 | tail -8`
Expected: todo pasa; el layout test de la lámina del Método sigue sin elementos fuera de la lámina (si `template-plan-layout.test.js` marca la lámina del Método por el botón nuevo, ajustar el `margin`, nunca la prueba).

- [ ] **Step 5: Mirarlo**

Generar el HTML con el fixture largo más `estado_url` y abrirlo en el navegador del panel (Claude_Browser) o con Playwright; sacar una captura de la lámina 02 para el informe. Comando de ayuda:

```bash
cd renderer && node -e "const {planLargo}=require('./test/fixtures/plan-ejemplo');const {renderPlanHtml}=require('./src/template-plan');const p=planLargo();p.estado_url='https://automatizatech.cl/ver-estado.php?id=Ab3dE5fG7hJ9';require('fs').writeFileSync(require('os').tmpdir()+'/plan-boton.html',renderPlanHtml(p,{}))"
```

- [ ] **Step 6: Commit**

```bash
git add renderer/src/template-plan.js renderer/test/template-plan.test.js
git commit -m "feat(renderer): botón «Ver detalle del avance» junto a «Estás aquí» cuando el plan trae estado_url"
```

---

### Task 3: Flujo «3 Render» con `mantener`

**Files:**
- Modify: `N8N/plan-trabajo/plan_js.py` (constante `JS_RENDER`: función nueva `fotosPropiasDelPlan`)
- Modify: `N8N/plan-trabajo/build_plan_3_render.py` (`JS_AVISO`, `CODE_PREPARAR`, `CODE_REINTENTAR`, `CODE_RESULTADO`, nodo `Guardar vista`)
- Modify: `N8N/plan-trabajo/correos_plan.py` (`correo_render`)
- Regenerate: `N8N/plan-trabajo/plan-3-render.json` (se reescribe al importar el build)
- Test: `N8N/plan-trabajo/probar_plan.py` (secciones `render_puras`, `render`, `correos_render`)

**Interfaces:**
- Consumes: webhook `{id, codigo, modo: 'final', aviso: false, mantener: true}` (lo manda WordPress en Task 4).
- Produces: `POST /plan/{id}/vista` con cuerpo `{modo:'final', mantener:true, ok, view_url, pdf_url, faltan:[], nota}`; JS `fotosPropiasDelPlan(manifest, base, uid) -> {images: {slide: url}, slides: string[]}`.

- [ ] **Step 1: Prueba pura que falla (helper)**

En `probar_plan.py`, dentro de `prueba_render_puras()` (línea ~998), agregar al final de la función, con el mismo `node_js(programa, datos)` que ya usa ese bloque:

```python
    r = node_js(JS + "\nconsole.log(JSON.stringify(fotosPropiasDelPlan(d.m, d.base, d.uid)));", {
        'm': {'metodo': {'file': 'metodo.jpg', 'hash': 'x'}, 'gantt': {'file': 'gantt.png', 'hash': 'y'},
              'malo': {'file': '../x.jpg'}, 'sin': {}},
        'base': 'https://render.example', 'uid': 'Ab3dE5fG7hJ9'})
    check('mantener: las fotos del propio plan pasan como URL absolutas', r['images'] == {
        'metodo': 'https://render.example/p/Ab3dE5fG7hJ9/img/metodo.jpg',
        'gantt': 'https://render.example/p/Ab3dE5fG7hJ9/img/gantt.png'} and sorted(r['slides']) == ['gantt', 'metodo'])
    r = node_js(JS + "\nconsole.log(JSON.stringify(fotosPropiasDelPlan(d.m, d.base, d.uid)));", {'m': 'no-json', 'base': 'https://r.example', 'uid': 'Ab3dE5fG7hJ9'})
    check('mantener: manifest ilegible = sin fotos, sin lanzar', r == {'images': {}, 'slides': []})
```

(Usar los nombres reales de ese archivo: si la variable con la librería JS se llama distinto de `JS` o el comprobador distinto de `check`, usar los existentes en `prueba_render_puras`.)

Run: `python N8N/plan-trabajo/probar_plan.py render_puras 2>&1 | tail -5`
Expected: FAIL (`fotosPropiasDelPlan is not defined`).

- [ ] **Step 2: Implementar el helper**

En `plan_js.py`, dentro de la cadena `JS_RENDER`, después de `reutilizarFotosPlan`:

```js
// «Actualizar la vista del plan» (mantener): las fotos que el plan ya tiene guardadas en el renderer
// (img/manifest.json del propio plan) pasan como URL absolutas, para que el render no pida ni cobre ninguna.
function fotosPropiasDelPlan(manifest, base, uid) {
  let m = manifest;
  if (typeof m === 'string') { try { m = JSON.parse(m); } catch (e) { m = null; } }
  const images = {};
  const slides = [];
  if (!esObjetoPlano(m) || !/^[A-Za-z0-9_-]{6,64}$/.test(String(uid || '')) || !/^https?:\/\/[A-Za-z0-9.-]+(:\d+)?$/.test(String(base || ''))) {
    return { images, slides };
  }
  for (const [slide, e] of Object.entries(m)) {
    const file = esObjetoPlano(e) && typeof e.file === 'string' && /^[A-Za-z0-9_-]+\.[A-Za-z0-9]{2,5}$/.test(e.file) ? e.file : '';
    if (!file || !/^[a-z0-9_]{2,40}$/.test(slide)) continue;
    images[slide] = base + '/p/' + uid + '/img/' + file;
    slides.push(slide);
  }
  return { images, slides };
}
```

Run: `python N8N/plan-trabajo/probar_plan.py render_puras 2>&1 | tail -5` → Expected: PASS.

- [ ] **Step 3: Prueba de flujo que falla (simulador)**

En `prueba_render()` de `probar_plan.py` (línea ~1145), agregar un escenario con el simulador existente (`simular(...)` y los fabricantes `leido(...)`, `renderer_ok`), webhook `{'id': 7, 'codigo': 'Ab3dE5fG7hJ9', 'modo': 'final', 'aviso': False, 'mantener': True}`, `leer_render=leido(render, '', estado='enviado')`, manifest del plan con `metodo` y `gantt`, y comprobar:

```python
    check('mantener: el render no pide fotos nuevas', cuerpo_render['image_briefs'] == [])
    check('mantener: usa las fotos del propio plan', cuerpo_render['images'].get('metodo', '').endswith('/img/metodo.jpg'))
    check('mantener: no consulta a OpenAI', 'Buscar texto en fotos' not in nodos_ejecutados)
    check('mantener: /vista lleva mantener=true y modo final', vista['mantener'] is True and vista['modo'] == 'final')
    check('mantener: sin correo a Luis si salió bien', 'Correo a Luis' not in nodos_ejecutados)
```

Usar los nombres reales que devuelve `simular` (cuerpo enviado a `Render`, cuerpo enviado a `Guardar vista`, lista de nodos ejecutados): leerlos en la función `simular`/`SIM` del mismo archivo (líneas 190-277) antes de escribir. Agregar también el caso con fallo del renderer (status 500 tres veces): `vista['ok'] is False` y **sí** hay correo a Luis.

Run: `python N8N/plan-trabajo/probar_plan.py render 2>&1 | tail -8` → Expected: FAIL.

- [ ] **Step 4: Implementar en el build**

En `build_plan_3_render.py`:

1. `JS_AVISO`: agregar la lectura de la bandera.

```python
JS_AVISO = r"""const avisoPedido = (v) => v === true || v === 1 || v === '1' || v === 'true';
const mantenerPedido = (v) => v === true || v === 1 || v === '1' || v === 'true';
"""
```

2. El nodo `Preparar render` debe incluir `JS_AVISO` (hoy solo `Resultado del render` lo incluye). En la lista `nodes`, cambiar su `jsCode` a `LIB + JS_AVISO + CODE_PREPARAR` (y quitar de `CODE_PREPARAR` cualquier definición duplicada de `avisoPedido` si la hubiera).

3. `CODE_PREPARAR`: después de `const modo = ...`, agregar `const mantener = modo === 'final' && mantenerPedido(hook.mantener);`. Dentro de la rama `if (modo === 'final') {`, al principio, la rama de mantener que **no** genera nada:

```js
if (modo === 'final' && mantener) {
  // «Actualizar la vista del plan» (D10): mismo contenido, mismas fotos, ninguna nueva.
  let previo = null;
  if ($('Fotos previas del plan').isExecuted) {
    const pp = $('Fotos previas del plan').first().json || {};
    if (pp.statusCode === 200) previo = pp.body;
  }
  const propias = fotosPropiasDelPlan(previo, RENDERER_BASE, String(render.unique_id || ''));
  render = Object.assign({}, render, { draft: false, image_briefs: [],
    images: Object.assign({}, esObjetoPlano(render.images) ? render.images : {}, propias.images) });
  reutilizadas = propias.slides;
} else if (modo === 'final') {
```

(convertir el `if (modo === 'final') {` original en ese `else if`). En el `return` final agregar `mantener` al objeto: `return [{ json: { id, modo, mantener, aviso: ..., ...`.

4. `CODE_REINTENTAR`: `revisar_texto: final && base.mantener !== true && lr.status === 200 && !!lr.view_url && lr.missing.length === 0`.

5. `CODE_RESULTADO`: leer `const mantener = modo === 'final' && mantenerPedido(hook.mantener);`, agregarlo a `vacio` (`mantener,`), y en el `resumen`/`nota`, cuando `mantener` y `ok`: `resumen = ['Vista del plan actualizada con el mismo contenido y las mismas fotos']` y nota `'Vista del plan actualizada: ' + resumen.join(' · ') + extra`. Cuando `mantener`, no agregar los textos de fotos nuevas ni de revisión.

6. Nodo `Guardar vista`: agregar `mantener` al cuerpo:

```python
         "={{ JSON.stringify({ modo: $json.modo, mantener: $json.mantener === true, ok: $json.ok, view_url: $json.view_url, pdf_url: $json.pdf_url,"
         " faltan: $json.faltan, nota: $json.nota }) }}"),
```

En `correos_plan.py`, `correo_render()`: después de `const final = v.modo === 'final';` agregar `const mantener = v.mantener === true;` y cambiar

```js
const quedoListo = !final || mantener || estadoWp === '' || estadoWp === 'listo';
const enviar = mantener ? (!ok || !guardado) : (v.aviso === true || final || !ok || !guardado);
```

y en `que`, si `mantener`: `'la vista del plan no se pudo actualizar'` cuando falla.

- [ ] **Step 5: Correr todas las pruebas del flujo**

Run:
```bash
python N8N/plan-trabajo/probar_plan.py 2>&1 | tail -5
python N8N/plan-trabajo/probar_revision_plan.py 2>&1 | tail -3
```
Expected: todo OK (el import reescribe `plan-3-render.json`). Revisar con `git diff --stat` que el JSON cambió.

- [ ] **Step 6: Commit**

```bash
git add N8N/plan-trabajo/plan_js.py N8N/plan-trabajo/build_plan_3_render.py N8N/plan-trabajo/correos_plan.py N8N/plan-trabajo/plan-3-render.json N8N/plan-trabajo/probar_plan.py
git commit -m "feat(n8n): «3 Render» con mantener: rehace la vista de un plan enviado con sus mismas fotos, sin revisar texto ni correo"
```

---

### Task 4: WordPress — `estado_url`, `/vista` con `mantener` y `at_pt_pedir_render`

**Files:**
- Modify: `wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php` (función nueva `at_pt_url_ver_estado`; `at_pt_armar_render` línea ~1104)
- Modify: `wp-content/themes/automatiza-tech/inc/plan-trabajo/rest.php` (`at_pt_rest_vista` línea ~218)
- Modify: `wp-content/themes/automatiza-tech/inc/plan-trabajo/disparador.php` (`at_pt_pedir_render` línea ~75)
- Test: `tests/plan/render-test.php`, `tests/plan/rest-render-wp-test.php`, `tests/plan/disparador-wp-test.php`

**Interfaces:**
- Produces: `at_pt_url_ver_estado(string $base, string $codigo): string`; `at_pt_pedir_render(int $plan_id, string $modo, bool $aviso, bool $mantener = false): string` (manda `mantener` a n8n solo si es `true`); cuerpo de render con la clave `estado_url` justo después de `agenda`.

- [ ] **Step 1: Pruebas que fallan**

`tests/plan/render-test.php`: en la línea 64 (la que compara `array_keys($r)`), insertar `'estado_url'` después de `'agenda'`. Y antes de `fin();`:

```php
ok(at_pt_url_ver_estado('https://automatizatech.cl', 'AbCdEf123456') === 'https://automatizatech.cl/ver-estado.php?id=AbCdEf123456', 'url de ver-estado con el código del plan');
ok(at_pt_url_ver_estado('https://automatizatech.cl/', 'abc') === '' && at_pt_url_ver_estado('', 'AbCdEf123456') === '', 'código corto o sin sitio: sin url');
ok(at_pt_armar_render($plan, $datos, true)['estado_url'] === at_pt_url_ver_estado($datos['sitio'], $datos['codigo']), 'el cuerpo del render lleva estado_url');
```

(Si `$datos` en ese archivo no trae `sitio` o `codigo`, usar los valores que sí trae; leer su definición en la línea ~55.)

`tests/plan/rest-render-wp-test.php`, antes de `fin();`:

```php
// Etapa 1 estado: «Actualizar la vista del plan» (mantener) nunca cambia el estado.
ptc_estado($pid, 'enviado');
$nueva = $base . '/index.html?v=2';
$r = pt_pedir('POST', "/plan/{$pid}/vista", ['modo' => 'final', 'mantener' => true, 'ok' => true, 'view_url' => $nueva, 'pdf_url' => $base . '/presentation.pdf', 'faltan' => [], 'nota' => 'Vista del plan actualizada']);
$f = at_pt_plan($pid);
ok($r->get_status() === 200 && $f->estado === 'enviado' && $f->view_url === $nueva, 'mantener: enviado sigue enviado y guarda los enlaces');
pt_pedir('POST', "/plan/{$pid}/vista", ['modo' => 'final', 'mantener' => true, 'ok' => false, 'view_url' => '', 'pdf_url' => '', 'faltan' => [], 'nota' => 'renderer HTTP 502']);
$f = at_pt_plan($pid);
ok($f->estado === 'enviado' && $f->view_url === $nueva && strpos((string) $f->nota, 'No se pudo actualizar la vista del plan') !== false, 'mantener fallido: el estado y los enlaces no cambian, la nota lo dice');
ptc_estado($pid, 'listo');
pt_pedir('POST', "/plan/{$pid}/vista", ['modo' => 'final', 'mantener' => true, 'ok' => true, 'view_url' => $base . '/index.html', 'pdf_url' => $base . '/presentation.pdf', 'faltan' => [], 'nota' => '']);
ok(at_pt_plan($pid)->estado === 'listo', 'Review Focus 5: mantener sobre un plan «listo» tampoco cambia el estado');
```

(Si en ese archivo la variable del plan o la base de URL tienen otro nombre que `$pid`/`$base`, usar los existentes, definidos al principio del archivo.)

`tests/plan/disparador-wp-test.php`, antes de `fin();` (usar su manera existente de capturar el cuerpo mandado a n8n; leerla antes):

```php
// mantener solo viaja cuando es true.
```

con dos comprobaciones: `at_pt_pedir_render($id, 'final', false, true)` manda `mantener => true`; `at_pt_pedir_render($id, 'final', false)` no manda la clave `mantener`.

Run:
```bash
/c/wamp64/bin/php/php8.4.15/php.exe tests/plan/render-test.php | tail -3
AT_WP_LOAD=$SCR/wp-local-plan/wp-load.php /c/wamp64/bin/php/php8.4.15/php.exe tests/plan/rest-render-wp-test.php | tail -3
AT_WP_LOAD=$SCR/wp-local-plan/wp-load.php /c/wamp64/bin/php/php8.4.15/php.exe tests/plan/disparador-wp-test.php | tail -3
```
Expected: FALLAS en lo nuevo.

- [ ] **Step 2: Implementar**

`puras.php`, junto a `at_pt_url_ver_plan` (misma validación del código):

```php
/** Página pública «Estado del proyecto» del plan: {sitio}/ver-estado.php?id=<código>. '' si el código no sirve. */
function at_pt_url_ver_estado(string $base, string $codigo): string {
	$base = rtrim(trim($base), '/');
	if ($base === '' || !preg_match('/^https?:\/\//i', $base) || !preg_match('/^[A-Za-z0-9]{6,32}$/', $codigo)) {
		return '';
	}
	return $base . '/ver-estado.php?id=' . $codigo;
}
```

En `at_pt_armar_render`, en el arreglo que devuelve, justo después de la clave `'agenda' => [...]`:

```php
		'estado_url'        => at_pt_url_ver_estado((string) ($datos['sitio'] ?? ''), (string) ($datos['codigo'] ?? '')),
```

(respetar la alineación de las demás claves).

`rest.php`, en `at_pt_rest_vista`, después de validar `modo` y antes de las guardas de llegada tardía (líneas ~240-247):

```php
	// «Actualizar la vista del plan» (mantener): mismo contenido y mismas fotos; nunca cambia el estado.
	if ($modo === 'final' && !empty($p['mantener']) && $p['mantener'] !== 'false') {
		if ($ok && $view !== '') {
			at_pt_guardar($id, ['view_url' => $view, 'pdf_url' => $pdf, 'nota' => 'Vista del plan actualizada (' . current_time('Y-m-d H:i') . ').']);
		} else {
			at_pt_guardar($id, ['nota' => 'No se pudo actualizar la vista del plan: ' . ($nota !== '' ? $nota : 'el renderer no respondió') . '.']);
		}
		return new WP_REST_Response(['ok' => true, 'estado' => (string) at_pt_plan($id)->estado], 200);
	}
```

(Usar los nombres de variables que ya tiene la función para `$ok`, `$view`, `$pdf`, `$nota`, `$id`, `$modo`; leerlos antes. `at_pt_guardar` limita la nota a 1000 caracteres.)

`disparador.php`, `at_pt_pedir_render`: cambiar la firma a `function at_pt_pedir_render(int $plan_id, string $modo, bool $aviso, bool $mantener = false): string` y armar el cuerpo así:

```php
	$cuerpo = ['id' => (int) $f->id, 'codigo' => (string) $f->codigo, 'modo' => $modo, 'aviso' => $aviso];
	if ($mantener) {
		$cuerpo['mantener'] = true;
	}
	$motivo = at_pt_llamar_n8n(AT_N8N_PLAN_RENDER, $cuerpo);
```

y, si falla con `$mantener`, guardar la nota `'No se pudo pedir la actualización de la vista: ' . $motivo` sin tocar el estado (la rama que pasa a `error` es solo para `final` sin `mantener` y con el plan `aprobando`).

- [ ] **Step 3: Correr las pruebas**

Las tres del Step 1 más `ver-plan-wp-test.php`, `panel-acciones-wp-test.php` y `envio-wp-test.php`. Expected: TODO OK.

- [ ] **Step 4: Commit**

```bash
git add wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php wp-content/themes/automatiza-tech/inc/plan-trabajo/rest.php wp-content/themes/automatiza-tech/inc/plan-trabajo/disparador.php tests/plan/render-test.php tests/plan/rest-render-wp-test.php tests/plan/disparador-wp-test.php
git commit -m "feat(plan): estado_url en el cuerpo del render y /vista con mantener sin cambiar el estado"
```

---

### Task 5: Botón de panel «Actualizar la vista del plan» y `ver-estado.php` mínimo

**Files:**
- Modify: `wp-content/themes/automatiza-tech/inc/plan-trabajo/panel.php` (`at_pt_mensajes_panel` línea ~49; bloque de botones de envío líneas ~364-390; registro de acciones líneas ~469-476)
- Modify: `wp-content/themes/automatiza-tech/inc/plan-trabajo/vista.php` (funciones nuevas)
- Create: `ver-estado.php` (raíz del repo)
- Test: `tests/plan/panel-acciones-wp-test.php`, `tests/plan/ver-estado-wp-test.php` (nuevo)

**Interfaces:**
- Consumes: `at_pt_pedir_render(int, string, bool, bool)` de Task 4; `at_pt_accion_plan()`, `at_pt_volver()`, `$ocultos($accion)` de panel.php; `at_pt_fila_ver_plan()` y `at_pt_url_ver_plan()` existentes.
- Produces: acción `admin_post_at_pt_actualizar_vista`; claves de mensaje `vista_pedida`, `vista_fallo`, `vista_no_enviado`; `at_pt_fila_ver_estado(string $codigo): ?object`; `at_pt_html_ver_estado(?object $fila): string`.

- [ ] **Step 1: Pruebas que fallan**

Crear `tests/plan/ver-estado-wp-test.php`:

```php
<?php
// Correr: AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php php tests/plan/ver-estado-wp-test.php
// Estado del proyecto, Etapa 1: ver-estado.php mínimo («Pronto verás aquí el avance») con el enlace al plan.
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/fixtures-panel.php';
exigir('at_pt_fila_ver_estado', 'at_pt_html_ver_estado');
$m = ptc_marca();
$c = ptc_cliente($m, 'prueba-ver-estado@example.com');
$plan = ptc_plan(ptc_contrato($c['tech'], $m, null, 'servicios', 'signed', 'prueba-ver-estado@example.com'));
ptc_sembrar((int) $plan->id);
$vista = 'https://' . at_pt_host_renderer() . '/p/' . $plan->codigo . '/index.html';
at_pt_guardar((int) $plan->id, ['estado' => 'enviado', 'view_url' => $vista]);
$codigo = (string) $plan->codigo;

$fila = at_pt_fila_ver_estado($codigo);
$h = at_pt_html_ver_estado($fila);
ok($fila !== null && strpos($h, 'Pronto verás aquí el avance de tu proyecto') !== false, 'plan enviado: avisa que el avance viene pronto');
ok(strpos($h, 'href="' . esc_url(at_pt_url_ver_plan(home_url(), $codigo)) . '"') !== false && strpos($h, 'Ver el plan de actividades inicial') !== false, 'enlace al plan inicial por automatizatech');
ok(stripos($h, 'easypanel') === false, 'ningún enlace al renderer');
ok(stripos($h, 'noindex') !== false, 'no se indexa');
at_pt_guardar((int) $plan->id, ['estado' => 'listo']);
ok(at_pt_fila_ver_estado($codigo) === null, 'un plan que no está enviado no tiene página de estado');
ok(at_pt_fila_ver_estado('NoExiste1234') === null && at_pt_fila_ver_estado('') === null && at_pt_fila_ver_estado('ab<c') === null, 'código inventado, vacío o con caracteres raros: sin página');
$hn = at_pt_html_ver_estado(null);
ok(strpos($hn, 'no está disponible') !== false && stripos($hn, $codigo) === false, 'sin fila: mensaje de no disponible sin datos del plan');
fin();
```

En `tests/plan/panel-acciones-wp-test.php`, antes de `fin();`, usando su helper de acción (línea ~65; leer su firma: envuelve `pt_correr_accion` con el nonce `'at_pt_plan_' . $fila->id`):

```php
// Estado del proyecto, Etapa 1: «Actualizar la vista del plan».
```

Comprobar tres casos: (1) plan `enviado` → redirige con `pt_msg=vista_pedida` y n8n recibe `{modo:'final', aviso:false, mantener:true}`, y el plan sigue `enviado`; (2) plan `listo` → `pt_msg=vista_no_enviado` y n8n no recibe nada; (3) n8n caído (`$n8n = 'falla'` o el valor que use el helper) → `pt_msg=vista_fallo`, plan `enviado`.

Run:
```bash
AT_WP_LOAD=$SCR/wp-local-plan/wp-load.php /c/wamp64/bin/php/php8.4.15/php.exe tests/plan/ver-estado-wp-test.php | tail -3
AT_WP_LOAD=$SCR/wp-local-plan/wp-load.php /c/wamp64/bin/php/php8.4.15/php.exe tests/plan/panel-acciones-wp-test.php | tail -3
```
Expected: exit 2 / FALLAS.

- [ ] **Step 2: Implementar `vista.php`**

Agregar al final:

```php
/** El plan de ese código si tiene página de «Estado del proyecto» (Etapa 1: solo planes enviados). null si no. */
function at_pt_fila_ver_estado(string $codigo): ?object {
	if (!preg_match('/^[A-Za-z0-9]{6,32}$/', $codigo)) {
		return null;
	}
	$f = at_pt_plan_por_codigo($codigo);
	return ($f && (string) $f->estado === 'enviado') ? $f : null;
}

/** Documento HTML de ver-estado.php. Etapa 1: todavía sin presentación de estado, solo el aviso y el enlace al plan. */
function at_pt_html_ver_estado(?object $fila): string {
	$tema = get_template_directory_uri();
	$css = get_template_directory() . '/assets/css/plan-ver.css';
	$cabeza = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">'
		. '<meta name="robots" content="noindex, nofollow"><title>Estado del proyecto — AutomatizaTech</title>'
		. '<link rel="stylesheet" href="' . esc_url($tema . '/assets/css/plan-ver.css?v=' . (file_exists($css) ? (string) filemtime($css) : '1')) . '"></head>';
	if (!$fila) {
		return $cabeza . '<body class="at-pt-ver at-pt-ver--vacio"><main class="at-pt-vacio"><h1>Esta página no está disponible</h1>'
			. '<p>Revisa el enlace que te enviamos o escríbenos a contacto@automatizatech.cl.</p></main></body></html>';
	}
	$plan = at_pt_url_ver_plan(home_url(), (string) $fila->codigo);
	return $cabeza . '<body class="at-pt-ver at-pt-ver--vacio"><main class="at-pt-vacio"><h1>Pronto verás aquí el avance de tu proyecto</h1>'
		. '<p>Cada vez que terminemos una parte del plan, te mostraremos aquí qué hicimos y cómo quedó.</p>'
		. '<p><a class="at-pt-btn at-pt-btn--si" href="' . esc_url($plan) . '">Ver el plan de actividades inicial</a></p>'
		. '</main></body></html>';
}
```

- [ ] **Step 3: Crear `ver-estado.php` en la raíz**

```php
<?php
// ver-estado.php?id=<código del plan> — «Estado del proyecto» del cliente. La lógica vive en
// wp-content/themes/automatiza-tech/inc/plan-trabajo/vista.php.
require_once __DIR__ . '/wp-load.php';

$codigo = isset($_GET['id']) && is_string($_GET['id']) ? preg_replace('/[^A-Za-z0-9]/', '', (string) wp_unslash($_GET['id'])) : '';
if (!defined('DONOTCACHEPAGE')) {
	define('DONOTCACHEPAGE', true);
}
nocache_headers();
header('X-Robots-Tag: noindex, nofollow');
if (!function_exists('at_pt_html_ver_estado')) {
	status_header(503);
	exit('Estado del proyecto no disponible por ahora.');
}
$fila = at_pt_fila_ver_estado((string) $codigo);
if (!$fila) {
	status_header(404);
}
echo at_pt_html_ver_estado($fila);
```

En el sitio local, enlazar el archivo como se hizo con `ver-entregable.php` (enlace duro):

```powershell
cmd /c mklink /H "$S\ver-estado.php" "$W\ver-estado.php"
```

- [ ] **Step 4: Implementar el botón y la acción en `panel.php`**

En `at_pt_mensajes_panel()`, agregar:

```php
		'vista_pedida'     => ['ok', 'Pedimos la vista actualizada del plan. Queda lista en alrededor de un minuto; recarga la página para ver la nota.'],
		'vista_fallo'      => ['error', 'No se pudo pedir la vista actualizada del plan. Revisa la nota del plan y reintenta.'],
		'vista_no_enviado' => ['aviso', 'Solo se actualiza la vista de un plan ya enviado al cliente.'],
```

En el bloque de botones (`in_array($estado, ['listo', 'enviado'])`, después de «💬 Enviar por mi WhatsApp»), solo con el plan `enviado`:

```php
			if ($estado === 'enviado') {
				echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block" onsubmit="return confirm(\'Se rehace la presentación con el mismo contenido y las mismas fotos, sin costo. ¿Seguimos?\');">'
					. $ocultos('at_pt_actualizar_vista')
					. '<button class="button">🔄 Actualizar la vista del plan</button></form> ';
			}
```

(usar la misma variable de estado y el mismo estilo que los botones vecinos; leerlos antes.)

Registrar la acción junto a las demás (`add_action('admin_post_at_pt_actualizar_vista', 'at_pt_accion_actualizar_vista');`) y el handler:

```php
/** «🔄 Actualizar la vista del plan»: rehace la presentación de un plan enviado con el mismo contenido y las mismas fotos. */
function at_pt_accion_actualizar_vista(): void {
	[$fila, $crm] = at_pt_accion_plan();
	$id = (int) $fila->id;
	if ((string) $fila->estado !== 'enviado') {
		at_pt_volver($crm, $id, 'vista_no_enviado');
	}
	$motivo = at_pt_pedir_render($id, 'final', false, true);
	at_pt_volver($crm, $id, $motivo === '' ? 'vista_pedida' : 'vista_fallo');
}
```

- [ ] **Step 5: Correr las pruebas**

`ver-estado-wp-test.php`, `panel-acciones-wp-test.php`, `ver-plan-wp-test.php`, `panel-render-wp-test.php`, `panel-envio-wp-test.php`. Expected: TODO OK.

- [ ] **Step 6: Verificar la página en el navegador local**

Con el servidor `wp-local-plan` (configuración en `.claude/launch.json` del worktree; si falta, agregarla sin commitearla: `php -S localhost:8093 -t $SCR/wp-local-plan $SCR/wp-local-plan/router.php`), abrir `http://localhost:8093/ver-estado.php?id=<código de un plan enviado de prueba>` y `?id=NoExiste1234`: captura del aviso y comprobación de 404. Revisar la consola sin errores.

- [ ] **Step 7: Commit**

```bash
git add ver-estado.php wp-content/themes/automatiza-tech/inc/plan-trabajo/vista.php wp-content/themes/automatiza-tech/inc/plan-trabajo/panel.php tests/plan/ver-estado-wp-test.php tests/plan/panel-acciones-wp-test.php
git commit -m "feat(plan): «Actualizar la vista del plan» en el panel y ver-estado.php mínimo"
```

---

### Task 6: Documentación, grafo y lista de despliegue

**Files:**
- Modify: `Docs/METODO_AT/PLAN-DE-TRABAJO.md` (sección nueva «Estado del proyecto, Etapa 1»)
- Modify: `CLAUDE.md` y `AGENTS.md` (puntero del plan de trabajo: una línea)

- [ ] **Step 1: Guía**

Agregar a `PLAN-DE-TRABAJO.md` una sección «Estado del proyecto — Etapa 1» con: botón «Ver detalle del avance» (sale solo si el render trae `estado_url`, que WordPress manda desde ahora a todo plan), cronograma con tercer carril, «🔄 Actualizar la vista del plan» (solo `enviado`; flujo «3 Render» con `mantener`: fotos del manifest propio, sin fotos nuevas, sin GPT-4o, sin correo, sin cambiar el estado), `ver-estado.php` mínimo, y el orden de despliegue del Step 2.

- [ ] **Step 2: Lista de despliegue (para Luis, en el informe y en la guía)**

| Orden | Local | Destino | Clase |
|---|---|---|---|
| 1 | zip de `renderer/` de esta rama | Easypanel, servicio del renderer (lo sube Luis) | OBLIGATORIO |
| 2 | `N8N/plan-trabajo/plan-3-render.json` | n8n, `python N8N/propuestas-v3/deploy.py <ruta absoluta>` | OBLIGATORIO |
| 3 | `wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php`, `rest.php`, `disparador.php`, `vista.php`, `panel.php` | igual | OBLIGATORIO (respaldar y cotejar md5 contra `main`) |
| 4 | `ver-estado.php` | raíz | OBLIGATORIO (nuevo) |
| — | `tests/`, `Docs/` | — | NO subir |

Después: con el OK aparte de Luis, «🔄 Actualizar la vista del plan» de Orly; verificar desde afuera `ver-plan.php?id=AbCdEf123456` (botón en la lámina 02, cronograma sin etiquetas pisadas) y `ver-estado.php?id=AbCdEf123456` (aviso «Pronto…»).

- [ ] **Step 3: Grafo y commit**

```bash
graphify update .
git add Docs/METODO_AT/PLAN-DE-TRABAJO.md CLAUDE.md AGENTS.md graphify-out
git commit -m "docs(plan): Estado del proyecto, Etapa 1 — botón, cronograma, actualizar vista y ver-estado mínimo"
```
