# Renderizador propio de propuestas (reemplazo de Gamma) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reemplazar el paso manual "copiar el prompt y pegarlo en Gamma" del pipeline de propuestas por un microservicio propio (`propuesta-renderer`) que genera la presentación (HTML + PDF) automáticamente dentro del mismo workflow de n8n.

**Architecture:** Un microservicio Node.js/Express + Playwright, desplegado como contenedor nuevo en el proyecto `n8n` de Easypanel (junto a `reel-media-worker`), recibe el contenido estructurado que genera `OpenAI (Cerebro)`, genera las fotos por slide con la API REST de Higgsfield, renderiza una plantilla HTML de marca AutomatizaTech a una página pública + PDF, y n8n guarda esas dos URLs en las columnas `gamma_iframe_url`/`pdf_path` que el panel de WordPress (`admin-proposals.php`) y `ver-presentacion.php` ya consumen sin cambios.

**Tech Stack:** Node.js (Express, Playwright), PHP (WordPress endpoint estilo `api-save-proposal.php`), n8n (HTTP Request nodes), Docker (imagen base `mcr.microsoft.com/playwright`), Easypanel.

## Global Constraints

- Salida: solo link web (HTML embebible en iframe) + PDF. Nada de `.pptx`.
- `/p/{unique_id}/...` NUNCA debe enviar `X-Frame-Options` ni una CSP con `frame-ancestors` restrictivo — debe quedar embebible en el `<iframe>` de `ver-presentacion.php`.
- Logo real de AutomatizaTech como watermark: `https://automatizatech.cl/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech%20+%20slogan.png`, pequeño y discreto (no centrado, no agrandado).
- No se tocan `wp-content/themes/automatiza-tech/inc/admin-proposals.php` ni `ver-presentacion.php` — ya leen `gamma_iframe_url`/`pdf_path`.
- No hay cambios de schema en `wp_automatiza_propuestas` — esas columnas ya existen.
- Un fallo en la generación de una imagen puntual nunca debe bloquear la propuesta completa — cae a un degradado de marca AT para esa slide.
- `api-save-presentation.php` sigue exactamente el mismo patrón de seguridad que `api-save-proposal.php`: `at-rate-limit.php` + `at-webhook-verify.php` (HMAC con fallback a whitelist de IP), sin `?>` de cierre al final del archivo (evita el bug de JSON corrupto ya corregido hoy en el archivo hermano).
- Higgsfield: API REST (no MCP) en `https://platform.higgsfield.ai/higgsfield-ai/soul/standard`, header `Authorization: Key ${HF_API_KEY_ID}:${HF_API_KEY_SECRET}`.
- Credenciales (`HF_API_KEY_ID`, `HF_API_KEY_SECRET`) viven como variables de entorno del contenedor en Easypanel — nunca en el repo.

## Nota de diseño (refinamiento sobre el spec aprobado)

El spec original ponía la generación de imágenes ("Generar Imágenes Propuesta") como un nodo nuevo en n8n. Al planificar la implementación se decidió mover esa lógica **dentro del microservicio `propuesta-renderer`** en vez de construirla como nodos nativos de n8n:

- El Code node de n8n no tiene acceso directo y estable al almacén de credenciales para hacer `fetch` autenticado sin exponer la key en el propio código del nodo.
- La lógica de "enviar 6 solicitudes async, sondear cada una, y usar un degradado si falla" es mucho más simple, legible y testeable como JS versionado en el repo que como una cadena de nodos HTTP Request + Wait + IF en n8n.
- El resultado funcional es idéntico al spec: n8n sigue sin tener que saber nada de Higgsfield, solo llama a `POST /render` con el contenido ya generado por `OpenAI (Cerebro)` y recibe `{view_url, pdf_url}`.

---

## File Structure

```
renderer/
  package.json
  Dockerfile
  .dockerignore
  .env.example
  src/
    escape.js        # escapar HTML de texto generado por IA
    schema.js         # validar el payload que llega a POST /render
    higgsfield.js       # cliente REST de Higgsfield (submit + poll + fallback a null)
    template.js          # función pura: datos + urls de imagen -> HTML completo
    render.js             # Playwright: HTML -> archivo .html + .pdf en disco
    server.js              # Express: POST /render, GET /health, estático /p
    index.js                # arranca el server leyendo variables de entorno
  test/
    escape.test.js
    schema.test.js
    template.test.js
    higgsfield.test.js
    render.test.js
    server.test.js
api-save-presentation.php   # raíz del repo, hermano de api-save-proposal.php
```

---

### Task 1: Scaffold del proyecto `renderer/` + health check

**Files:**
- Create: `renderer/package.json`
- Create: `renderer/src/server.js`
- Create: `renderer/src/index.js`
- Create: `renderer/test/server.test.js` (solo el test de health check en este task; los demás tests de este archivo se agregan en el Task 7)
- Create: `renderer/.env.example`
- Create: `renderer/.dockerignore`

**Interfaces:**
- Produces: `createApp({ publicDir, baseUrl, higgsfieldCredentials })` → instancia de Express con `GET /health`. `start()` arranca el server leyendo `process.env`.

- [ ] **Step 1: Crear `package.json`**

```json
{
  "name": "propuesta-renderer",
  "version": "1.0.0",
  "private": true,
  "main": "src/index.js",
  "scripts": {
    "start": "node src/index.js",
    "test": "node --test test/"
  },
  "dependencies": {
    "express": "^4.19.2",
    "playwright": "^1.47.0"
  }
}
```

- [ ] **Step 2: Instalar dependencias**

Run: `cd renderer && npm install`
Expected: se crea `node_modules/` y `package-lock.json` sin errores.

- [ ] **Step 3: Escribir `src/server.js` (solo health check por ahora)**

```js
const path = require('node:path');
const express = require('express');

function createApp({ publicDir, baseUrl, higgsfieldCredentials }) {
  const app = express();
  app.use(express.json({ limit: '2mb' }));

  // Iframe embedding must keep working for ver-presentacion.php — never set
  // X-Frame-Options or a frame-ancestors CSP here.
  app.use('/p', express.static(publicDir));

  app.get('/health', (req, res) => {
    res.json({ status: 'ok' });
  });

  return app;
}

function start() {
  const port = process.env.PORT || 3000;
  const publicDir = process.env.PUBLIC_DIR || path.join(__dirname, '..', 'public');
  const baseUrl = process.env.BASE_URL || `http://localhost:${port}`;
  const app = createApp({
    publicDir,
    baseUrl,
    higgsfieldCredentials: {
      keyId: process.env.HF_API_KEY_ID,
      keySecret: process.env.HF_API_KEY_SECRET,
    },
  });
  app.listen(port, () => {
    console.log(`propuesta-renderer listening on :${port}`);
  });
}

module.exports = { createApp, start };
```

- [ ] **Step 4: Escribir `src/index.js`**

```js
require('./server').start();
```

- [ ] **Step 5: Escribir `test/server.test.js` (health check)**

```js
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs/promises');
const os = require('node:os');
const path = require('node:path');
const { createApp } = require('../src/server');

test('GET /health returns status ok', async () => {
  const publicDir = await fs.mkdtemp(path.join(os.tmpdir(), 'renderer-public-'));
  const app = createApp({ publicDir, baseUrl: 'http://localhost:3000', higgsfieldCredentials: {} });
  const server = app.listen(0);
  const { port } = server.address();
  try {
    const response = await fetch(`http://localhost:${port}/health`);
    assert.equal(response.status, 200);
    assert.deepEqual(await response.json(), { status: 'ok' });
  } finally {
    server.close();
  }
});
```

- [ ] **Step 6: Correr el test**

Run: `cd renderer && npm test`
Expected: PASS (1 test).

- [ ] **Step 7: `.env.example` y `.dockerignore`**

`renderer/.env.example`:
```
PORT=3000
BASE_URL=https://propuestas.automatizatech.cl
HF_API_KEY_ID=
HF_API_KEY_SECRET=
```

`renderer/.dockerignore`:
```
node_modules
test
public
.env
```

- [ ] **Step 8: Commit**

```bash
git add renderer/package.json renderer/package-lock.json renderer/src/server.js renderer/src/index.js renderer/test/server.test.js renderer/.env.example renderer/.dockerignore
git commit -m "feat(renderer): scaffold propuesta-renderer con health check"
```

---

### Task 2: `escape.js` — escapar HTML de texto generado por IA

**Files:**
- Create: `renderer/src/escape.js`
- Test: `renderer/test/escape.test.js`

**Interfaces:**
- Produces: `escapeHtml(value: any): string`

- [ ] **Step 1: Escribir el test (falla primero)**

```js
const test = require('node:test');
const assert = require('node:assert/strict');
const { escapeHtml } = require('../src/escape');

test('escapes html special characters', () => {
  assert.equal(escapeHtml('<script>alert("x")</script>'), '&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;');
});

test('returns empty string for null/undefined', () => {
  assert.equal(escapeHtml(null), '');
  assert.equal(escapeHtml(undefined), '');
});

test('passes through plain text unchanged', () => {
  assert.equal(escapeHtml('Academia de Béisbol'), 'Academia de Béisbol');
});
```

- [ ] **Step 2: Correr y verificar que falla**

Run: `cd renderer && npm test`
Expected: FAIL — `Cannot find module '../src/escape'`.

- [ ] **Step 3: Implementar `src/escape.js`**

```js
function escapeHtml(value) {
  if (value === null || value === undefined) return '';
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

module.exports = { escapeHtml };
```

- [ ] **Step 4: Correr y verificar que pasa**

Run: `cd renderer && npm test`
Expected: PASS (4 tests en total).

- [ ] **Step 5: Commit**

```bash
git add renderer/src/escape.js renderer/test/escape.test.js
git commit -m "feat(renderer): escapeHtml para texto generado por IA"
```

---

### Task 3: `schema.js` — validar el payload de `POST /render`

**Files:**
- Create: `renderer/src/schema.js`
- Test: `renderer/test/schema.test.js`

**Interfaces:**
- Consumes: nada (función pura).
- Produces: `validatePayload(body): { valid: boolean, errors: string[] }`. Campos requeridos: `unique_id, client_name, company_name, challenge_title, challenge_text, solution_title, solution_text, benefits[], how_it_works[], pricing_rows[], next_steps[]`. `image_briefs` es opcional pero debe ser array si viene.

- [ ] **Step 1: Escribir el test**

```js
const test = require('node:test');
const assert = require('node:assert/strict');
const { validatePayload } = require('../src/schema');

const VALID_PAYLOAD = {
  unique_id: 'abc123',
  client_name: 'Jeffer Garcia',
  company_name: 'Academia de Béisbol X',
  challenge_title: 'Gestión manual',
  challenge_text: 'Texto',
  solution_title: 'Solución',
  solution_text: 'Texto',
  benefits: [{ title: 'A', text: 'B' }],
  how_it_works: [{ step_title: 'A', step_text: 'B' }],
  pricing_rows: [{ service: 'Sitio web', price_usd: 500, price_clp: 460000 }],
  next_steps: ['Aprobación'],
};

test('accepts a fully populated payload', () => {
  const result = validatePayload(VALID_PAYLOAD);
  assert.equal(result.valid, true);
  assert.deepEqual(result.errors, []);
});

test('rejects a payload missing required fields', () => {
  const result = validatePayload({});
  assert.equal(result.valid, false);
  assert.ok(result.errors.includes('missing or empty required field: unique_id'));
  assert.ok(result.errors.includes('missing or empty required array field: benefits'));
});

test('rejects a non-object body', () => {
  const result = validatePayload(null);
  assert.equal(result.valid, false);
  assert.deepEqual(result.errors, ['request body must be a JSON object']);
});

test('rejects image_briefs when present but not an array', () => {
  const result = validatePayload({ ...VALID_PAYLOAD, image_briefs: 'nope' });
  assert.equal(result.valid, false);
  assert.ok(result.errors.includes('image_briefs must be an array when present'));
});
```

- [ ] **Step 2: Correr y verificar que falla**

Run: `cd renderer && npm test`
Expected: FAIL — `Cannot find module '../src/schema'`.

- [ ] **Step 3: Implementar `src/schema.js`**

```js
const REQUIRED_STRING_FIELDS = [
  'unique_id',
  'client_name',
  'company_name',
  'challenge_title',
  'challenge_text',
  'solution_title',
  'solution_text',
];

const REQUIRED_ARRAY_FIELDS = ['benefits', 'how_it_works', 'pricing_rows', 'next_steps'];

function validatePayload(body) {
  const errors = [];

  if (!body || typeof body !== 'object' || Array.isArray(body)) {
    return { valid: false, errors: ['request body must be a JSON object'] };
  }

  for (const field of REQUIRED_STRING_FIELDS) {
    if (typeof body[field] !== 'string' || body[field].trim() === '') {
      errors.push(`missing or empty required field: ${field}`);
    }
  }

  for (const field of REQUIRED_ARRAY_FIELDS) {
    if (!Array.isArray(body[field]) || body[field].length === 0) {
      errors.push(`missing or empty required array field: ${field}`);
    }
  }

  if (body.image_briefs !== undefined && !Array.isArray(body.image_briefs)) {
    errors.push('image_briefs must be an array when present');
  }

  return { valid: errors.length === 0, errors };
}

module.exports = { validatePayload, REQUIRED_STRING_FIELDS, REQUIRED_ARRAY_FIELDS };
```

- [ ] **Step 4: Correr y verificar que pasa**

Run: `cd renderer && npm test`
Expected: PASS (8 tests en total).

- [ ] **Step 5: Commit**

```bash
git add renderer/src/schema.js renderer/test/schema.test.js
git commit -m "feat(renderer): validacion de payload para POST /render"
```

---

### Task 4: `higgsfield.js` — cliente REST de Higgsfield (submit + poll + fallback)

**Files:**
- Create: `renderer/src/higgsfield.js`
- Test: `renderer/test/higgsfield.test.js`

**Interfaces:**
- Consumes: nada de tasks anteriores.
- Produces: `generateProposalImages(imageBriefs: {slide: string, prompt: string}[], credentials: {keyId, keySecret}): Promise<Record<string, string|null>>` — mapa `slide -> url` (o `null` si falló esa imagen puntual). **Nunca lanza** por un fallo individual.

- [ ] **Step 1: Escribir el test**

```js
const test = require('node:test');
const assert = require('node:assert/strict');
const { generateProposalImages } = require('../src/higgsfield');

function makeFakeFetch({ submitOk = true, finalStatus = 'completed', imageUrl = 'https://cdn.example.com/img.jpg' } = {}) {
  return async (url) => {
    if (String(url).endsWith('/soul/standard')) {
      if (!submitOk) return { ok: false, status: 500 };
      return {
        ok: true,
        json: async () => ({
          status: 'queued',
          request_id: 'req-1',
          status_url: 'https://platform.higgsfield.ai/requests/req-1/status',
        }),
      };
    }
    return {
      ok: true,
      json: async () => ({ status: finalStatus, request_id: 'req-1', images: [{ url: imageUrl }] }),
    };
  };
}

test('resolves an image url per slide on success', async () => {
  const originalFetch = global.fetch;
  global.fetch = makeFakeFetch();
  try {
    const result = await generateProposalImages(
      [{ slide: 'cover', prompt: 'foto de academia de béisbol' }],
      { keyId: 'id', keySecret: 'secret' }
    );
    assert.equal(result.cover, 'https://cdn.example.com/img.jpg');
  } finally {
    global.fetch = originalFetch;
  }
});

test('resolves to null (not a thrown error) when the submit call fails', async () => {
  const originalFetch = global.fetch;
  global.fetch = makeFakeFetch({ submitOk: false });
  try {
    const result = await generateProposalImages(
      [{ slide: 'cover', prompt: 'foto' }],
      { keyId: 'id', keySecret: 'secret' }
    );
    assert.equal(result.cover, null);
  } finally {
    global.fetch = originalFetch;
  }
});

test('resolves to null when the job status ends up failed', async () => {
  const originalFetch = global.fetch;
  global.fetch = makeFakeFetch({ finalStatus: 'failed' });
  try {
    const result = await generateProposalImages(
      [{ slide: 'cover', prompt: 'foto' }],
      { keyId: 'id', keySecret: 'secret' }
    );
    assert.equal(result.cover, null);
  } finally {
    global.fetch = originalFetch;
  }
});

test('returns an empty object when there are no briefs', async () => {
  const result = await generateProposalImages(undefined, { keyId: 'id', keySecret: 'secret' });
  assert.deepEqual(result, {});
});

test('resolves multiple briefs independently, keyed by slide', async () => {
  const originalFetch = global.fetch;
  global.fetch = makeFakeFetch();
  try {
    const result = await generateProposalImages(
      [
        { slide: 'cover', prompt: 'a' },
        { slide: 'challenge', prompt: 'b' },
      ],
      { keyId: 'id', keySecret: 'secret' }
    );
    assert.equal(result.cover, 'https://cdn.example.com/img.jpg');
    assert.equal(result.challenge, 'https://cdn.example.com/img.jpg');
  } finally {
    global.fetch = originalFetch;
  }
});
```

- [ ] **Step 2: Correr y verificar que falla**

Run: `cd renderer && npm test`
Expected: FAIL — `Cannot find module '../src/higgsfield'`.

- [ ] **Step 3: Implementar `src/higgsfield.js`**

```js
const ENDPOINT = 'https://platform.higgsfield.ai/higgsfield-ai/soul/standard';
const POLL_INTERVAL_MS = 5000;
const POLL_TIMEOUT_MS = 60000;

function authHeader(keyId, keySecret) {
  return `Key ${keyId}:${keySecret}`;
}

async function submitImageRequest(prompt, { keyId, keySecret, fetchImpl = fetch } = {}) {
  const response = await fetchImpl(ENDPOINT, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Authorization: authHeader(keyId, keySecret),
    },
    body: JSON.stringify({ prompt, aspect_ratio: '16:9', resolution: '720p' }),
  });
  if (!response.ok) {
    throw new Error(`higgsfield submit failed: ${response.status}`);
  }
  return response.json();
}

async function pollUntilComplete(
  statusUrl,
  { keyId, keySecret, fetchImpl = fetch, intervalMs = POLL_INTERVAL_MS, timeoutMs = POLL_TIMEOUT_MS } = {}
) {
  const deadline = Date.now() + timeoutMs;
  while (Date.now() < deadline) {
    const response = await fetchImpl(statusUrl, { headers: { Authorization: authHeader(keyId, keySecret) } });
    if (!response.ok) {
      throw new Error(`higgsfield status check failed: ${response.status}`);
    }
    const data = await response.json();
    if (data.status === 'completed') return data;
    if (data.status === 'failed') throw new Error('higgsfield generation failed');
    await new Promise((resolve) => setTimeout(resolve, intervalMs));
  }
  throw new Error('higgsfield polling timed out');
}

async function generateImageUrl(prompt, credentials) {
  const submitted = await submitImageRequest(prompt, credentials);
  const completed = await pollUntilComplete(submitted.status_url, credentials);
  const url = completed.images && completed.images[0] && completed.images[0].url;
  if (!url) throw new Error('higgsfield response had no image url');
  return url;
}

/**
 * Generates one image per brief. Never throws — a failed brief resolves to
 * null so the template can fall back to a brand gradient for that slide.
 */
async function generateProposalImages(imageBriefs, credentials) {
  const briefs = Array.isArray(imageBriefs) ? imageBriefs : [];
  const results = await Promise.all(
    briefs.map(async (brief) => {
      try {
        const url = await generateImageUrl(brief.prompt, credentials);
        return { slide: brief.slide, url };
      } catch (err) {
        return { slide: brief.slide, url: null, error: err.message };
      }
    })
  );
  const bySlide = {};
  for (const result of results) {
    bySlide[result.slide] = result.url;
  }
  return bySlide;
}

module.exports = { submitImageRequest, pollUntilComplete, generateImageUrl, generateProposalImages };
```

- [ ] **Step 4: Correr y verificar que pasa**

Run: `cd renderer && npm test`
Expected: PASS (13 tests en total).

- [ ] **Step 5: Commit**

```bash
git add renderer/src/higgsfield.js renderer/test/higgsfield.test.js
git commit -m "feat(renderer): cliente REST de Higgsfield con fallback silencioso por imagen"
```

---

### Task 5: `template.js` — HTML de las 8 slides, estilo "Cinematográfico Oscuro"

**Files:**
- Create: `renderer/src/template.js`
- Test: `renderer/test/template.test.js`

**Interfaces:**
- Consumes: `escapeHtml` de `src/escape.js`.
- Produces: `renderProposalHtml(data, images = {}): string` — HTML completo de la propuesta. `images` es el mapa `slide -> url|null` que devuelve `generateProposalImages`. Claves de slide usadas: `cover, challenge, solution, benefits, how_it_works, pricing` (6 slides con foto; `next_steps` y el cierre no llevan foto).

- [ ] **Step 1: Escribir el test**

```js
const test = require('node:test');
const assert = require('node:assert/strict');
const { renderProposalHtml } = require('../src/template');

const DATA = {
  company_name: 'Academia de Béisbol X',
  client_name: 'Jeffer Garcia',
  challenge_title: 'Gestión manual que no escala',
  challenge_text: 'Texto de <b>desafío</b>',
  solution_title: 'Sitio + asistente virtual',
  solution_text: 'Texto de solución',
  benefits: [{ title: 'Ahorro de tiempo', text: 'Menos WhatsApp manual' }],
  how_it_works: [{ step_title: 'Kick-off', step_text: 'Reunión inicial' }],
  pricing_rows: [{ service: 'Sitio web', price_usd: 500, price_clp: 460000 }],
  pricing_note: 'Precio referencial',
  next_steps: ['Aprobación', 'Kick-off'],
};

test('includes company and client name', () => {
  const html = renderProposalHtml(DATA, {});
  assert.ok(html.includes('Academia de Béisbol X'));
  assert.ok(html.includes('Jeffer Garcia'));
});

test('escapes html found inside AI-generated text', () => {
  const html = renderProposalHtml(DATA, {});
  assert.ok(!html.includes('Texto de <b>desafío</b>'));
  assert.ok(html.includes('Texto de &lt;b&gt;desafío&lt;/b&gt;'));
});

test('renders exactly 8 slide sections', () => {
  const html = renderProposalHtml(DATA, {});
  const count = (html.match(/<section class="slide/g) || []).length;
  assert.equal(count, 8);
});

test('uses the real AutomatizaTech logo url as watermark', () => {
  const html = renderProposalHtml(DATA, {});
  assert.ok(html.includes('logo-automatiza-tech'));
  assert.ok(html.includes('at-watermark'));
});

test('falls back to a brand gradient when no image is provided', () => {
  const html = renderProposalHtml(DATA, {});
  assert.ok(html.includes('linear-gradient(135deg, #0d1b2a'));
});

test('uses a provided image url as the slide background', () => {
  const html = renderProposalHtml(DATA, { cover: 'https://example.com/photo.jpg' });
  assert.ok(html.includes("url('https://example.com/photo.jpg')"));
});

test('never sets X-Frame-Options style meta tags in the markup', () => {
  const html = renderProposalHtml(DATA, {});
  assert.ok(!html.toLowerCase().includes('x-frame-options'));
});
```

- [ ] **Step 2: Correr y verificar que falla**

Run: `cd renderer && npm test`
Expected: FAIL — `Cannot find module '../src/template'`.

- [ ] **Step 3: Implementar `src/template.js`**

```js
const { escapeHtml } = require('./escape');

const LOGO_URL =
  'https://automatizatech.cl/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech%20+%20slogan.png';

const BRAND_GRADIENTS = [
  'linear-gradient(135deg, #0d1b2a 0%, #12314a 100%)',
  'linear-gradient(135deg, #0a1622 0%, #0f2233 100%)',
  'linear-gradient(135deg, #0d1b2a 0%, #163a52 100%)',
];

function pickFallbackGradient(index) {
  return BRAND_GRADIENTS[index % BRAND_GRADIENTS.length];
}

function backgroundStyle(imageUrl, index) {
  if (imageUrl) {
    return `background-image: linear-gradient(180deg, rgba(13,27,42,.15) 0%, rgba(13,27,42,.55) 55%, rgba(13,27,42,.94) 100%), url('${escapeHtml(
      imageUrl
    )}'); background-size: cover; background-position: center;`;
  }
  return `background: ${pickFallbackGradient(index)};`;
}

function logoMark() {
  return `<div class="at-watermark"><img src="${LOGO_URL}" alt="AutomatizaTech" /></div>`;
}

function renderCoverSlide({ company_name, client_name, index, imageUrl }) {
  return `
    <section class="slide slide-cover" style="${backgroundStyle(imageUrl, index)}">
      ${logoMark()}
      <div class="slide-body">
        <p class="eyebrow">Propuesta de transformación digital</p>
        <h1>${escapeHtml(company_name)}</h1>
        <p class="lede">Preparado para ${escapeHtml(client_name)}</p>
        <div class="accent-bar"></div>
      </div>
    </section>`;
}

function renderContentSlide({ index, eyebrow, title, bodyHtml, imageUrl }) {
  return `
    <section class="slide slide-content" style="${backgroundStyle(imageUrl, index)}">
      ${logoMark()}
      <div class="slide-text">
        <p class="eyebrow">${String(index).padStart(2, '0')} · ${escapeHtml(eyebrow)}</p>
        <div class="accent-bar"></div>
        <h2>${escapeHtml(title)}</h2>
        ${bodyHtml}
      </div>
    </section>`;
}

function renderParagraphBody(text) {
  return `<p class="body-text">${escapeHtml(text)}</p>`;
}

function renderBulletListBody(items, formatter) {
  const lis = items.map((item) => `<li>${formatter(item)}</li>`).join('');
  return `<ul class="body-list">${lis}</ul>`;
}

function renderPricingBody(rows, note) {
  const trs = rows
    .map(
      (row) => `
      <tr>
        <td>${escapeHtml(row.service)}</td>
        <td>$${escapeHtml(row.price_usd)} USD ($${escapeHtml(row.price_clp)} CLP aprox)</td>
      </tr>`
    )
    .join('');
  const noteHtml = note ? `<p class="pricing-note">${escapeHtml(note)}</p>` : '';
  return `<table class="pricing-table"><tbody>${trs}</tbody></table>${noteHtml}`;
}

function renderClosingSlide() {
  return `
    <section class="slide slide-closing">
      <div class="closing-left"><div class="closing-mark">AT</div></div>
      <div class="closing-right">
        <h2>Hablemos</h2>
        <p class="contact-row">contacto@automatizatech.cl</p>
        <p class="contact-row">automatizatech.cl</p>
        <p class="contact-row">+56 9 2700 2984</p>
        <p class="contact-row">@automatizatech.cl</p>
      </div>
    </section>`;
}

const STYLE = `
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: -apple-system, "Segoe UI", Roboto, sans-serif; background: #0d1b2a; }
  .slide { position: relative; width: 1920px; height: 1080px; overflow: hidden; break-after: page; }
  .at-watermark { position: absolute; top: 32px; left: 32px; z-index: 3; opacity: .4; }
  .at-watermark img { width: 76px; display: block; }
  .accent-bar { width: 64px; height: 5px; background: #00d9c0; border-radius: 3px; margin: 20px 0; }
  .eyebrow { color: #00d9c0; font-size: 20px; letter-spacing: .15em; text-transform: uppercase; font-weight: 700; }
  .slide-cover .slide-body { position: absolute; left: 64px; right: 64px; bottom: 72px; z-index: 2; }
  .slide-cover h1 { color: #fff; font-size: 72px; font-weight: 800; max-width: 80%; }
  .slide-cover .lede { color: #c9d4e0; font-size: 28px; margin-top: 12px; }
  .slide-content .slide-text { position: absolute; left: 72px; top: 120px; width: 46%; z-index: 2; }
  .slide-content h2 { color: #fff; font-size: 46px; font-weight: 800; margin-bottom: 24px; }
  .body-text { color: #c9d4e0; font-size: 26px; line-height: 1.6; }
  .body-list { color: #c9d4e0; font-size: 24px; line-height: 2; padding-left: 28px; }
  .pricing-table { width: 100%; border-collapse: collapse; margin-top: 12px; }
  .pricing-table td { color: #c9d4e0; font-size: 24px; padding: 14px 0; border-bottom: 1px solid rgba(255,255,255,.12); }
  .pricing-note { color: #8ea3ba; font-size: 18px; margin-top: 16px; }
  .slide-closing { display: flex; }
  .closing-left { width: 42%; background: #0a1420; display: flex; align-items: center; justify-content: center; }
  .closing-mark { width: 160px; height: 160px; border-radius: 50%; background: #0d2b4e; color: #fff; font-weight: 800; font-size: 56px; display: flex; align-items: center; justify-content: center; border: 2px solid #1b3454; }
  .closing-right { width: 58%; background: linear-gradient(160deg, #0f2233, #0a1622); display: flex; flex-direction: column; justify-content: center; padding: 0 80px; }
  .closing-right h2 { color: #fff; font-size: 48px; margin-bottom: 24px; }
  .contact-row { color: #c9d4e0; font-size: 24px; margin-bottom: 10px; }
`;

function renderProposalHtml(data, images = {}) {
  const benefitsBody = renderBulletListBody(
    data.benefits,
    (b) => `<strong>${escapeHtml(b.title)}:</strong> ${escapeHtml(b.text)}`
  );
  const stepsBody = renderBulletListBody(
    data.how_it_works,
    (s) => `<strong>${escapeHtml(s.step_title)}:</strong> ${escapeHtml(s.step_text)}`
  );
  const nextStepsBody = renderBulletListBody(data.next_steps, (s) => escapeHtml(s));
  const pricingBody = renderPricingBody(data.pricing_rows, data.pricing_note);

  const slides = [
    renderCoverSlide({
      company_name: data.company_name,
      client_name: data.client_name,
      index: 0,
      imageUrl: images.cover,
    }),
    renderContentSlide({
      index: 2,
      eyebrow: 'El desafío actual',
      title: data.challenge_title,
      bodyHtml: renderParagraphBody(data.challenge_text),
      imageUrl: images.challenge,
    }),
    renderContentSlide({
      index: 3,
      eyebrow: 'Nuestra solución',
      title: data.solution_title,
      bodyHtml: renderParagraphBody(data.solution_text),
      imageUrl: images.solution,
    }),
    renderContentSlide({
      index: 4,
      eyebrow: 'Beneficios clave',
      title: 'Lo que gana ' + data.company_name,
      bodyHtml: benefitsBody,
      imageUrl: images.benefits,
    }),
    renderContentSlide({
      index: 5,
      eyebrow: '¿Cómo funciona?',
      title: 'Proceso de implementación',
      bodyHtml: stepsBody,
      imageUrl: images.how_it_works,
    }),
    renderContentSlide({
      index: 6,
      eyebrow: 'Inversión',
      title: 'Precio de la propuesta',
      bodyHtml: pricingBody,
      imageUrl: images.pricing,
    }),
    renderContentSlide({
      index: 7,
      eyebrow: 'Próximos pasos',
      title: 'Cómo seguimos',
      bodyHtml: nextStepsBody,
      imageUrl: null,
    }),
    renderClosingSlide(),
  ];

  return `<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8" />
<title>Propuesta AutomatizaTech · ${escapeHtml(data.company_name)}</title>
<style>${STYLE}</style>
</head>
<body>
${slides.join('\n')}
</body>
</html>`;
}

module.exports = {
  renderProposalHtml,
  renderCoverSlide,
  renderContentSlide,
  renderClosingSlide,
  renderParagraphBody,
  renderBulletListBody,
  renderPricingBody,
};
```

- [ ] **Step 4: Correr y verificar que pasa**

Run: `cd renderer && npm test`
Expected: PASS (20 tests en total).

- [ ] **Step 5: Commit**

```bash
git add renderer/src/template.js renderer/test/template.test.js
git commit -m "feat(renderer): plantilla de 8 slides estilo Cinematografico Oscuro"
```

---

### Task 6: `render.js` — Playwright: HTML a archivo `.html` + `.pdf`

**Files:**
- Create: `renderer/src/render.js`
- Test: `renderer/test/render.test.js`

**Interfaces:**
- Consumes: string HTML ya armado (de `template.js`, pero la función es agnóstica del contenido).
- Produces: `renderToFiles(html: string, outputDir: string): Promise<{ htmlPath: string, pdfPath: string }>`.

- [ ] **Step 1: Instalar navegadores de Playwright localmente (una sola vez)**

Run: `cd renderer && npx playwright install --with-deps chromium`
Expected: descarga Chromium sin errores (puede tardar unos minutos).

- [ ] **Step 2: Escribir el test**

```js
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs/promises');
const os = require('node:os');
const path = require('node:path');
const { renderToFiles } = require('../src/render');

test('renders a minimal html string to an html file and a non-empty pdf', async () => {
  const outputDir = await fs.mkdtemp(path.join(os.tmpdir(), 'renderer-test-'));
  const html = '<!doctype html><html><body><h1>Hola</h1></body></html>';
  const { htmlPath, pdfPath } = await renderToFiles(html, outputDir);

  const htmlContent = await fs.readFile(htmlPath, 'utf8');
  assert.ok(htmlContent.includes('Hola'));

  const pdfStat = await fs.stat(pdfPath);
  assert.ok(pdfStat.size > 1000, `expected a real pdf, got ${pdfStat.size} bytes`);
});
```

- [ ] **Step 3: Correr y verificar que falla**

Run: `cd renderer && npm test`
Expected: FAIL — `Cannot find module '../src/render'`.

- [ ] **Step 4: Implementar `src/render.js`**

```js
const fs = require('node:fs/promises');
const path = require('node:path');
const { chromium } = require('playwright');

async function renderToFiles(html, outputDir) {
  await fs.mkdir(outputDir, { recursive: true });
  const htmlPath = path.join(outputDir, 'index.html');
  const pdfPath = path.join(outputDir, 'presentation.pdf');
  await fs.writeFile(htmlPath, html, 'utf8');

  const browser = await chromium.launch();
  try {
    const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });
    await page.goto(`file://${htmlPath}`, { waitUntil: 'networkidle' });
    await page.pdf({
      path: pdfPath,
      width: '1920px',
      height: '1080px',
      printBackground: true,
    });
  } finally {
    await browser.close();
  }

  return { htmlPath, pdfPath };
}

module.exports = { renderToFiles };
```

- [ ] **Step 5: Correr y verificar que pasa**

Run: `cd renderer && npm test`
Expected: PASS (21 tests en total). Este test tarda más que los anteriores (unos segundos) porque lanza Chromium de verdad.

- [ ] **Step 6: Commit**

```bash
git add renderer/src/render.js renderer/test/render.test.js
git commit -m "feat(renderer): renderizar HTML a pagina + PDF con Playwright"
```

---

### Task 7: `server.js` — wiring completo de `POST /render`

**Files:**
- Modify: `renderer/src/server.js`
- Modify: `renderer/test/server.test.js`

**Interfaces:**
- Consumes: `validatePayload` (Task 3), `generateProposalImages` (Task 4), `renderProposalHtml` (Task 5), `renderToFiles` (Task 6).
- Produces: `POST /render` → `200 { view_url, pdf_url }` o `400 { error, details }` si el payload es inválido.

- [ ] **Step 1: Ampliar `test/server.test.js` con los casos de `/render`**

Agregar estos tests al archivo (dejando el de `/health` del Task 1 intacto):

```js
const { validatePayload } = require('../src/schema');

const VALID_PAYLOAD = {
  unique_id: 'test-proposal-1',
  client_name: 'Jeffer Garcia',
  company_name: 'Academia de Béisbol X',
  challenge_title: 'Gestión manual',
  challenge_text: 'Texto',
  solution_title: 'Solución',
  solution_text: 'Texto',
  benefits: [{ title: 'A', text: 'B' }],
  how_it_works: [{ step_title: 'A', step_text: 'B' }],
  pricing_rows: [{ service: 'Sitio web', price_usd: 500, price_clp: 460000 }],
  next_steps: ['Aprobación'],
  image_briefs: [],
};

test('POST /render returns view_url and pdf_url for a valid payload', async () => {
  const publicDir = await fs.mkdtemp(path.join(os.tmpdir(), 'renderer-public-'));
  const app = createApp({
    publicDir,
    baseUrl: 'http://localhost:3000',
    higgsfieldCredentials: { keyId: 'id', keySecret: 'secret' },
  });
  const server = app.listen(0);
  const { port } = server.address();

  try {
    const response = await fetch(`http://localhost:${port}/render`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(VALID_PAYLOAD),
    });
    assert.equal(response.status, 200);
    const body = await response.json();
    assert.equal(body.view_url, 'http://localhost:3000/p/test-proposal-1/index.html');
    assert.equal(body.pdf_url, 'http://localhost:3000/p/test-proposal-1/presentation.pdf');
  } finally {
    server.close();
  }
});

test('POST /render returns 400 for an invalid payload', async () => {
  const publicDir = await fs.mkdtemp(path.join(os.tmpdir(), 'renderer-public-'));
  const app = createApp({
    publicDir,
    baseUrl: 'http://localhost:3000',
    higgsfieldCredentials: { keyId: 'id', keySecret: 'secret' },
  });
  const server = app.listen(0);
  const { port } = server.address();

  try {
    const response = await fetch(`http://localhost:${port}/render`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({}),
    });
    assert.equal(response.status, 400);
  } finally {
    server.close();
  }
});

test('GET /p/{id}/index.html does not send X-Frame-Options (must stay iframe-embeddable)', async () => {
  const publicDir = await fs.mkdtemp(path.join(os.tmpdir(), 'renderer-public-'));
  await fs.mkdir(path.join(publicDir, 'existing'), { recursive: true });
  await fs.writeFile(path.join(publicDir, 'existing', 'index.html'), '<html></html>', 'utf8');

  const app = createApp({
    publicDir,
    baseUrl: 'http://localhost:3000',
    higgsfieldCredentials: { keyId: 'id', keySecret: 'secret' },
  });
  const server = app.listen(0);
  const { port } = server.address();

  try {
    const response = await fetch(`http://localhost:${port}/p/existing/index.html`);
    assert.equal(response.status, 200);
    assert.equal(response.headers.get('x-frame-options'), null);
  } finally {
    server.close();
  }
});
```

- [ ] **Step 2: Correr y verificar que fallan los 3 tests nuevos**

Run: `cd renderer && npm test`
Expected: FAIL — `/render` responde 404 (la ruta no existe todavía).

- [ ] **Step 3: Completar `src/server.js` con la ruta `/render`**

```js
const path = require('node:path');
const express = require('express');
const { validatePayload } = require('./schema');
const { generateProposalImages } = require('./higgsfield');
const { renderProposalHtml } = require('./template');
const { renderToFiles } = require('./render');

function createApp({ publicDir, baseUrl, higgsfieldCredentials }) {
  const app = express();
  app.use(express.json({ limit: '2mb' }));

  // Iframe embedding must keep working for ver-presentacion.php — never set
  // X-Frame-Options or a frame-ancestors CSP here.
  app.use('/p', express.static(publicDir));

  app.get('/health', (req, res) => {
    res.json({ status: 'ok' });
  });

  app.post('/render', async (req, res) => {
    const { valid, errors } = validatePayload(req.body);
    if (!valid) {
      return res.status(400).json({ error: 'invalid payload', details: errors });
    }

    const data = req.body;
    const images = await generateProposalImages(data.image_briefs, higgsfieldCredentials);
    const html = renderProposalHtml(data, images);
    const outputDir = path.join(publicDir, data.unique_id);

    try {
      await renderToFiles(html, outputDir);
    } catch (err) {
      return res.status(502).json({ error: 'render failed', details: err.message });
    }

    res.json({
      view_url: `${baseUrl}/p/${data.unique_id}/index.html`,
      pdf_url: `${baseUrl}/p/${data.unique_id}/presentation.pdf`,
    });
  });

  return app;
}

function start() {
  const port = process.env.PORT || 3000;
  const publicDir = process.env.PUBLIC_DIR || path.join(__dirname, '..', 'public');
  const baseUrl = process.env.BASE_URL || `http://localhost:${port}`;
  const app = createApp({
    publicDir,
    baseUrl,
    higgsfieldCredentials: {
      keyId: process.env.HF_API_KEY_ID,
      keySecret: process.env.HF_API_KEY_SECRET,
    },
  });
  app.listen(port, () => {
    console.log(`propuesta-renderer listening on :${port}`);
  });
}

module.exports = { createApp, start };
```

- [ ] **Step 4: Correr y verificar que pasa todo**

Run: `cd renderer && npm test`
Expected: PASS (24 tests en total). Este test tarda unos segundos extra por el render real con Playwright.

- [ ] **Step 5: Commit**

```bash
git add renderer/src/server.js renderer/test/server.test.js
git commit -m "feat(renderer): POST /render orquesta imagenes + plantilla + PDF"
```

---

### Task 8: Dockerfile + build local

**Files:**
- Create: `renderer/Dockerfile`

**Interfaces:**
- Produces: imagen Docker `propuesta-renderer` que expone el puerto 3000.

- [ ] **Step 1: Escribir `Dockerfile`**

```dockerfile
FROM mcr.microsoft.com/playwright:v1.47.0-jammy
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm install --omit=dev
COPY src ./src
RUN mkdir -p /app/public
ENV PORT=3000
ENV PUBLIC_DIR=/app/public
EXPOSE 3000
CMD ["node", "src/index.js"]
```

- [ ] **Step 2: Build local**

Run: `cd renderer && docker build -t propuesta-renderer:local .`
Expected: build exitoso sin errores.

- [ ] **Step 3: Smoke test del contenedor**

Run:
```bash
docker run --rm -p 3000:3000 -e HF_API_KEY_ID=test -e HF_API_KEY_SECRET=test propuesta-renderer:local &
sleep 2
curl -s http://localhost:3000/health
```
Expected: `{"status":"ok"}`. Después detener el contenedor (`docker stop` o `kill` el proceso en background).

- [ ] **Step 4: Commit**

```bash
git add renderer/Dockerfile
git commit -m "feat(renderer): Dockerfile basado en imagen oficial de Playwright"
```

---

### Task 9: `api-save-presentation.php`

**Files:**
- Create: `api-save-presentation.php` (raíz del repo)

**Interfaces:**
- Consumes: `at-rate-limit.php`, `at-webhook-verify.php` (ya existen, sin cambios).
- Produces: endpoint que hace `UPDATE wp_automatiza_propuestas SET gamma_iframe_url=?, pdf_path=? WHERE unique_link_id=?`.

- [ ] **Step 1: Crear el archivo**

```php
<?php
// api-save-presentation.php
// Endpoint para el microservicio propuesta-renderer: guarda el link de la
// presentacion renderizada y el PDF en la propuesta correspondiente.

require_once('wp-load.php');
require_once __DIR__ . '/at-rate-limit.php';
require_once __DIR__ . '/at-webhook-verify.php';

header('Content-Type: application/json');

if ( ! at_rate_limit_check( 'save_presentation', 20, 60 ) ) {
    at_rate_limit_reject( 60, 'save_presentation' );
}

$raw_body   = file_get_contents('php://input');
$n8n_secret = defined('AT_N8N_WEBHOOK_SECRET') ? AT_N8N_WEBHOOK_SECRET : '';

if ( $n8n_secret !== '' ) {
    $hmac_result = at_webhook_verify_hmac( $raw_body, $n8n_secret );
    if ( $hmac_result === false ) {
        http_response_code( 403 );
        echo json_encode( [ 'error' => 'Firma HMAC invalida.' ] );
        exit;
    }
    if ( $hmac_result === null ) {
        $allowed_ips = defined('AT_N8N_ALLOWED_IPS')
            ? array_map( 'trim', explode( ',', AT_N8N_ALLOWED_IPS ) )
            : [];
        if ( ! empty( $allowed_ips ) ) {
            $client_ip = at_rate_limit_client_ip();
            if ( ! in_array( $client_ip, $allowed_ips, true ) ) {
                http_response_code( 403 );
                echo json_encode( [ 'error' => 'Acceso no autorizado.' ] );
                exit;
            }
        } else {
            error_log( 'api-save-presentation: AT_N8N_WEBHOOK_SECRET configurado pero sin headers HMAC y sin AT_N8N_ALLOWED_IPS — solicitud aceptada en modo permisivo.' );
        }
    }
} else {
    error_log( 'api-save-presentation: AT_N8N_WEBHOOK_SECRET no configurado — ejecutando sin verificacion HMAC.' );
}

$data = json_decode( $raw_body, true );

if ( ! $data || empty( $data['unique_id'] ) ) {
    echo json_encode( [ 'error' => 'unique_id requerido' ] );
    exit;
}

$unique_id = sanitize_text_field( $data['unique_id'] );
$view_url  = isset( $data['view_url'] ) ? esc_url_raw( $data['view_url'] ) : '';
$pdf_url   = isset( $data['pdf_url'] ) ? esc_url_raw( $data['pdf_url'] ) : '';

if ( ! $view_url && ! $pdf_url ) {
    echo json_encode( [ 'error' => 'view_url o pdf_url requerido' ] );
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'automatiza_propuestas';

$update = [];
if ( $view_url ) {
    $update['gamma_iframe_url'] = $view_url;
}
if ( $pdf_url ) {
    $update['pdf_path'] = $pdf_url;
}

$updated = $wpdb->update( $table_name, $update, [ 'unique_link_id' => $unique_id ] );

if ( $updated === false ) {
    echo json_encode( [ 'error' => 'Database update failed', 'db_error' => $wpdb->last_error ] );
    exit;
}

echo json_encode( [ 'success' => true, 'unique_id' => $unique_id ] );
```

- [ ] **Step 2: Verificar sintaxis PHP**

Run (PowerShell, usando el PHP de WAMP): `& "C:\wamp64\bin\php\php8.x.x\php.exe" -l api-save-presentation.php` (ajustar la versión de PHP a la carpeta real de `C:\wamp64\bin\php`)
Expected: `No syntax errors detected`.

- [ ] **Step 3: Prueba manual local (con WAMP corriendo) — caso de error controlado**

Run (PowerShell):
```powershell
Invoke-RestMethod -Uri "http://localhost/automatiza-tech/api-save-presentation.php" -Method Post -ContentType "application/json" -Body '{}'
```
Expected: JSON `{"error":"unique_id requerido"}` — confirma que el endpoint responde JSON válido incluso sin datos (sin el bug de código duplicado fuera de `<?php ?>` que tenía `api-save-proposal.php`).

- [ ] **Step 4: Commit**

```bash
git add api-save-presentation.php
git commit -m "feat: endpoint api-save-presentation.php para guardar link/PDF de la propuesta renderizada"
```

---

### Task 10: Desplegar `propuesta-renderer` en Easypanel + dominio

**Files:** ninguno (trabajo de infraestructura, vía navegador — lo ejecuta Claude con Luis autenticado, según lo acordado en la sesión).

- [ ] **Step 1:** Abrir `https://kchiba.easypanel.host`, confirmar sesión activa (o pedirle a Luis que inicie sesión de nuevo si expiró).
- [ ] **Step 2:** Entrar al proyecto `n8n` → "Create Service" → tipo "App" (build desde Dockerfile). Nombrarlo `propuesta-renderer`.
- [ ] **Step 3:** Configurar el origen del build: repo Git de este proyecto (rama donde viven los cambios de este plan) apuntando a la carpeta `renderer/`, o subir la imagen ya buildeada — usar el método que el propio Easypanel ofrezca para Dockerfile-based apps (Git deploy es preferible para poder re-desplegar con `git push`).
- [ ] **Step 4:** Variables de entorno del servicio: `PORT=3000`, `PUBLIC_DIR=/app/public`, `BASE_URL=https://propuestas.automatizatech.cl`, `HF_API_KEY_ID=...`, `HF_API_KEY_SECRET=...` (los dos últimos tomados del archivo de credenciales de Luis, nunca escritos en el repo ni en el chat).
- [ ] **Step 5:** En "Domains" del servicio, agregar `propuestas.automatizatech.cl`. Confirmar con Luis que ya agregó el registro `A` en Hostinger (`propuestas` → `72.61.132.193`) antes de este paso, o esperar a que propague.
- [ ] **Step 6:** Deploy. Verificar `https://propuestas.automatizatech.cl/health` devuelve `{"status":"ok"}` una vez que el certificado SSL esté emitido.

---

### Task 11: n8n — nuevo esquema de `OpenAI (Cerebro)`

**Files:** ninguno (edición remota vía `mcp__n8n-mcp__n8n_update_partial_workflow` sobre el workflow `APuTGmusbjLAJ74w`).

- [ ] **Step 1:** Leer el nodo actual con `n8n_get_workflow` modo `filtered`, `nodeNames: ["OpenAI (Cerebro)"]` para confirmar el `prompt.messages[0].content` exacto antes de tocarlo (puede haber cambiado desde la sesión de hoy).
- [ ] **Step 2:** Reemplazar ese `content` (vía `patchNodeField`, `fieldPath: "parameters.prompt.messages[0].content"`) manteniendo el contexto de negocio/tasa CLP existente, pero cambiando el "FORMATO DE SALIDA" a:

```
# FORMATO DE SALIDA (JSON ONLY)
Tu respuesta debe ser UNICAMENTE un objeto JSON válido con la siguiente estructura. Asegúrate de escapar las comillas dobles internas con barra invertida (\") para no romper el JSON.

{
  "client_name": "[Nombre de la persona extraído de la transcripción]",
  "company_name": "[Nombre de la empresa/emprendimiento del cliente]",
  "challenge_title": "[Título corto del desafío, ej: Gestión manual que no escala]",
  "challenge_text": "[Párrafo de 3-4 oraciones: situación actual, problemas específicos mencionados en la transcripción, impacto en el negocio. Usa datos concretos de la conversación e incluye el nombre del cliente y su empresa.]",
  "solution_title": "[Título corto de la solución, ej: Sitio web + asistente virtual 24/7]",
  "solution_text": "[Párrafo de 3-4 oraciones: solución técnica completa que AutomatizaTech implementará, tecnologías específicas (chatbots, sitio web, WhatsApp API, etc.), cómo resuelve cada problema mencionado.]",
  "benefits": [
    { "title": "[Beneficio corto]", "text": "[Explicación de 1 línea, específica y medible]" }
  ],
  "how_it_works": [
    { "step_title": "[Nombre del paso]", "step_text": "[Qué implica ese paso]" }
  ],
  "pricing_rows": [
    { "service": "[Nombre del servicio]", "price_usd": [numero], "price_clp": [numero, ya multiplicado por la tasa {{ $json.dolar.valor }}] }
  ],
  "pricing_note": "[Nota corta sobre la inversión, ej: recuperación rápida gracias a X]",
  "next_steps": ["Aprobación de la propuesta", "Reunión de kick-off", "Implementación (X semanas)", "Capacitación y entrega"],
  "image_briefs": [
    { "slide": "cover", "prompt": "[Brief fotográfico cinematográfico, profesional, relacionado al rubro del cliente]" },
    { "slide": "challenge", "prompt": "[Brief fotográfico dramático que represente el problema del rubro]" },
    { "slide": "solution", "prompt": "[Brief fotográfico futurista de tecnología aplicada al rubro]" },
    { "slide": "benefits", "prompt": "[Brief fotográfico de personas de negocio productivas en el rubro]" },
    { "slide": "how_it_works", "prompt": "[Brief de diagrama/infografía visual de proceso tecnológico]" },
    { "slide": "pricing", "prompt": "[Brief fotográfico profesional relacionado a inversión/crecimiento en el rubro]" }
  ],
  "email_subject": "Propuesta AutomatizaTech para [Nombre Empresa]: [Título del Proyecto]",
  "email_body_summary": "Hola [Nombre del Cliente],\n\nGracias por la reunión de hoy. Fue un placer conocer más sobre [Nombre Empresa] y sus objetivos de crecimiento.\n\nResumen de nuestra propuesta:\n\n📌 Situación Actual:\n[Resumen del problema en 2-3 líneas]\n\n✅ Solución Propuesta:\n[Resumen de la solución en 2-3 líneas]\n\n💰 Inversión Estimada: $[Precio] USD ($[Precio CLP] CLP aprox)\n\nQuedamos atentos a tus comentarios.\n\nSaludos cordiales,\nEquipo AutomatizaTech"
}

Reglas estrictas: "benefits" debe tener 4-5 ítems. "how_it_works" debe tener 3 pasos. "pricing_rows" al menos 1 fila. "next_steps" 4 ítems. "image_briefs" debe tener EXACTAMENTE 6 ítems con esos 6 valores de "slide", en ese orden exacto.
```

- [ ] **Step 3:** `n8n_validate_workflow` sobre `APuTGmusbjLAJ74w` — 0 errores.
- [ ] **Step 4:** Ejecutar manualmente el nodo (o el workflow completo con datos de prueba) desde n8n y confirmar que `Limpiar JSON` produce un objeto con las claves nuevas y `image_briefs.length === 6`.

---

### Task 12: n8n — nodos `Renderizar Propuesta` y `Guardar Presentación`

**Files:** ninguno (edición remota vía `n8n_update_partial_workflow` sobre `APuTGmusbjLAJ74w`).

**Interfaces:**
- Consumes: salida de `Limpiar JSON` (Task 11) y de `Guardar en BD` (`unique_id`, sin cambios).
- Produces: `Renderizar Propuesta` deja `view_url`/`pdf_url` en `$json` para el nodo siguiente y para `Email Aprobación (Admin)` (Task 13).

- [ ] **Step 1:** `removeNode` del nodo `Preparar Prompt Gamma` (ya no aplica — su función la absorbe el nuevo nodo `Renderizar Propuesta`).
- [ ] **Step 2:** `addNode` — `Renderizar Propuesta` (`n8n-nodes-base.httpRequest`, typeVersion 4.2), método POST a `https://propuestas.automatizatech.cl/render`, body JSON armado con expresiones:

```json
{
  "unique_id": "={{ $('Guardar en BD').item.json.unique_id }}",
  "client_name": "={{ $('Limpiar JSON').item.json.client_name }}",
  "company_name": "={{ $('Limpiar JSON').item.json.company_name }}",
  "challenge_title": "={{ $('Limpiar JSON').item.json.challenge_title }}",
  "challenge_text": "={{ $('Limpiar JSON').item.json.challenge_text }}",
  "solution_title": "={{ $('Limpiar JSON').item.json.solution_title }}",
  "solution_text": "={{ $('Limpiar JSON').item.json.solution_text }}",
  "benefits": "={{ $('Limpiar JSON').item.json.benefits }}",
  "how_it_works": "={{ $('Limpiar JSON').item.json.how_it_works }}",
  "pricing_rows": "={{ $('Limpiar JSON').item.json.pricing_rows }}",
  "pricing_note": "={{ $('Limpiar JSON').item.json.pricing_note }}",
  "next_steps": "={{ $('Limpiar JSON').item.json.next_steps }}",
  "image_briefs": "={{ $('Limpiar JSON').item.json.image_briefs }}"
}
```

  `onError: "continueRegularOutput"` (un fallo del render nunca debe tumbar el envío del correo de aprobación — ver Task 13, que maneja el caso sin `view_url`).

- [ ] **Step 3:** `addNode` — `Guardar Presentación` (`n8n-nodes-base.httpRequest`), método POST a `https://automatizatech.cl/api-save-presentation.php`, body:

```json
{
  "unique_id": "={{ $('Guardar en BD').item.json.unique_id }}",
  "view_url": "={{ $json.view_url }}",
  "pdf_url": "={{ $json.pdf_url }}"
}
```

  `onError: "continueRegularOutput"`.

- [ ] **Step 4:** Rewire de conexiones: `Guardar en BD` → `Renderizar Propuesta` → `Guardar Presentación` → `Email Aprobación (Admin)`.
- [ ] **Step 5:** `n8n_validate_workflow` — 0 errores.

---

### Task 13: n8n — actualizar `Email Aprobación (Admin)`

**Files:** ninguno (edición remota).

- [ ] **Step 1:** `patchNodeField` sobre `parameters.html` del nodo `Email Aprobación (Admin)`: reemplazar el bloque "1. Generar Presentación (Gamma) — Copia este prompt y pégalo en Gamma" por:

```html
<h4>1. Presentación generada automáticamente</h4>
<p><a href="{{ $('Renderizar Propuesta').item.json.view_url }}" style="background-color:#00d9c0; color:#0d1b2a; padding:12px 24px; text-decoration:none; border-radius:5px; display:inline-block; font-weight:bold;">📊 Ver Presentación</a></p>
<p style="font-size:13px; color:#666;">Si este link no funciona, el render puede haber fallado — revisa <code>propuesta-renderer</code> en Easypanel o pega un link de respaldo manualmente en el panel de propuestas.</p>
```

  (el resto del correo — prompt de personalidad del bot, botón al panel de aprobación — se mantiene igual).

- [ ] **Step 2:** `n8n_validate_workflow` — 0 errores.

---

### Task 14: Verificación end-to-end real

- [ ] **Step 1:** Confirmar que `renderer/` (Tasks 1-9) está desplegado en Easypanel (Task 10) y responde en `https://propuestas.automatizatech.cl/health`.
- [ ] **Step 2:** Confirmar que `api-save-presentation.php` (Task 9) está subido a PROD (FTP, mismo destino que `api-save-proposal.php`).
- [ ] **Step 3:** Disparar una ejecución real del workflow `Propuesta AutomatizaTech (Con Tasa CLP)` (`APuTGmusbjLAJ74w`) — vía una transcripción de prueba nueva en la carpeta Drive "Transcripciones", o re-ejecutando manualmente con datos de ejemplo.
- [ ] **Step 4:** Verificar con `n8n_executions` (mode `error` o `full`) que `Renderizar Propuesta` y `Guardar Presentación` terminan en éxito.
- [ ] **Step 5:** Abrir `https://automatizatech.cl/wp-admin/admin.php?page=automatiza-proposals&edit_id={id}` y confirmar que "URL Iframe Gamma" y el PDF ya aparecen precargados.
- [ ] **Step 6:** Abrir `https://automatizatech.cl/ver-presentacion.php?id={unique_link_id}` y confirmar que la presentación se ve embebida correctamente (sin bloqueo de iframe).

---

## Self-Review (hecho por Claude al escribir este plan)

- **Cobertura del spec:** formato salida (Tasks 5-7), imágenes IA con fallback (Task 4), hosting en Easypanel (Task 10), estilo visual + logo real (Task 5), reuso de `gamma_iframe_url`/`pdf_path` sin cambios de schema (Task 9), manejo de errores (Tasks 4, 12, 13), testing (Tasks 1-9 con TDD, Task 14 end-to-end). Cubierto.
- **Placeholders:** ninguno — todo el código de los Tasks 1-9 es completo y ejecutable tal cual. Los Tasks 10-13 son pasos de infraestructura/configuración remota (no código de archivo), documentados paso a paso con valores reales (URLs, nombres de nodos, JSON exacto).
- **Consistencia de tipos/nombres:** `generateProposalImages` (Task 4) devuelve exactamente las claves `cover, challenge, solution, benefits, how_it_works, pricing` que `template.js` (Task 5) espera en `images`. `validatePayload` (Task 3) exige los mismos campos que `Renderizar Propuesta` (Task 12) envía. `createApp({ publicDir, baseUrl, higgsfieldCredentials })` se usa igual en `index.js` (Task 1) y en todos los tests (Tasks 1, 7).
