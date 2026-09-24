# Flujo de propuestas v3 (borrador → cambios → final verificado → envío) — plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Que una transcripción de reunión se convierta sola en una propuesta **borrador sin gasto**, que Luis la ajuste desde el panel (comentarios y precios) las veces que quiera, que al aprobar se genere la versión final con fotos **alojadas y verificada**, y que el envío al cliente sea siempre el último paso y con su clic.

**Architecture:** Tres flujos nuevos de n8n (Borrador, Cambios, Final) con rutas de webhook nuevas; el flujo de Meet solo cambia de destino en la última tarea. WordPress guarda el estado de cada propuesta (`flujo = 'v3'`, `status`, `status_note`, `feedback_log`) y hace cumplir las reglas que no deben depender de n8n: transiciones válidas, precios que solo cambia Luis y envío solo desde `lista`. El renderer guarda las fotos junto a la presentación (no enlaza CDN) y marca las vistas previas como borrador.

**Tech Stack:** Node 20 + Express + Playwright (renderer, `node --test`), PHP 8.3 / WordPress (tema `automatiza-tech`, pruebas con scripts `php` simples), n8n (vía el conector `n8n-mcp`), OpenAI GPT-4o (credencial existente `OpenAi account`), Soul 2 por la API de Higgsfield (ya integrado en el renderer).

## Global Constraints

- Una sola plantilla: toda propuesta usa el `propuesta-renderer` (regla de Luis, `Docs/METODO_AT/PROPUESTAS-PLANTILLA-UNICA.md`). Nada de Gamma.
- **Los precios los decide Luis.** El modelo deja `price_label: "Por confirmar"`; ningún paso automático puede cambiar `pricing_rows` ni `pricing_note` después (lo hace cumplir WordPress, no n8n).
- **Cero gasto antes de aprobar:** el borrador y los cambios se renderizan **sin** `image_briefs`. Las fotos (Soul 2, US$0,0032 por imagen de lista) solo se piden en el flujo Final, que se dispara con el botón "Aprobar" del panel; ese botón muestra el número de fotos y el costo antes del clic.
- **El envío al cliente es manual y último:** una propuesta `flujo = 'v3'` solo puede enviarse desde `status = 'lista'`. Ningún flujo de n8n v3 escribe al cliente.
- Textos visibles en español de Chile. Fotos sin texto, sin logos, sin pantallas y sin rostros reconocibles.
- Secretos: ningún agente escribe valores. Se reutiliza `AT_REST_SECRET` (ya existe en `wp-config`) en ambos sentidos con el header `X-AT-Secret`; Luis crea la credencial de n8n y pega el valor.
- El flujo actual `APuTGmusbjLAJ74w` no se toca: queda como respaldo. El de Meet `FrWZcgbizlipK5pb` solo cambia su URL de destino en la Tarea 12.
- Las propuestas viejas (`flujo` vacío, estados `draft/pending/sent`) se comportan exactamente como hoy.
- PROD: los archivos de WordPress se suben por SSH solo con autorización explícita de Luis para ese deploy (reconocer, respaldar, subir, verificar desde afuera). El renderer lo despliega Luis con un zip.
- Los archivos del repo están en CRLF: cada edición conserva los fines de línea del archivo.
- Regla de fundamentos: cada verificación de este plan se hace midiendo (HTTP, consulta, prueba), no suponiendo.
- **Respaldar antes de cada cambio en PROD** (regla de Luis, 2026-09-23): tabla, archivos, flujos de n8n y renderer tienen un respaldo nombrado y verificado antes de tocarse. Ya hechos al empezar la ejecución (2026-09-24 01:33): `~/respaldos/wp_automatiza_propuestas-antes-v3-20260924-013320.sql` (11 filas, md5 `e580d02e5aa8e75e7a4e324aca98b72c`) y `~/respaldos/tema-propuestas-antes-v3-20260924-013331.tar.gz` (functions.php, admin-proposals.php, rest-proposals.php, api-save-proposal.php, api-get-prompt.php, api-save-presentation.php, ver-demo.php, ver-presentacion.php). Los flujos de n8n se exportan a `C:/Users/luis_/respaldos/n8n/2026-09-23/` antes de editarlos.
- 🔴 **`functions.php` NO se toca ni se sube**: el de PROD va por delante de `main` (carga `inc/admin-at-finanzas.php`, que no está en el repo; cotejado el 2026-09-24). Subir el de la rama borraría Finanzas AT en PROD.

## Estados (`status`) y transiciones del flujo v3

```
(alta) ─► borrador ─► ajustando ─► borrador            (ciclo de cambios)
             │  ▲         └──────► error
             │  └──────── error ◄─┐
             └──► generando ─► lista ─► sent           (final y envío)
                      └──────► error      lista ─► ajustando
```

| desde \ hacia | borrador | ajustando | generando | lista | error | sent |
|---|---|---|---|---|---|---|
| borrador  |   | sí | sí |   |   |   |
| ajustando | sí |   |   |   | sí |   |
| generando |   |   |   | sí | sí |   |
| lista     |   | sí |   |   |   | sí |
| error     | sí | sí | sí |   |   |   |

## Mapa de archivos

| Archivo | Responsabilidad |
|---|---|
| `renderer/src/images-store.js` (nuevo) | Copiar las fotos remotas a `/p/<id>/img/` y devolver rutas locales |
| `renderer/src/server.js` | Usar `images-store`; responder con el resumen de fotos |
| `renderer/src/template.js` | Sello "Borrador" cuando `draft: true` |
| `renderer/test/images-store.test.js` (nuevo), `server-images.test.js`, `template-deck.test.js` | Pruebas |
| `wp-content/themes/automatiza-tech/inc/proposals-flow.php` (nuevo) | Reglas puras v3 + migración de columnas |
| `tests/propuestas/flow-test.php` (nuevo) | Pruebas de las reglas puras |
| `wp-content/themes/automatiza-tech/inc/rest-proposals.php` | Rutas `POST /proposal` y `GET/POST /proposal/{id}/state` |
| `wp-content/themes/automatiza-tech/inc/admin-proposals.php` | Sección de revisión v3, botones, candado de envío, texto del PDF |
| n8n: `Propuestas v3 · 1 Borrador`, `· 2 Cambios`, `· 3 Final` (nuevos) | Orquestación |
| `Docs/METODO_AT/PROPUESTAS-PLANTILLA-UNICA.md` | Documentar el flujo v3 |

---

### Task 0: Preparar la rama

**Files:** ninguno nuevo.

La rama `claude/propuestas-v3` nace de `claude/renderer-soul2` (`324fbf2`, PR #31), que va 300 commits por detrás de `main` pero no toca archivos del tema.

- [ ] **Step 1: Traer `main`**

Si el PR #31 ya está mergeado:
```bash
git fetch origin && git rebase origin/main
```
Si no:
```bash
git fetch origin && git merge --no-ff origin/main -m "merge: main en claude/propuestas-v3"
```
Expected: sin conflictos (la rama solo agrega `renderer/` y `api-save-presentation.php`).

- [ ] **Step 2: Verificar la base**

Run: `cd renderer && npm ci && npm test 2>&1 | grep -E "^ℹ (tests|pass|fail)"`
Expected: `tests 68`, `pass 68`, `fail 0`.

Run: `git diff --stat origin/main -- wp-content/themes/automatiza-tech | tail -1`
Expected: vacío (el tema es igual a `main`).

---

### Task 1: El renderer guarda las fotos junto a la presentación

**Files:**
- Create: `renderer/src/images-store.js`
- Test: `renderer/test/images-store.test.js`

**Interfaces:**
- Produces: `persistImages(images: {[slide]: string|null}, outputDir: string, opts?: {fetchImpl?, timeoutMs?}) → Promise<{ images: {[slide]: string}, report: { saved: string[], kept_remote: string[] } }>`. Una foto descargada queda como `img/<slide>.<ext>` (ruta relativa a `index.html`); una que falla conserva su URL remota; un valor vacío se omite.

- [ ] **Step 1: Escribir las pruebas que fallan**

`renderer/test/images-store.test.js`:
```js
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs/promises');
const os = require('node:os');
const path = require('node:path');
const { persistImages } = require('../src/images-store');

function fakeFetch(map) {
  return async (url) => {
    const hit = map[url];
    if (!hit) return { ok: false, status: 404, headers: { get: () => 'text/html' }, arrayBuffer: async () => new ArrayBuffer(0) };
    return { ok: true, status: 200, headers: { get: () => hit.type }, arrayBuffer: async () => Buffer.from(hit.body) };
  };
}

test('downloads remote photos next to index.html and returns relative paths', async () => {
  const dir = await fs.mkdtemp(path.join(os.tmpdir(), 'at-store-'));
  try {
    const fetchImpl = fakeFetch({ 'https://cdn.example.com/a.png': { type: 'image/png', body: 'PNGDATA' } });
    const { images, report } = await persistImages({ cover: 'https://cdn.example.com/a.png' }, dir, { fetchImpl });
    assert.equal(images.cover, 'img/cover.png');
    assert.deepEqual(report.saved, ['cover']);
    assert.equal(await fs.readFile(path.join(dir, 'img', 'cover.png'), 'utf8'), 'PNGDATA');
  } finally {
    await fs.rm(dir, { recursive: true, force: true });
  }
});

test('a photo that cannot be downloaded keeps its remote url', async () => {
  const dir = await fs.mkdtemp(path.join(os.tmpdir(), 'at-store-'));
  try {
    const { images, report } = await persistImages({ challenge: 'https://cdn.example.com/gone.png' }, dir, { fetchImpl: fakeFetch({}) });
    assert.equal(images.challenge, 'https://cdn.example.com/gone.png');
    assert.deepEqual(report.kept_remote, ['challenge']);
  } finally {
    await fs.rm(dir, { recursive: true, force: true });
  }
});

test('non-image responses are not saved and empty slides are skipped', async () => {
  const dir = await fs.mkdtemp(path.join(os.tmpdir(), 'at-store-'));
  try {
    const fetchImpl = fakeFetch({ 'https://x.cl/page': { type: 'text/html', body: '<html>' } });
    const { images } = await persistImages({ pricing: 'https://x.cl/page', solution: null }, dir, { fetchImpl });
    assert.equal(images.pricing, 'https://x.cl/page');
    assert.ok(!('solution' in images));
  } finally {
    await fs.rm(dir, { recursive: true, force: true });
  }
});
```

- [ ] **Step 2: Correr y ver que fallan**

Run: `cd renderer && node --test test/images-store.test.js`
Expected: FAIL con `Cannot find module '../src/images-store'`.

- [ ] **Step 3: Implementar**

`renderer/src/images-store.js`:
```js
const fs = require('node:fs/promises');
const path = require('node:path');

const EXT_BY_TYPE = { 'image/jpeg': 'jpg', 'image/png': 'png', 'image/webp': 'webp' };

// Photos are copied next to index.html so a published proposal never depends
// on a CDN that can expire: Higgsfield's did, and by 2026-09-23 one of the
// seven photos of proposal 42 no longer loaded. A photo that fails to
// download keeps its remote URL — a slide with a possibly-stale photo beats a
// slide with none — and the report says which ones, so the caller can flag it.
async function persistImages(images, outputDir, { fetchImpl = fetch, timeoutMs = 30000 } = {}) {
  const imgDir = path.join(outputDir, 'img');
  await fs.mkdir(imgDir, { recursive: true });
  const stored = {};
  const report = { saved: [], kept_remote: [] };

  for (const [slide, url] of Object.entries(images || {})) {
    if (!url) continue;
    if (!/^https?:\/\//i.test(url)) {
      stored[slide] = url;
      continue;
    }
    try {
      const res = await fetchImpl(url, { signal: AbortSignal.timeout(timeoutMs) });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const type = String(res.headers.get('content-type') || '').split(';')[0].trim().toLowerCase();
      const ext = EXT_BY_TYPE[type];
      if (!ext) throw new Error(`not an image: ${type || 'sin content-type'}`);
      const name = `${slide.replace(/[^a-z0-9_-]/gi, '')}.${ext}`;
      await fs.writeFile(path.join(imgDir, name), Buffer.from(await res.arrayBuffer()));
      stored[slide] = `img/${name}`;
      report.saved.push(slide);
    } catch (err) {
      console.error(`persistImages: kept remote url for "${slide}": ${err.message}`);
      stored[slide] = url;
      report.kept_remote.push(slide);
    }
  }
  return { images: stored, report };
}

module.exports = { persistImages };
```

- [ ] **Step 4: Correr y ver que pasan**

Run: `node --test test/images-store.test.js`
Expected: `pass 3`, `fail 0`.

- [ ] **Step 5: Commit**

```bash
git add renderer/src/images-store.js renderer/test/images-store.test.js
git commit -m "feat(renderer): guardar las fotos junto a la presentacion en vez de enlazar el CDN"
```

---

### Task 2: `/render` usa las fotos guardadas y reporta cuáles faltan

**Files:**
- Modify: `renderer/src/server.js` (bloque `try` de `app.post('/render')` y el `res.json` final)
- Modify: `renderer/test/server-images.test.js` (la prueba de imagen provista)

**Interfaces:**
- Consumes: `persistImages` (Task 1).
- Produces: respuesta de `POST /render` = `{ view_url, pdf_url, images: { requested: number, stored_local: number, kept_remote: string[], missing: string[] } }`. `missing` = láminas con brief y sin foto. Los flujos de n8n (Tasks 10 y 11) leen `images.missing` y `images.kept_remote`.

- [ ] **Step 1: Ajustar la prueba existente y agregar la nueva**

En `renderer/test/server-images.test.js`, la prueba `'a slide whose image is supplied is never requested from Higgsfield'` hoy deja pasar el pedido a `cdn.example.com` a la red real. Reemplaza su `global.fetch` por uno que sirva la foto y cambia la aserción final:
```js
  global.fetch = async (url, opts) => {
    if (String(url).includes('higgsfield')) {
      asked.push(String(url));
      return { ok: false, status: 500, text: async () => 'no debio llamarse' };
    }
    if (String(url) === 'https://cdn.example.com/ya-la-tengo.png') {
      return { ok: true, status: 200, headers: { get: () => 'image/png' }, arrayBuffer: async () => Buffer.from('PNG') };
    }
    return originalFetch(url, opts);
  };
```
y
```js
    assert.ok(html.includes("url('img/cover.png')"), 'la foto provista queda guardada y enlazada en local');
    assert.equal(res.body.images.stored_local, 1);
    assert.deepEqual(res.body.images.missing, []);
```

Agrega al final del archivo:
```js
test('a brief whose photo could not be generated is reported as missing', async () => {
  const dir = await fs.mkdtemp(path.join(os.tmpdir(), 'at-render-'));
  const originalFetch = global.fetch;
  global.fetch = async (url, opts) => {
    if (String(url).includes('higgsfield')) return { ok: false, status: 500, text: async () => 'caido' };
    return originalFetch(url, opts);
  };
  try {
    const app = createApp({ publicDir: dir, baseUrl: 'http://x', higgsfieldCredentials: { keyId: 'k', keySecret: 's' } });
    const res = await post(app, { ...PAYLOAD, unique_id: 'faltante', image_briefs: [{ slide: 'cover', prompt: 'foto' }] });
    assert.equal(res.status, 200);
    assert.equal(res.body.images.requested, 1);
    assert.deepEqual(res.body.images.missing, ['cover']);
  } finally {
    global.fetch = originalFetch;
    await fs.rm(dir, { recursive: true, force: true });
  }
});
```

- [ ] **Step 2: Correr y ver que fallan**

Run: `node --test test/server-images.test.js`
Expected: FAIL (`res.body.images` es `undefined`).

- [ ] **Step 3: Implementar**

En `renderer/src/server.js`, agrega arriba:
```js
const { persistImages } = require('./images-store');
```
Reemplaza el bloque desde `let images;` hasta el `res.json({...})` final por:
```js
    const requested = (Array.isArray(data.image_briefs) ? data.image_briefs : [])
      .map((b) => b && b.slide)
      .filter(Boolean);

    let images;
    let report;
    let html;
    try {
      const generated = await generateProposalImages(pending, higgsfieldCredentials);
      ({ images, report } = await persistImages(Object.assign({}, generated, provided), outputDir));
      html = renderProposalHtml(data, images);
      await renderToFiles(html, outputDir);
    } catch (err) {
      console.error('POST /render failed:', err.stack || err.message);
      return res.status(502).json({ error: 'render failed', details: err.message });
    }

    res.json({
      view_url: `${baseUrl}/p/${data.unique_id}/index.html`,
      pdf_url: `${baseUrl}/p/${data.unique_id}/presentation.pdf`,
      images: {
        requested: requested.length,
        stored_local: report.saved.length,
        kept_remote: report.kept_remote,
        missing: requested.filter((slide) => !images[slide]),
      },
    });
```

- [ ] **Step 4: Correr toda la suite**

Run: `npm test 2>&1 | grep -E "^ℹ (tests|pass|fail)"`
Expected: `fail 0` (72 pruebas: 68 + 3 de Task 1 + 1 nueva).

- [ ] **Step 5: Commit**

```bash
git add renderer/src/server.js renderer/test/server-images.test.js
git commit -m "feat(renderer): /render guarda las fotos en /p/<id>/img y reporta las que faltan"
```

---

### Task 3: Sello "Borrador" en las vistas previas

**Files:**
- Modify: `renderer/src/template.js` (`renderProposalHtml` y `STYLE`)
- Test: `renderer/test/template-deck.test.js`

**Interfaces:**
- Consumes: campo opcional del payload `draft: boolean`.
- Produces: con `draft: true`, un `<div class="at-draft-badge">Borrador · vista previa sin fotos</div>` fijo arriba a la derecha y el botón de PDF oculto. Sin `draft`, el HTML no cambia.

- [ ] **Step 1: Pruebas que fallan** (al final de `test/template-deck.test.js`)

```js
test('draft previews carry a visible badge and hide the PDF button', () => {
  const html = renderProposalHtml({ ...DATA, draft: true });
  assert.ok(html.includes('class="at-draft-badge"'));
  assert.ok(html.includes('Borrador · vista previa sin fotos'));
  assert.ok(html.includes('body.is-draft .pdf-button'));
});

test('final proposals have no draft badge', () => {
  const html = renderProposalHtml(DATA);
  assert.ok(!html.includes('class="at-draft-badge"'));
});
```

- [ ] **Step 2: Correr y ver que fallan**

Run: `node --test test/template-deck.test.js`
Expected: FAIL en las 2 pruebas nuevas.

- [ ] **Step 3: Implementar**

En `STYLE`, junto a `.pdf-button`, agrega:
```css
  .at-draft-badge { position: fixed; top: 18px; right: 18px; z-index: 50; background: #f59e0b; color: #1f1300;
    font: 700 15px/1 system-ui, sans-serif; padding: 10px 16px; border-radius: 999px; letter-spacing: .02em; }
  body.is-draft .pdf-button { display: none !important; }
```
En `renderProposalHtml`, cambia la apertura del `<body>` que hoy es `<body>` por:
```js
<body${data.draft ? ' class="is-draft"' : ''}>
${data.draft ? '<div class="at-draft-badge">Borrador · vista previa sin fotos</div>' : ''}
```
(Verificado al escribir el plan: hay una sola etiqueta `<body>` literal, `template.js:654`, y el script de navegación usa `body.classList.toggle`, así que no borra `is-draft`.)

- [ ] **Step 4: Correr toda la suite**

Run: `npm test 2>&1 | grep -E "^ℹ (tests|pass|fail)"`
Expected: `fail 0`.

- [ ] **Step 5: Commit**

```bash
git add renderer/src/template.js renderer/test/template-deck.test.js
git commit -m "feat(renderer): sello Borrador en vistas previas sin fotos"
```

---

### Task 4: Desplegar el renderer y comprobarlo en vivo

**Files:** ninguno.

- [ ] **Step 1: Armar el zip**

```bash
git archive --format=zip -o "C:/Users/luis_/Downloads/propuesta-renderer-V3-$(git rev-parse --short HEAD).zip" HEAD:renderer
```
Entregar a Luis: Easypanel → proyecto `n8n` → `propuesta-renderer` → Source → Upload → Deploy. Rollback: zip de `324fbf2`.

- [ ] **Step 2: Verificar borrador sin gasto** (después del deploy de Luis)

Run:
```bash
curl -s -m 60 -X POST https://n8n-propuesta-renderer.kchiba.easypanel.host/render -H "Content-Type: application/json" \
  -d '{"unique_id":"verif-v3-borrador","draft":true,"client_name":"Prueba","company_name":"Prueba v3","challenge_title":"D","challenge_text":"T","solution_title":"S","solution_text":"T","benefits":[{"title":"A","text":"B"}],"how_it_works":[{"step_title":"A","step_text":"B"}],"pricing_rows":[{"service":"X","price_usd":0,"price_label":"Por confirmar"}],"next_steps":["Uno"]}'
```
Expected: HTTP 200 con `"images":{"requested":0,...,"missing":[]}`; `curl -s https://n8n-propuesta-renderer.kchiba.easypanel.host/p/verif-v3-borrador/index.html | grep -c at-draft-badge` → `1`.

- [ ] **Step 3: Verificar que las propuestas existentes siguen igual**

Run: `curl -s -o /dev/null -w "%{http_code}" https://n8n-propuesta-renderer.kchiba.easypanel.host/p/uBn21AF16EcM/index.html`
Expected: `200` (el volumen persiste; el deploy no borra presentaciones).

---

### Task 5: Reglas puras del flujo v3 en WordPress

**Files:**
- Create: `wp-content/themes/automatiza-tech/inc/proposals-flow.php`
- Create: `tests/propuestas/flow-test.php`
- Modify: `wp-content/themes/automatiza-tech/inc/rest-proposals.php` y `inc/admin-proposals.php` (una línea `require_once` en cada uno; **no** `functions.php`, ver Global Constraints)

**Interfaces:**
- Produces (todas puras salvo la migración):
  - `at_propuesta_transicion_valida(string $desde, string $hacia): bool`
  - `at_propuesta_puede_enviarse(?string $flujo, string $status): bool`
  - `at_propuesta_aplicar_precios(array $payload, array $filas, string $nota): array`
  - `at_propuesta_conservar_precios(array $guardado, array $entrante): array`
  - `at_propuesta_agregar_comentario(?string $log_json, string $comentario, string $fecha): string`
  - `at_propuesta_costo_fotos(array $payload): array` → `['fotos' => int, 'usd_lista' => float]`
  - `at_propuesta_errores_payload(array $payload): array` → lista de errores (vacía = válido)
  - `automatiza_proposals_migrate_v3(): void` (WordPress; agrega `flujo`, `feedback_log`, `status_note`)

- [ ] **Step 1: Pruebas que fallan**

`tests/propuestas/flow-test.php`:
```php
<?php
// Correr: php tests/propuestas/flow-test.php   (sin WordPress)
require __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/proposals-flow.php';

$fallas = 0;
function ok($cond, $msg) { global $fallas; if ($cond) { echo "ok   $msg\n"; } else { $fallas++; echo "FALLA $msg\n"; } }

// Transiciones
ok(at_propuesta_transicion_valida('borrador', 'ajustando'), 'borrador -> ajustando');
ok(at_propuesta_transicion_valida('borrador', 'generando'), 'borrador -> generando');
ok(!at_propuesta_transicion_valida('borrador', 'sent'), 'borrador no se envía');
ok(!at_propuesta_transicion_valida('generando', 'generando'), 'doble clic en aprobar no repite');
ok(at_propuesta_transicion_valida('lista', 'sent'), 'lista -> sent');
ok(at_propuesta_transicion_valida('error', 'generando'), 'error permite reintentar el final');
ok(!at_propuesta_transicion_valida('desconocido', 'borrador'), 'estado desconocido no transita');

// Candado de envío
ok(at_propuesta_puede_enviarse(null, 'pending'), 'propuesta vieja se envía como siempre');
ok(at_propuesta_puede_enviarse('', 'draft'), 'flujo vacío = vieja');
ok(!at_propuesta_puede_enviarse('v3', 'borrador'), 'v3 en borrador no se envía');
ok(at_propuesta_puede_enviarse('v3', 'lista'), 'v3 lista se envía');

// Precios desde el panel
$p = ['pricing_rows' => [['service' => 'X', 'price_usd' => 0, 'price_label' => 'Por confirmar']], 'pricing_note' => ''];
$p2 = at_propuesta_aplicar_precios($p, [
    ['service' => 'Fase 1', 'price_label' => '$250.000 en 2 pagos'],
    ['service' => '', 'price_label' => '$1'],
    ['service' => 'Soporte', 'price_label' => 'Incluido', 'emphasis' => '1'],
], ' Valores en pesos. ');
ok(count($p2['pricing_rows']) === 2, 'filas vacías se descartan');
ok($p2['pricing_rows'][0] === ['service' => 'Fase 1', 'price_usd' => 0, 'price_label' => '$250.000 en 2 pagos'], 'fila normal');
ok(($p2['pricing_rows'][1]['emphasis'] ?? false) === true, 'fila destacada');
ok($p2['pricing_note'] === 'Valores en pesos.', 'nota recortada');
ok(at_propuesta_aplicar_precios($p, [], 'n')['pricing_rows'] === $p['pricing_rows'], 'sin filas se conservan las anteriores');

// Nadie más cambia precios
$guardado = ['unique_id' => 'abc', 'pricing_rows' => [['service' => 'A', 'price_usd' => 0, 'price_label' => '$1']], 'pricing_note' => 'n', 'challenge_text' => 'viejo'];
$entrante = ['unique_id' => 'otro', 'pricing_rows' => [['service' => 'A', 'price_usd' => 999]], 'pricing_note' => 'inventada', 'challenge_text' => 'nuevo'];
$r = at_propuesta_conservar_precios($guardado, $entrante);
ok($r['pricing_rows'] === $guardado['pricing_rows'], 'precios guardados ganan');
ok($r['pricing_note'] === 'n', 'nota guardada gana');
ok($r['unique_id'] === 'abc', 'unique_id no cambia');
ok($r['challenge_text'] === 'nuevo', 'el resto sí se actualiza');

// Historial de comentarios
$log = at_propuesta_agregar_comentario(null, 'Fase 2 en 2 pagos', '2026-09-23 18:00');
$log = at_propuesta_agregar_comentario($log, 'Ads a $120.000', '2026-09-23 18:05');
$d = json_decode($log, true);
ok(count($d) === 2 && $d[1]['comentario'] === 'Ads a $120.000', 'historial acumula');
ok(count(json_decode(at_propuesta_agregar_comentario('no-json', 'x', 'f'), true)) === 1, 'historial corrupto se reinicia');

// Costo de fotos
$c = at_propuesta_costo_fotos(['image_briefs' => [['slide' => 'cover', 'prompt' => 'a'], ['slide' => 'x'], ['slide' => 'pricing', 'prompt' => 'b']]]);
ok($c === ['fotos' => 2, 'usd_lista' => 0.0064], 'costo = fotos válidas x 0,0032');

// Validación del payload (mismos campos obligatorios que renderer/src/schema.js)
$valido = ['unique_id' => 'a', 'client_name' => 'b', 'company_name' => 'c', 'challenge_title' => 'd', 'challenge_text' => 'e',
    'solution_title' => 'f', 'solution_text' => 'g', 'benefits' => [1], 'how_it_works' => [1], 'pricing_rows' => [1], 'next_steps' => [1]];
ok(at_propuesta_errores_payload($valido) === [], 'payload completo es válido');
$sin = $valido; unset($sin['benefits']); $sin['challenge_text'] = '  ';
ok(count(at_propuesta_errores_payload($sin)) === 2, 'detecta array faltante y texto vacío');

echo $fallas ? "\n$fallas FALLAS\n" : "\nTODO OK\n";
exit($fallas ? 1 : 0);
```

- [ ] **Step 2: Correr y ver que falla**

Run: `"/c/wamp64/bin/php/php8.4.15/php.exe" tests/propuestas/flow-test.php`
Expected: error `Failed opening required ... proposals-flow.php`.

- [ ] **Step 3: Implementar**

`wp-content/themes/automatiza-tech/inc/proposals-flow.php`:
```php
<?php
/**
 * Flujo v3 de propuestas: estados, transiciones y reglas que no deben depender de n8n.
 *
 * Todo menos automatiza_proposals_migrate_v3() es puro (sin WordPress) para poder
 * probarlo con `php tests/propuestas/flow-test.php`.
 *
 * @package AutomatizaTech
 */

/** Transiciones permitidas del flujo v3 (ver Docs/METODO_AT/PROPUESTAS-PLANTILLA-UNICA.md). */
function at_propuesta_transicion_valida(string $desde, string $hacia): bool {
    $permitidas = [
        'borrador'  => ['ajustando', 'generando'],
        'ajustando' => ['borrador', 'error'],
        'generando' => ['lista', 'error'],
        'lista'     => ['ajustando', 'sent'],
        'error'     => ['borrador', 'ajustando', 'generando'],
    ];
    return in_array($hacia, $permitidas[$desde] ?? [], true);
}

/** Las propuestas v3 solo salen al cliente desde 'lista'; las viejas, como siempre. */
function at_propuesta_puede_enviarse(?string $flujo, string $status): bool {
    if ($flujo !== 'v3') {
        return true;
    }
    return in_array($status, ['lista', 'sent'], true);
}

/** Precios que Luis escribe en el panel. Sin filas válidas, se conservan las anteriores. */
function at_propuesta_aplicar_precios(array $payload, array $filas, string $nota): array {
    $limpias = [];
    foreach ($filas as $f) {
        $servicio = trim((string) ($f['service'] ?? ''));
        $precio = trim((string) ($f['price_label'] ?? ''));
        if ($servicio === '' || $precio === '') {
            continue;
        }
        $fila = ['service' => $servicio, 'price_usd' => 0, 'price_label' => $precio];
        if (!empty($f['emphasis'])) {
            $fila['emphasis'] = true;
        }
        $limpias[] = $fila;
    }
    if ($limpias) {
        $payload['pricing_rows'] = $limpias;
    }
    $payload['pricing_note'] = trim($nota);
    return $payload;
}

/** Un payload que llega de n8n nunca cambia precios ni el unique_id de lo guardado. */
function at_propuesta_conservar_precios(array $guardado, array $entrante): array {
    $entrante['pricing_rows'] = $guardado['pricing_rows'] ?? [];
    if (array_key_exists('pricing_note', $guardado)) {
        $entrante['pricing_note'] = $guardado['pricing_note'];
    } else {
        unset($entrante['pricing_note']);
    }
    $entrante['unique_id'] = $guardado['unique_id'] ?? ($entrante['unique_id'] ?? '');
    return $entrante;
}

/** Agrega un comentario al historial (JSON). Un historial ilegible se reinicia. */
function at_propuesta_agregar_comentario(?string $log_json, string $comentario, string $fecha): string {
    $log = json_decode((string) $log_json, true);
    if (!is_array($log)) {
        $log = [];
    }
    $log[] = ['fecha' => $fecha, 'comentario' => $comentario];
    return json_encode($log, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/** Fotos que pedirá el flujo Final y su costo de lista (Soul 2: US$0,0032 c/u, 2026-09-20). */
function at_propuesta_costo_fotos(array $payload): array {
    $n = 0;
    foreach ($payload['image_briefs'] ?? [] as $b) {
        if (!empty($b['slide']) && !empty($b['prompt'])) {
            $n++;
        }
    }
    return ['fotos' => $n, 'usd_lista' => round($n * 0.0032, 4)];
}

/** Mismos campos obligatorios que renderer/src/schema.js. */
function at_propuesta_errores_payload(array $p): array {
    $errores = [];
    foreach (['unique_id', 'client_name', 'company_name', 'challenge_title', 'challenge_text', 'solution_title', 'solution_text'] as $k) {
        if (!isset($p[$k]) || !is_string($p[$k]) || trim($p[$k]) === '') {
            $errores[] = "falta o está vacío: $k";
        }
    }
    foreach (['benefits', 'how_it_works', 'pricing_rows', 'next_steps'] as $k) {
        if (empty($p[$k]) || !is_array($p[$k])) {
            $errores[] = "falta o está vacía la lista: $k";
        }
    }
    return $errores;
}

/** Columnas del flujo v3. Idempotente: se puede correr en cada carga. */
function automatiza_proposals_migrate_v3() {
    if (get_option('at_propuestas_schema') === '3') {
        return;
    }
    global $wpdb;
    $t = $wpdb->prefix . 'automatiza_propuestas';
    $cols = $wpdb->get_col("SHOW COLUMNS FROM {$t}");
    if (!$cols) {
        return;
    }
    if (!in_array('flujo', $cols, true)) {
        $wpdb->query("ALTER TABLE {$t} ADD COLUMN flujo varchar(10) DEFAULT NULL");
    }
    if (!in_array('feedback_log', $cols, true)) {
        $wpdb->query("ALTER TABLE {$t} ADD COLUMN feedback_log longtext DEFAULT NULL");
    }
    if (!in_array('status_note', $cols, true)) {
        $wpdb->query("ALTER TABLE {$t} ADD COLUMN status_note text DEFAULT NULL");
    }
    update_option('at_propuestas_schema', '3');
}

if (function_exists('add_action')) {
    add_action('init', 'automatiza_proposals_migrate_v3');
}
```

En `inc/rest-proposals.php` y en `inc/admin-proposals.php`, justo después de su guarda `if (!defined('ABSPATH')) { exit; }` (o equivalente al inicio del archivo), agrega:
```php
require_once __DIR__ . '/proposals-flow.php';
```
(`require_once` evita la doble carga; así `functions.php` queda intacto.)

- [ ] **Step 4: Correr y ver que pasa**

Run: `"/c/wamp64/bin/php/php8.4.15/php.exe" tests/propuestas/flow-test.php`
Expected: todas las líneas `ok`, `TODO OK`, código de salida 0.
Run también: `"/c/wamp64/bin/php/php8.4.15/php.exe" -l wp-content/themes/automatiza-tech/inc/proposals-flow.php` → `No syntax errors`.

- [ ] **Step 5: Commit**

```bash
git add wp-content/themes/automatiza-tech/inc/proposals-flow.php tests/propuestas/flow-test.php wp-content/themes/automatiza-tech/inc/rest-proposals.php wp-content/themes/automatiza-tech/inc/admin-proposals.php
git commit -m "feat(propuestas): reglas del flujo v3 (estados, precios, envio) y migracion de columnas"
```

---

### Task 6: Rutas REST del flujo v3

**Files:**
- Modify: `wp-content/themes/automatiza-tech/inc/rest-proposals.php` (funciones nuevas + registro en `automatiza_proposals_rest_routes`)

**Interfaces:**
- Consumes: funciones de Task 5; `automatiza_proposals_rest_auth` (ya existe, header `X-AT-Secret`).
- Produces (todas con `X-AT-Secret`, base `https://automatizatech.cl/?rest_route=/automatiza-tech/v1`):
  - `POST /proposal` body `{client_email, client_name, company_name, phone?, transcript, payload, system_prompt}` → 201 `{id, unique_id, view_url, panel_url}`. Crea con `flujo='v3'`, `status='borrador'`, `n8n_chat_url` y `gamma_iframe_url` ya escritos, `gamma_prompt_text` = payload JSON con `unique_id` puesto.
  - `GET /proposal/{id}/state` → `{id, unique_id, flujo, status, status_note, client_email, client_name, company_name, payload, system_prompt, feedback_log, ultimo_comentario}`.
  - `POST /proposal/{id}/state` body `{status, note?, payload?}` → 200 con el mismo formato que GET; 409 si la transición no es válida; 422 si el payload no es válido. El payload entrante pasa por `at_propuesta_conservar_precios`.

- [ ] **Step 1: Implementar**

Agrega en `rest-proposals.php`, antes de `function automatiza_proposals_rest_routes()`:
```php
/** Formato común de salida del estado de una propuesta v3. */
function automatiza_proposals_state_out($row) {
    $log = json_decode((string) $row->feedback_log, true);
    $log = is_array($log) ? $log : [];
    $ultimo = $log ? end($log) : null;
    return [
        'id'                => (int) $row->id,
        'unique_id'         => $row->unique_link_id,
        'flujo'             => $row->flujo,
        'status'            => $row->status,
        'status_note'       => (string) $row->status_note,
        'client_email'      => $row->client_email,
        'client_name'       => $row->client_name,
        'company_name'      => $row->company_name,
        'payload'           => json_decode((string) $row->gamma_prompt_text, true),
        'system_prompt'     => (string) $row->system_prompt_text,
        'feedback_log'      => $log,
        'ultimo_comentario' => $ultimo ? $ultimo['comentario'] : '',
    ];
}

/** POST /proposal — alta de una propuesta v3 en borrador. */
function automatiza_proposals_rest_create(WP_REST_Request $request) {
    global $wpdb;
    $t = $wpdb->prefix . 'automatiza_propuestas';
    $p = $request->get_json_params();
    $payload = is_array($p['payload'] ?? null) ? $p['payload'] : null;
    if (!$payload) {
        return new WP_Error('at_no_payload', 'Falta payload (objeto).', ['status' => 422]);
    }
    $uid = wp_generate_password(12, false);
    $payload['unique_id'] = $uid;
    $errores = at_propuesta_errores_payload($payload);
    if ($errores) {
        return new WP_Error('at_payload_invalido', implode('; ', $errores), ['status' => 422]);
    }
    $email = sanitize_email((string) ($p['client_email'] ?? ''));
    $renderer = 'https://n8n-propuesta-renderer.kchiba.easypanel.host/p/' . $uid . '/index.html';
    $ok = $wpdb->insert($t, [
        'client_email'       => $email,
        'unique_link_id'     => $uid,
        'transcript_text'    => (string) ($p['transcript'] ?? ''),
        'gamma_prompt_text'  => wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'system_prompt_text' => (string) ($p['system_prompt'] ?? ''),
        'gamma_iframe_url'   => $renderer,
        'n8n_chat_url'       => 'https://n8n-n8n.kchiba.easypanel.host/webhook/demo-dinamico/chat',
        'client_name'        => sanitize_text_field((string) $payload['client_name']),
        'company_name'       => sanitize_text_field((string) $payload['company_name']),
        'phone'              => sanitize_text_field((string) ($p['phone'] ?? '')),
        'status'             => 'borrador',
        'flujo'              => 'v3',
        'created_at'         => current_time('mysql'),
    ]);
    if (!$ok) {
        return new WP_Error('at_insert_failed', $wpdb->last_error, ['status' => 500]);
    }
    $id = (int) $wpdb->insert_id;
    return new WP_REST_Response([
        'id'        => $id,
        'unique_id' => $uid,
        'view_url'  => $renderer,
        'panel_url' => admin_url('admin.php?page=automatiza-proposals&edit_id=' . $id),
    ], 201);
}

/** GET /proposal/{id}/state */
function automatiza_proposals_rest_state_get(WP_REST_Request $request) {
    global $wpdb;
    $t = $wpdb->prefix . 'automatiza_propuestas';
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id = %d", (int) $request['id']));
    if (!$row) {
        return new WP_Error('at_proposal_not_found', 'No existe la propuesta.', ['status' => 404]);
    }
    return new WP_REST_Response(automatiza_proposals_state_out($row), 200);
}

/** POST /proposal/{id}/state — cambio de estado (+ payload opcional). */
function automatiza_proposals_rest_state_set(WP_REST_Request $request) {
    global $wpdb;
    $t = $wpdb->prefix . 'automatiza_propuestas';
    $id = (int) $request['id'];
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id = %d", $id));
    if (!$row) {
        return new WP_Error('at_proposal_not_found', 'No existe la propuesta.', ['status' => 404]);
    }
    $p = $request->get_json_params();
    $nuevo = (string) ($p['status'] ?? '');
    if (!at_propuesta_transicion_valida((string) $row->status, $nuevo)) {
        return new WP_Error('at_transicion', "Transición no permitida: {$row->status} → {$nuevo}", ['status' => 409]);
    }
    $update = ['status' => $nuevo, 'status_note' => sanitize_textarea_field((string) ($p['note'] ?? ''))];
    if (is_array($p['payload'] ?? null)) {
        $guardado = json_decode((string) $row->gamma_prompt_text, true) ?: [];
        $payload = at_propuesta_conservar_precios($guardado, $p['payload']);
        $errores = at_propuesta_errores_payload($payload);
        if ($errores) {
            return new WP_Error('at_payload_invalido', implode('; ', $errores), ['status' => 422]);
        }
        $update['gamma_prompt_text'] = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    if ($wpdb->update($t, $update, ['id' => $id]) === false) {
        return new WP_Error('at_update_failed', $wpdb->last_error, ['status' => 500]);
    }
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id = %d", $id));
    return new WP_REST_Response(automatiza_proposals_state_out($row), 200);
}
```
Dentro de `automatiza_proposals_rest_routes()`, agrega al final:
```php
    $id_arg = ['id' => ['required' => true, 'validate_callback' => function ($v) { return is_numeric($v); }]];
    register_rest_route('automatiza-tech/v1', '/proposal', [
        'methods'             => 'POST',
        'callback'            => 'automatiza_proposals_rest_create',
        'permission_callback' => 'automatiza_proposals_rest_auth',
    ]);
    register_rest_route('automatiza-tech/v1', '/proposal/(?P<id>\d+)/state', [
        ['methods' => 'GET', 'callback' => 'automatiza_proposals_rest_state_get', 'permission_callback' => 'automatiza_proposals_rest_auth', 'args' => $id_arg],
        ['methods' => 'POST', 'callback' => 'automatiza_proposals_rest_state_set', 'permission_callback' => 'automatiza_proposals_rest_auth', 'args' => $id_arg],
    ]);
```

- [ ] **Step 2: Sintaxis**

Run: `"/c/wamp64/bin/php/php8.4.15/php.exe" -l wp-content/themes/automatiza-tech/inc/rest-proposals.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit** (la prueba real de las rutas se hace en PROD en la Task 8, porque el sitio local no tiene la base de propuestas)

```bash
git add wp-content/themes/automatiza-tech/inc/rest-proposals.php
git commit -m "feat(propuestas): rutas REST v3 (alta en borrador y estado con transiciones)"
```

---

### Task 7: Panel — revisión v3, botones y candado de envío

**Files:**
- Modify: `wp-content/themes/automatiza-tech/inc/admin-proposals.php`

**Interfaces:**
- Consumes: Task 5 y el estado guardado por Task 6.
- Produces: POST del panel con `at_v3_accion` ∈ {`cambios`, `aprobar`} y nonce `at_v3_<id>`; llama a los webhooks de n8n (Tasks 10 y 11) con `{ "id": <id> }` y header `X-AT-Secret`.

- [ ] **Step 1: Constantes y llamada a n8n** (arriba del archivo, después de `if (!defined('ABSPATH')) exit;` o equivalente)

```php
if (!defined('AT_N8N_V3_CAMBIOS')) {
    define('AT_N8N_V3_CAMBIOS', 'https://n8n-n8n.kchiba.easypanel.host/webhook/propuesta-v3-cambios');
}
if (!defined('AT_N8N_V3_FINAL')) {
    define('AT_N8N_V3_FINAL', 'https://n8n-n8n.kchiba.easypanel.host/webhook/propuesta-v3-final');
}

/** Avisa a n8n; devuelve '' si respondió 2xx o el motivo del fallo. */
function at_v3_llamar_n8n(string $url, int $id): string {
    if (!defined('AT_REST_SECRET') || AT_REST_SECRET === '') {
        return 'AT_REST_SECRET no está configurado.';
    }
    $r = wp_remote_post($url, [
        'timeout' => 15,
        'headers' => ['Content-Type' => 'application/json', 'X-AT-Secret' => AT_REST_SECRET],
        'body'    => wp_json_encode(['id' => $id]),
    ]);
    if (is_wp_error($r)) {
        return $r->get_error_message();
    }
    $code = wp_remote_retrieve_response_code($r);
    return ($code >= 200 && $code < 300) ? '' : "n8n respondió HTTP {$code}";
}
```

- [ ] **Step 2: Manejador de los botones** (antes del manejador de guardado existente, dentro del bloque que procesa POST del panel)

🔴 Los botones v3 están dentro del mismo `<form>` que el guardado normal, y ese guardado hoy se dispara con **cualquier** POST que traiga `proposal_id` (`admin-proposals.php:65`, `if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proposal_id']))`), con la casilla de envío marcada por defecto. Si no se excluye, **pedir cambios enviaría el correo al cliente**. Cambia esa condición a:
```php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proposal_id']) && !isset($_POST['at_v3_accion'])) {
```
y agrega en el Step 6 una verificación manual: con una fila `[PRUEBA]`, **Pedir cambios** no cambia `status` a `sent` ni registra envío.

```php
if (isset($_POST['at_v3_accion'], $_POST['proposal_id']) && current_user_can('manage_options')) {
    $id = (int) $_POST['proposal_id'];
    check_admin_referer('at_v3_' . $id);
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $id));
    $accion = sanitize_key($_POST['at_v3_accion']);
    if ($row && $row->flujo === 'v3' && in_array($accion, ['cambios', 'aprobar'], true)) {
        $payload = json_decode((string) $row->gamma_prompt_text, true) ?: [];
        $filas = isset($_POST['at_precio']) && is_array($_POST['at_precio']) ? wp_unslash($_POST['at_precio']) : [];
        $payload = at_propuesta_aplicar_precios($payload, $filas, sanitize_textarea_field(wp_unslash($_POST['at_nota_precio'] ?? '')));
        $hacia = $accion === 'cambios' ? 'ajustando' : 'generando';
        if (!at_propuesta_transicion_valida((string) $row->status, $hacia)) {
            $message = '<div class="notice notice-error"><p>No se puede pasar de <strong>' . esc_html($row->status) . '</strong> a <strong>' . esc_html($hacia) . '</strong>.</p></div>';
        } else {
            $update = ['gamma_prompt_text' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'status' => $hacia, 'status_note' => ''];
            $comentario = trim(sanitize_textarea_field(wp_unslash($_POST['at_comentario'] ?? '')));
            if ($accion === 'cambios') {
                $update['feedback_log'] = at_propuesta_agregar_comentario($row->feedback_log, $comentario, current_time('mysql'));
            }
            $wpdb->update($table_name, $update, ['id' => $id]);
            $fallo = at_v3_llamar_n8n($accion === 'cambios' ? AT_N8N_V3_CAMBIOS : AT_N8N_V3_FINAL, $id);
            if ($fallo !== '') {
                $wpdb->update($table_name, ['status' => 'error', 'status_note' => 'No se pudo avisar a n8n: ' . $fallo], ['id' => $id]);
                $message = '<div class="notice notice-error"><p>' . esc_html('No se pudo avisar a n8n: ' . $fallo) . '</p></div>';
            } else {
                $message = '<div class="notice notice-success"><p>' . ($accion === 'cambios'
                    ? 'Cambios enviados. Te llegará un correo con la nueva vista previa.'
                    : 'Aprobada. Se están generando las fotos y la versión final; te llegará un correo cuando esté verificada.') . '</p></div>';
            }
        }
    }
}
```

- [ ] **Step 3: Sección visible en el formulario** (en el formulario de edición, antes de `<!-- CHECKBOX PARA ENVIAR CORREO -->`)

```php
<?php if (($edit_proposal->flujo ?? '') === 'v3'):
    $pl = json_decode((string) $edit_proposal->gamma_prompt_text, true) ?: [];
    $costo = at_propuesta_costo_fotos($pl);
    $filas = $pl['pricing_rows'] ?? [];
    for ($i = count($filas); $i < 6; $i++) { $filas[] = ['service' => '', 'price_label' => '']; }
    $log = json_decode((string) $edit_proposal->feedback_log, true) ?: [];
?>
<div class="v3-section" style="margin-top:20px;padding:20px;background:#f0fdfa;border:1px solid #14b8a6;border-radius:8px;">
  <h3 style="margin-top:0;color:#0f766e;">🔁 Revisión de la propuesta (flujo v3)</h3>
  <p>Estado: <strong><?php echo esc_html($edit_proposal->status); ?></strong>
     <?php if ($edit_proposal->status_note): ?> — <?php echo esc_html($edit_proposal->status_note); ?><?php endif; ?>
     · <a href="<?php echo esc_url($edit_proposal->gamma_iframe_url); ?>" target="_blank">Ver vista previa</a></p>
  <?php wp_nonce_field('at_v3_' . $edit_proposal->id); ?>
  <table class="widefat" style="max-width:820px;">
    <thead><tr><th>Servicio</th><th>Precio (texto tal cual)</th><th>Destacar</th></tr></thead>
    <tbody>
    <?php foreach ($filas as $i => $f): ?>
      <tr>
        <td><input type="text" class="regular-text" name="at_precio[<?php echo $i; ?>][service]" value="<?php echo esc_attr($f['service'] ?? ''); ?>"></td>
        <td><input type="text" class="regular-text" name="at_precio[<?php echo $i; ?>][price_label]" value="<?php echo esc_attr($f['price_label'] ?? ''); ?>"></td>
        <td><input type="checkbox" name="at_precio[<?php echo $i; ?>][emphasis]" value="1" <?php checked(!empty($f['emphasis'])); ?>></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p><label>Nota de precios<br><textarea name="at_nota_precio" rows="2" class="large-text"><?php echo esc_textarea($pl['pricing_note'] ?? ''); ?></textarea></label></p>
  <p><label>Comentarios para ajustar (qué cambiar en textos, láminas o chatbot)<br><textarea name="at_comentario" rows="4" class="large-text"></textarea></label></p>
  <?php if ($log): ?><details><summary>Historial de comentarios (<?php echo count($log); ?>)</summary><ul>
    <?php foreach ($log as $c): ?><li><?php echo esc_html($c['fecha'] . ' — ' . $c['comentario']); ?></li><?php endforeach; ?>
  </ul></details><?php endif; ?>
  <p>
    <button type="submit" name="at_v3_accion" value="cambios" class="button">✏️ Pedir cambios (sin costo)</button>
    <button type="submit" name="at_v3_accion" value="aprobar" class="button button-primary"
      onclick="return confirm('Se generarán <?php echo (int) $costo['fotos']; ?> fotos (≈ US$<?php echo esc_js(number_format($costo['usd_lista'], 4, ',', '.')); ?> de lista) y la versión final. ¿Aprobar?');">
      ✅ Aprobar y generar versión final (<?php echo (int) $costo['fotos']; ?> fotos ≈ US$<?php echo esc_html(number_format($costo['usd_lista'], 4, ',', '.')); ?>)</button>
  </p>
</div>
<?php endif; ?>
```
El bloque va dentro del mismo `<form>` de edición, que ya envía `proposal_id` (el manejador existente lo lee en `admin-proposals.php:72`).

- [ ] **Step 4: Candado de envío y estado**

En el manejador de guardado existente (hoy: `$send_email = isset($_POST['send_email']) ...` y `'status' => $send_email ? 'sent' : 'pending'`), reemplaza la construcción del estado por:
```php
        $actual = $wpdb->get_row($wpdb->prepare("SELECT flujo, status FROM {$table_name} WHERE id = %d", $id));
        $es_v3 = $actual && $actual->flujo === 'v3';
        if ($send_email && $actual && !at_propuesta_puede_enviarse($actual->flujo, (string) $actual->status)) {
            $send_email = false;
            $bloqueo_envio = true;
        }
```
y en `$update_data`:
```php
            'status' => $send_email ? 'sent' : ($es_v3 ? $actual->status : 'pending'),
```
Tras el `$wpdb->update(...)`, si `!empty($bloqueo_envio)`:
```php
        if (!empty($bloqueo_envio)) {
            $message = '<div class="notice notice-warning"><p>Propuesta guardada, pero <strong>no se envió</strong>: una propuesta v3 solo se envía cuando está <strong>lista</strong> (versión final verificada).</p></div>';
        }
```
y haz que la rama `if (!$send_email)` no pise ese mensaje (envuelve su `$message = ...` en `if (empty($bloqueo_envio))`).

En el checkbox `send_email` del formulario, para v3 no listas:
```php
<?php $puede = at_propuesta_puede_enviarse($edit_proposal->flujo ?? null, (string) $edit_proposal->status); ?>
<input type="checkbox" name="send_email" value="1" id="send_email" <?php echo $puede ? 'checked' : 'disabled'; ?> style="width: 20px; height: 20px;">
<?php if (!$puede): ?><p style="margin:8px 0 0 30px;color:#b45309;">Se habilita cuando la propuesta esté <strong>lista</strong>.</p><?php endif; ?>
```

- [ ] **Step 5: Texto del PDF en el correo**

Busca `Adjunto encontrará también una copia en PDF de la presentación para su archivo.` El adjunto se decide más abajo (`$attachments`), después de armar `$body`. Mueve el cálculo de `$attachments` antes de armar `$body` y reemplaza ese párrafo por:
```php
' . (!empty($attachments)
    ? '<p style="font-size: 14px; color: #666; text-align: center;">Adjunto encontrará también una copia en PDF de la presentación para su archivo.</p>'
    : '<p style="font-size: 14px; color: #666; text-align: center;">Puede descargar la presentación en PDF desde el botón del final de la presentación.</p>') . '
```

- [ ] **Step 6: Sintaxis y pruebas**

Run: `"/c/wamp64/bin/php/php8.4.15/php.exe" -l wp-content/themes/automatiza-tech/inc/admin-proposals.php` → `No syntax errors`.
Run: `grep -n "isset(\$_POST\['proposal_id'\]) && !isset(\$_POST\['at_v3_accion'\])" wp-content/themes/automatiza-tech/inc/admin-proposals.php` → 1 línea (el guardado normal excluye los botones v3).
Run: `"/c/wamp64/bin/php/php8.4.15/php.exe" tests/propuestas/flow-test.php` → `TODO OK`.

- [ ] **Step 7: Commit**

```bash
git add wp-content/themes/automatiza-tech/inc/admin-proposals.php
git commit -m "feat(propuestas): panel v3 con precios, cambios y aprobacion; envio solo desde lista"
```

---

### Task 8: Desplegar WordPress en PROD y probar las rutas

**Files:** `inc/proposals-flow.php` (nuevo), `inc/rest-proposals.php`, `inc/admin-proposals.php`. **`functions.php` no se sube.** **Requiere autorización explícita de Luis para este deploy.**

- [ ] **Step 1: Cotejar PROD antes de subir** (la copia local puede estar vieja)

Por SSH (helper `prod_ssh.py` del scratchpad, que tapa valores), sin retornos de carro: `tr -d '\r' < <archivo> | md5sum` de `inc/admin-proposals.php` e `inc/rest-proposals.php` en PROD vs `git show <commit base de la Task 5>:<ruta> | tr -d '\r' | md5sum`. Cotejo del 2026-09-24: ambos idénticos (`5cde0abb1ea0…` y `e115a143e163…`). Si alguno cambió desde entonces, **detenerse** y traer el de PROD al repo antes de aplicar los cambios encima.

- [ ] **Step 2: Respaldar y subir**

```bash
# en el servidor
cd ~/domains/automatizatech.cl/public_html/wp-content/themes/automatiza-tech
tar czf ~/respaldos/tema-propuestas-antes-deploy-v3-$(date +%Y%m%d-%H%M).tar.gz inc/admin-proposals.php inc/rest-proposals.php
mysqldump no está disponible desde PHP en Hostinger: repetir el respaldo de la tabla con el mismo script PHP del 2026-09-24 (SELECT * → archivo .sql 0600 en ~/respaldos) justo antes de la migración.
```
Subir por SFTP: `inc/proposals-flow.php` **primero** (los otros dos lo requieren), luego `inc/rest-proposals.php` e `inc/admin-proposals.php`. `php -l` de cada uno en el servidor. Rollback: extraer el tar de este paso sobre la misma carpeta y borrar `inc/proposals-flow.php`; las columnas nuevas pueden quedarse (son `DEFAULT NULL` y el código viejo no las lee).

- [ ] **Step 3: Migración**

Cargar cualquier página (`curl -s -o /dev/null https://automatizatech.cl/`) y verificar por SSH:
`SHOW COLUMNS FROM wp_automatiza_propuestas` incluye `flujo`, `feedback_log`, `status_note`; `get_option('at_propuestas_schema')` = `3`.

- [ ] **Step 4: Probar las rutas desde afuera** (Luis corre los comandos con su secreto, o se usa el helper sin imprimir el valor)

```bash
curl -s -X POST "https://automatizatech.cl/?rest_route=/automatiza-tech/v1/proposal" -H "Content-Type: application/json" -H "X-AT-Secret: $AT_REST_SECRET" \
  -d '{"client_email":"contacto@automatizatech.cl","transcript":"prueba","system_prompt":"prueba","payload":{"client_name":"Prueba","company_name":"[PRUEBA] v3","challenge_title":"D","challenge_text":"T","solution_title":"S","solution_text":"T","benefits":[{"title":"A","text":"B"}],"how_it_works":[{"step_title":"A","step_text":"B"}],"pricing_rows":[{"service":"X","price_usd":0,"price_label":"Por confirmar"}],"next_steps":["Uno"]}}'
```
Expected: 201 con `id` y `unique_id`. Luego:
- `GET .../proposal/<id>/state` → `status: borrador`, `flujo: v3`.
- `POST .../proposal/<id>/state` `{"status":"sent"}` → **409**.
- `POST .../proposal/<id>/state` `{"status":"ajustando","payload":{...con pricing_rows distinto...}}` → 200 y `payload.pricing_rows` **igual** al guardado.
- Sin header → 401.
- La propuesta 43 de Orly (`flujo` NULL) se abre y se ve igual en el panel.

La fila `[PRUEBA] v3` queda para las Tasks 9–11; su borrado lo hace Luis al final (es irreversible).

---

### Task 9: Credencial compartida en n8n (Luis)

**Files:** ninguno.

- [ ] **Step 1: Luis crea la credencial**

n8n → Credentials → New → **Header Auth** → nombre `AT REST Secret (header)`, Name `X-AT-Secret`, Value = el mismo valor de `AT_REST_SECRET`. Ningún agente escribe ni lee el valor.

- [ ] **Step 2: Anotar su id**

Con `n8n_manage_credentials` (acción list) buscar `AT REST Secret (header)` y anotar el `id` para las Tasks 10–12.

---

### Task 10: n8n "Propuestas v3 · 1 Borrador"

**Files:** workflow nuevo en n8n (crear con `n8n_create_workflow`, activar con `n8n_update_partial_workflow` → `activateWorkflow`). Ruta nueva: nadie la llama hasta la Task 13.

**Interfaces:**
- Consumes: `POST /webhook/propuesta-v3-borrador` body `{transcript, client_email, drive_file_id?, prueba?}` (mismo formato que manda hoy el flujo de Meet, más `prueba`).
- Produces: fila v3 en `borrador` con vista previa renderizada, o en `error` con `status_note`; correo a Luis.

- [ ] **Step 1: Nodos** (en orden)

1. **Webhook (Entrada)** — `n8n-nodes-base.webhook`, POST, path `propuesta-v3-borrador`, `responseMode: onReceived` (responde de inmediato), sin auth (igual que hoy; lo llama el flujo de Meet).
2. **Redactar propuesta** — `n8n-nodes-base.openAi` (credencial `OpenAi account`, id `g52IEXpRfN5r7jKw`), resource chat, model `gpt-4o`, `options.responseFormat: json_object`, mensaje system:

```
Eres consultor senior de AutomatizaTech (Chile). Con la transcripción de una reunión con un cliente, redacta el contenido de una propuesta comercial que se mostrará con una plantilla fija de láminas. Responde SOLO un objeto JSON con esta forma exacta:
{
 "client_name": "Nombre y apellido del cliente",
 "company_name": "Nombre del negocio",
 "phone": "teléfono del cliente si aparece, si no vacío",
 "challenge_title": "máx. 7 palabras",
 "challenge_text": "60 a 80 palabras, con datos concretos dichos en la reunión (cifras, lugares, experiencias). No inventes cifras.",
 "solution_title": "máx. 7 palabras",
 "solution_text": "60 a 80 palabras: qué hará AutomatizaTech y cómo resuelve cada problema mencionado",
 "benefits": [{"title": "máx. 5 palabras", "text": "una línea, empieza en minúscula"}],
 "how_it_works": [{"step_title": "máx. 4 palabras", "step_text": "una línea, empieza en minúscula"}],
 "extra_slides": [{"eyebrow": "etiqueta corta", "title": "título", "bullets": [{"title": "punto", "text": "una línea"}]}],
 "pricing_rows": [{"service": "nombre del servicio o fase", "price_usd": 0, "price_label": "Por confirmar"}],
 "pricing_note": "",
 "next_steps": ["paso concreto acordado en la reunión"],
 "image_briefs": [{"slide": "cover|challenge|solution|benefits|how_it_works|extra_1|extra_2|pricing|next_steps", "prompt": "descripción en inglés"}],
 "tono": "tu o usted",
 "resumen_para_luis": "3 a 5 líneas: qué pidió el cliente, presupuesto mencionado, dudas abiertas"
}
Reglas:
- Español de Chile. Trata al cliente de "tú", salvo rubros donde corresponde "usted" (funerario, salud, legal): ahí "usted" en todo el texto.
- benefits: 4 o 5. how_it_works: 4 o 5 pasos. next_steps: 3 o 4.
- extra_slides: 0, 1 o 2, solo si la reunión trae material real (por ejemplo fases o planes que el cliente pidió). No rellenes.
- PRECIOS: nunca escribas montos. Cada fila lleva "price_label": "Por confirmar" y "price_usd": 0. Las filas nombran los servicios o fases que se cotizarán. "pricing_note" vacío.
- image_briefs: una por lámina usada (cover, challenge, solution, benefits, how_it_works, pricing, next_steps y extra_N por cada extra). Fotografía realista, cálida y respetuosa del rubro. Cada prompt termina con: "no people facing camera, no screens, no phones, no computers, no papers, no signs, no text, no lettering, no logos, no watermarks". Nunca infografías, diagramas ni oficinas con papeles.
- No inventes datos del cliente (teléfonos, direcciones, precios, años) que no estén en la transcripción.
```
mensaje user: `={{ $json.body.transcript }}`.

3. **Personalidad del chatbot** — mismo tipo y credencial, `gpt-4o`, texto plano, system:
```
Escribe el system prompt de un asistente virtual de demostración para este negocio, en español de Chile. Estructura: identidad (1 párrafo); TONO (tú o usted según el rubro, breve, sin emojis si el rubro es delicado); ATENCIÓN URGENTE (si aplica al rubro: primero empatía, luego el contacto directo del negocio); SERVICIOS; PRECIOS (solo los que aparezcan en la transcripción, con la aclaración de que un asesor confirma); REGLAS (no inventar datos; derivar a un humano cuando hay intención clara de contratar pidiendo nombre, teléfono y comuna). Usa solo datos que estén en la transcripción.
```
user: `={{ $('Webhook (Entrada)').item.json.body.transcript }}`.

4. **Armar payload** — `n8n-nodes-base.code`:
```js
const raw = $('Redactar propuesta').first().json.message.content;
const d = typeof raw === 'string' ? JSON.parse(raw) : raw;
const body = $('Webhook (Entrada)').first().json.body;
const prueba = body.prueba === true;
// Reglas que no se le confían al modelo: precios y cantidad de láminas extra.
d.pricing_rows = (d.pricing_rows || []).map((r) => ({ service: String(r.service || 'Servicio'), price_usd: 0, price_label: 'Por confirmar' }));
d.pricing_note = '';
d.extra_slides = (d.extra_slides || []).slice(0, 2);
const validSlides = new Set(['cover', 'challenge', 'solution', 'benefits', 'how_it_works', 'pricing', 'next_steps', ...d.extra_slides.map((_, i) => `extra_${i + 1}`)]);
d.image_briefs = (d.image_briefs || []).filter((b) => b && validSlides.has(b.slide) && b.prompt);
if (prueba) d.company_name = `[PRUEBA] ${d.company_name}`;
const resumen = d.resumen_para_luis || '';
const phone = d.phone || '';
delete d.resumen_para_luis; delete d.tono; delete d.phone;
return [{ json: {
  payload: d,
  resumen,
  phone,
  client_email: prueba ? 'contacto@automatizatech.cl' : (body.client_email || ''),
  transcript: body.transcript,
  system_prompt: $('Personalidad del chatbot').first().json.message.content,
} }];
```
5. **Crear en WordPress** — `n8n-nodes-base.httpRequest` POST `https://automatizatech.cl/?rest_route=/automatiza-tech/v1/proposal`, auth `genericCredentialType` → `httpHeaderAuth` (credencial de la Task 9), JSON body:
`={{ JSON.stringify({ client_email: $json.client_email, phone: $json.phone, transcript: $json.transcript, system_prompt: $json.system_prompt, payload: $json.payload }) }}`.
6. **Vista previa (sin fotos)** — httpRequest POST `https://n8n-propuesta-renderer.kchiba.easypanel.host/render`, timeout 120000, `onError: continueRegularOutput`, body:
`={{ JSON.stringify(Object.assign({}, $('Armar payload').item.json.payload, { unique_id: $('Crear en WordPress').item.json.unique_id, draft: true, image_briefs: [] })) }}`.
7. **¿Render OK?** — `n8n-nodes-base.if`: `{{ !!$json.view_url }}` es true.
8. (rama false) **Marcar error** — httpRequest POST `.../proposal/{{ $('Crear en WordPress').item.json.id }}/state` body `{"status":"error","note":"El renderer no respondió al generar la vista previa"}` → luego al correo.
9. **Correo a Luis** — `n8n-nodes-base.emailSend` (credencial `SMTP account PROD`, id `dyhVFWmjRNC45ccA`), from `contacto@automatizatech.cl`, to `lmgm.0303@gmail.com`, subject `={{ ($('Armar payload').item.json.payload.company_name) }} · borrador listo para revisar`, HTML:
```html
<h3>Borrador de propuesta: {{ $('Armar payload').item.json.payload.company_name }}</h3>
<p>{{ $('Armar payload').item.json.resumen }}</p>
<p><a href="{{ $('Crear en WordPress').item.json.view_url }}">👀 Ver vista previa (sin fotos)</a></p>
<p><a href="{{ $('Crear en WordPress').item.json.panel_url }}">✏️ Revisar en el panel: precios, comentarios y aprobación</a></p>
<p style="color:#666">Los precios dicen «Por confirmar» hasta que los escribas en el panel. No se ha gastado nada.</p>
```
(conectar ambas ramas del IF a este correo; en la rama de error el asunto lleva el prefijo `⚠️`).

- [ ] **Step 2: Validar y activar**

`n8n_validate_workflow` → sin errores. Activar.

- [ ] **Step 3: Probar con la transcripción de Orly**

```bash
python - <<'EOF'
import json, urllib.request
t = open(r'C:/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad/orly/transcript.txt', encoding='utf-8').read()
req = urllib.request.Request('https://n8n-n8n.kchiba.easypanel.host/webhook/propuesta-v3-borrador',
    data=json.dumps({'transcript': t, 'client_email': 'contacto@automatizatech.cl', 'prueba': True}).encode(),
    headers={'Content-Type': 'application/json'})
print(urllib.request.urlopen(req, timeout=30).status)
EOF
```
(si esa ruta del scratchpad ya no existe, exportar el texto del Doc "Funerarias Amor de Dios (Orly Díaz)" de la carpeta Transcripciones).
Expected, medido:
- `n8n_executions` del workflow: última en `success`.
- `GET .../proposal/<nuevo id>/state`: `status: borrador`, `company_name` empieza con `[PRUEBA]`, todas las `price_label` = `Por confirmar`, `image_briefs` sin `how_it_works` de tipo infografía.
- La vista previa tiene `at-draft-badge` y **ninguna** foto (`grep -c "url('" index.html` = 0).
- Llegó el correo a Luis (preguntarle).

---

### Task 11: n8n "Propuestas v3 · 2 Cambios"

**Files:** workflow nuevo en n8n.

**Interfaces:**
- Consumes: `POST /webhook/propuesta-v3-cambios` `{id}` con `X-AT-Secret` (lo llama el panel, Task 7); `GET/POST .../proposal/{id}/state` (Task 6).
- Produces: estado `borrador` con la vista previa rehecha, o `error`.

- [ ] **Step 1: Nodos**

1. **Webhook** — path `propuesta-v3-cambios`, POST, authentication `headerAuth` (credencial Task 9), `responseMode: onReceived`.
2. **Leer estado** — httpRequest GET `https://automatizatech.cl/?rest_route=/automatiza-tech/v1/proposal/{{ $json.body.id }}/state` (header auth).
3. **¿Hay comentario?** — IF `{{ ($json.ultimo_comentario || '').trim().length > 0 }}`.
4. (true) **Aplicar comentarios** — openAi `gpt-4o`, `responseFormat: json_object`, system:
```
Recibes el JSON de una propuesta comercial y los comentarios del consultor. Devuelve el JSON completo con SOLO los cambios que piden los comentarios; todo lo demás queda idéntico, con la misma forma y claves. No toques pricing_rows, pricing_note ni unique_id (se descartan igual). Mantén español de Chile y el mismo tratamiento (tú/usted). Si un comentario pide cambiar precios, ignóralo: los precios se editan en el panel. Si pide una lámina extra nueva, agrégala en extra_slides (máximo 2) y su image_brief extra_N con el mismo cierre de prohibiciones que las demás.
```
user: `={{ JSON.stringify({ comentarios: $json.ultimo_comentario, propuesta: $json.payload }) }}`.
5. **Payload final** — code:
```js
const estado = $('Leer estado').first().json;
let p = estado.payload;
const ia = $('Aplicar comentarios').all();
if (ia.length && ia[0].json.message) {
  const c = ia[0].json.message.content;
  p = typeof c === 'string' ? JSON.parse(c) : c;
}
// WordPress restaura precios y unique_id de todos modos; aquí solo se asegura el formato.
p.extra_slides = (p.extra_slides || []).slice(0, 2);
return [{ json: { id: estado.id, unique_id: estado.unique_id, payload: p } }];
```
(la rama false del IF va directo a este nodo: solo cambiaron precios y basta con volver a renderizar).
6. **Guardar y volver a borrador** — httpRequest POST `.../proposal/{{ $json.id }}/state` body `={{ JSON.stringify({ status: 'borrador', note: '', payload: $json.payload }) }}`, `onError: continueRegularOutput`.
7. **Vista previa** — httpRequest POST `/render` body `={{ JSON.stringify(Object.assign({}, $json.payload, { unique_id: $json.unique_id, draft: true, image_briefs: [] })) }}` (usa el payload **devuelto por WordPress**, que ya trae los precios del panel), timeout 120000, `onError: continueRegularOutput`.
8. **¿Todo OK?** — IF `{{ !!$('Vista previa').item.json.view_url && $('Guardar y volver a borrador').item.json.status === 'borrador' }}`.
9. (false) **Marcar error** — POST state `{"status":"error","note":"Falló al aplicar cambios o al renderizar; revisar ejecución <id> en n8n"}` usando `{{ $execution.id }}`. (Si el estado quedó en `borrador` y falló solo el render, la transición `borrador→error` no existe: en ese caso el nodo recibe 409 y se deja `onError: continueRegularOutput`; el correo avisa igual).
10. **Correo a Luis** — asunto `={{ ... company_name }} · cambios aplicados` (o `⚠️ ... no se pudieron aplicar`), con el enlace a la vista previa y al panel.

- [ ] **Step 2: Validar, activar y probar**

En el panel, abrir la fila `[PRUEBA]` de la Task 10: escribir precios (`Fase 1 · $250.000 en 2 pagos`) y el comentario "cambia el título del desafío por «Una página que no te trae llamadas»" → **Pedir cambios**.
Expected, medido: la ejecución termina en `success`; `GET state` → `status: borrador`, el título cambió, `pricing_rows[0].price_label = "$250.000 en 2 pagos"`; la vista previa muestra ese precio y el nuevo título; `feedback_log` tiene 1 entrada.

---

### Task 12: n8n "Propuestas v3 · 3 Final" (fotos, render y verificación)

**Files:** workflow nuevo en n8n. **La prueba gasta fotos: pedir el "ok" de Luis con el costo antes del Step 2** (el número sale del botón del panel).

**Interfaces:**
- Consumes: `POST /webhook/propuesta-v3-final` `{id}` con `X-AT-Secret`; respuesta de `/render` con `images` (Task 2).
- Produces: estado `lista` (verificada) o `error` con el detalle de qué falló.

- [ ] **Step 1: Nodos**

1. **Webhook** — path `propuesta-v3-final`, header auth, `responseMode: onReceived`.
2. **Leer estado** — GET state del `id`.
3. **Render final** — httpRequest POST `/render`, **timeout 290000** (el renderer se da 210 s para fotos + render), `onError: continueRegularOutput`, body `={{ JSON.stringify(Object.assign({}, $json.payload, { unique_id: $json.unique_id, draft: false })) }}` (con `image_briefs`: aquí sí se generan).
4. **Verificar** — code:
```js
const estado = $('Leer estado').first().json;
const r = $('Render final').first().json || {};
const problemas = [];
if (!r.view_url) problemas.push('el renderer no devolvió la presentación');
const img = r.images || {};
if ((img.missing || []).length) problemas.push(`fotos que no se generaron: ${img.missing.join(', ')}`);
if ((img.kept_remote || []).length) problemas.push(`fotos que no se pudieron guardar en local: ${img.kept_remote.join(', ')}`);
const pres = await this.helpers.httpRequest({ method: 'GET', url: `https://automatizatech.cl/ver-presentacion.php?id=${estado.unique_id}`, returnFullResponse: true, ignoreHttpStatusErrors: true });
if (pres.statusCode !== 200) problemas.push(`ver-presentacion respondió ${pres.statusCode}`);
const pdf = await this.helpers.httpRequest({ method: 'HEAD', url: r.pdf_url || 'https://invalid.invalid', returnFullResponse: true, ignoreHttpStatusErrors: true }).catch(() => ({ statusCode: 0 }));
if (pdf.statusCode !== 200) problemas.push(`el PDF respondió ${pdf.statusCode}`);
const chat = await this.helpers.httpRequest({ method: 'POST', url: 'https://n8n-n8n.kchiba.easypanel.host/webhook/demo-dinamico/chat', body: { chatInput: 'Hola', sessionId: estado.unique_id, action: 'sendMessage' }, json: true, returnFullResponse: true, ignoreHttpStatusErrors: true }).catch(() => ({ statusCode: 0, body: {} }));
if (chat.statusCode !== 200 || !(chat.body && chat.body.output)) problemas.push(`el chatbot de demo no respondió (HTTP ${chat.statusCode})`);
return [{ json: { id: estado.id, unique_id: estado.unique_id, company: estado.company_name, ok: problemas.length === 0, problemas, view_url: r.view_url, pdf_url: r.pdf_url, fotos: img, chat_respuesta: chat.body && chat.body.output } }];
```
5. **Guardar resultado** — POST state `={{ JSON.stringify($json.ok ? { status: 'lista', note: 'Verificada: presentación, PDF, ' + ($json.fotos.stored_local || 0) + ' fotos locales y chatbot OK' } : { status: 'error', note: $json.problemas.join(' · ') }) }}`.
6. **Correo a Luis** — asunto `={{ $json.ok ? '✅ ' + $json.company + ' · lista para enviar' : '⚠️ ' + $json.company + ' · la versión final tiene problemas' }}`; cuerpo con la lista de verificaciones (o `problemas`), la respuesta del chatbot, los enlaces a la presentación, al PDF y al panel, y la frase «El envío al cliente lo haces tú desde el panel».

- [ ] **Step 2: Probar** (con el "ok" de Luis)

En el panel, fila `[PRUEBA]` → **Aprobar**. Expected, medido:
- La ejecución termina en `success` en menos de 300 s.
- `GET state` → `status: lista`, `status_note` empieza con `Verificada`.
- `index.html` publicado: `grep -c "url('img/" index.html` = número de fotos; `grep -c cloudfront` = 0.
- El PDF trae una imagen de fondo por lámina (pymupdf: `get_images` ≥ 2 en cada página de contenido).
- El checkbox de envío del panel ahora está habilitado; **no enviar** (es una prueba).
- Segundo clic a **Aprobar** con la fila en `lista`: el panel lo rechaza (transición `lista→generando` no existe).

---

### Task 13: Cambiar el flujo de Meet al v3 y documentar

**Files:**
- Modify (n8n): nodo **Enviar a Workflow Propuestas** de `FrWZcgbizlipK5pb` → URL `https://n8n-n8n.kchiba.easypanel.host/webhook/propuesta-v3-borrador`.
- Modify: `Docs/METODO_AT/PROPUESTAS-PLANTILLA-UNICA.md` (sección nueva "Flujo automático v3").

- [ ] **Step 1: Respaldar y cambiar la URL** — exportar primero el workflow completo (`n8n_get_workflow` modo `full`) a `C:/Users/luis_/respaldos/n8n/2026-09-23/FrWZcgbizlipK5pb-antes-v3.json` y comprobar que el archivo tiene los 8 nodos. Luego `n8n_update_partial_workflow` (`updateNode` → `parameters.url`). Valor anterior para revertir: `=https://n8n-n8n.kchiba.easypanel.host/webhook/generar-propuesta-v2`.

- [ ] **Step 2: Documentar** — agregar a la guía:
```markdown
## Flujo automático v3

1. Transcripción nueva en Drive › Transcripciones → n8n «Propuestas v3 · 1 Borrador»: redacta con la plantilla,
   precios «Por confirmar», vista previa **sin fotos** (sello Borrador) y correo a Luis. Estado `borrador`.
2. Panel (propuesta › «Revisión v3»): Luis escribe precios y comentarios → **Pedir cambios** → «2 Cambios» aplica los
   comentarios (nunca toca precios: WordPress los restaura) y rehace la vista previa. Se repite las veces que haga falta.
3. **Aprobar** (muestra fotos y costo) → «3 Final»: genera las fotos, las guarda junto a la presentación, renderiza y
   verifica presentación, PDF y chatbot. Queda `lista` o `error` con el detalle.
4. Envío: solo desde `lista`, con la casilla «Enviar correo» del panel.

El flujo viejo (`generar-propuesta-v2`, workflow `APuTGmusbjLAJ74w`) queda de respaldo, sin llamadas.
```

- [ ] **Step 3: Prueba de punta a punta** — subir a la carpeta Transcripciones una copia del Doc de Orly con el título `PRUEBA v3 flujo completo` y verificar que en ≤ 2 minutos aparece la ejecución de «1 Borrador» y el correo. (El flujo de Meet exige correo del cliente en el texto; si no lo encuentra, llega el aviso «email faltante» en vez del borrador: también es un resultado válido de la prueba.)

- [ ] **Step 4: Commit y PR**

```bash
git add Docs/METODO_AT/PROPUESTAS-PLANTILLA-UNICA.md
git commit -m "docs(propuestas): flujo automatico v3 con revision y envio manual"
git push -u origin claude/propuestas-v3
gh pr create --base main --title "feat(propuestas): flujo v3 con borrador, cambios, final verificado y envio manual" --body "Implementa Docs/superpowers/plans/2026-09-23-flujo-propuestas-v3.md: renderer guarda fotos y sella borradores; WordPress con estados v3, precios solo desde el panel y envio solo desde lista; n8n Borrador, Cambios y Final. Pruebas: npm test (renderer), php tests/propuestas/flow-test.php y las verificaciones en vivo de las Tasks 4, 8, 10, 11 y 12."
```

- [ ] **Step 5: Limpieza (Luis)** — borrar desde el panel las filas `[PRUEBA]` y las carpetas `verif-v3-*` del renderer (consola de Easypanel: `rm -rf /app/public/verif-v3-borrador`). Son borrados irreversibles: los hace Luis.

---

## Qué NO cubre este plan (a propósito)

- Cambiar el modelo de GPT-4o a otro: se mantiene la credencial que ya funciona.
- Cotizar precios automáticamente: los precios los pone Luis; la regla de fundamentos exige referencias de mercado, y eso se hace a pedido, no dentro del flujo.
- Enviar por WhatsApp: el envío sigue siendo el correo del panel.
- Re-renderizar propuestas viejas (Jeffer queda como está por decisión de Luis).
