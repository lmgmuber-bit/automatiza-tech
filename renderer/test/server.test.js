const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs/promises');
const os = require('node:os');
const path = require('node:path');
const { createApp } = require('../src/server');
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

test('POST /render survives a Higgsfield image-generation failure without hanging or crashing', async () => {
  // Mirrors the fetch-mocking pattern from test/higgsfield.test.js: the
  // Higgsfield submit call returns a non-ok response for every brief.
  //
  // NOTE on the assertion below: generateProposalImages (src/higgsfield.js)
  // is documented and unit-tested (test/higgsfield.test.js: "resolves to
  // null (not a thrown error) when the submit call fails") to NEVER throw —
  // a failed brief resolves to { slide, url: null } so the template can
  // fall back to a brand gradient for that slide. So a Higgsfield outage
  // does not reach the /render route's try/catch as a rejection; it
  // degrades gracefully and the request still completes with 200. What
  // this test actually proves — the safety property that matters — is that
  // a Higgsfield failure does not hang the request or crash the process:
  // the response comes back promptly and a follow-up /health call still
  // succeeds.
  const originalFetch = global.fetch;
  global.fetch = async (url, init) => {
    // Only intercept the Higgsfield submit endpoint. Everything else
    // (including this test's own HTTP calls to the local Express server)
    // must keep hitting the real fetch, since higgsfield.js's default
    // fetchImpl and this test file both resolve to the same global.fetch.
    if (String(url).endsWith('/soul/v2/standard')) {
      return { ok: false, status: 500 };
    }
    return originalFetch(url, init);
  };

  const publicDir = await fs.mkdtemp(path.join(os.tmpdir(), 'renderer-public-'));
  const app = createApp({
    publicDir,
    baseUrl: 'http://localhost:3000',
    higgsfieldCredentials: { keyId: 'id', keySecret: 'secret' },
  });
  const server = app.listen(0);
  const { port } = server.address();

  try {
    const payload = {
      ...VALID_PAYLOAD,
      unique_id: 'higgsfield-failure-case',
      image_briefs: [{ slide: 'cover', prompt: 'foto de la academia' }],
    };
    const response = await fetch(`http://localhost:${port}/render`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    // Graceful degradation, not a hang or a crash.
    assert.equal(response.status, 200);
    const body = await response.json();
    assert.equal(body.view_url, 'http://localhost:3000/p/higgsfield-failure-case/index.html');

    const health = await fetch(`http://localhost:${port}/health`);
    assert.equal(health.status, 200);
  } finally {
    global.fetch = originalFetch;
    server.close();
  }
});

test('POST /render returns 502 (not a hang or crash) when the render pipeline throws, and the server stays responsive', async () => {
  const publicDir = await fs.mkdtemp(path.join(os.tmpdir(), 'renderer-public-'));
  // Force renderToFiles to throw: pre-create a plain file where it expects
  // to mkdir its output directory, so fs.mkdir(outputDir, { recursive: true })
  // rejects with EEXIST. This exercises the widened try/catch that now
  // spans generateProposalImages + renderProposalHtml + renderToFiles.
  await fs.writeFile(path.join(publicDir, 'blocked-output'), 'not a directory', 'utf8');

  const app = createApp({
    publicDir,
    baseUrl: 'http://localhost:3000',
    higgsfieldCredentials: { keyId: 'id', keySecret: 'secret' },
  });
  const server = app.listen(0);
  const { port } = server.address();

  try {
    const payload = { ...VALID_PAYLOAD, unique_id: 'blocked-output', image_briefs: [] };
    const response = await fetch(`http://localhost:${port}/render`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    assert.equal(response.status, 502);
    const body = await response.json();
    assert.equal(body.error, 'render failed');

    const health = await fetch(`http://localhost:${port}/health`);
    assert.equal(health.status, 200);
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
