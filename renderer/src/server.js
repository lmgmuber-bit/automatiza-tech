const crypto = require('node:crypto');
const path = require('node:path');
const express = require('express');
const { validatePayload } = require('./schema');
const { generateProposalImages } = require('./higgsfield');
const { renderProposalHtml } = require('./template');
const { renderToFiles } = require('./render');
const { persistImages } = require('./images-store');
const { promptHash, readManifest, writeManifest, reusablePhotos } = require('./photo-manifest');

// Compara en tiempo constante: una respuesta que tarda distinto según cuántos caracteres calzan
// deja adivinar la clave de a uno.
function claveCorrecta(dada, esperada) {
  const a = Buffer.from(String(dada || ''), 'utf8');
  const b = Buffer.from(String(esperada), 'utf8');
  return a.length === b.length && crypto.timingSafeEqual(a, b);
}

// renderKey vacío (sin variable RENDER_KEY) deja /render abierto, como antes: así el código nuevo
// se puede desplegar antes de que n8n mande la clave. Con renderKey, /render exige la cabecera
// X-AT-Render-Key y la revisa antes que el payload, para no gastar en Higgsfield ni tocar el disco
// por un pedido ajeno. /health y las presentaciones públicas (/p/…) siguen sin clave.
function createApp({ publicDir, baseUrl, higgsfieldCredentials, renderKey = '' }) {
  const app = express();
  app.use(express.json({ limit: '2mb' }));

  // Iframe embedding must keep working for ver-presentacion.php — never set
  // X-Frame-Options or a frame-ancestors CSP here.
  app.use('/p', express.static(publicDir));

  app.get('/health', (req, res) => {
    res.json({ status: 'ok' });
  });

  app.post('/render', (req, res, next) => {
    if (!renderKey || claveCorrecta(req.get('x-at-render-key'), renderKey)) {
      return next();
    }
    return res.status(401).json({ error: 'unauthorized' });
  }, async (req, res) => {
    const { valid, errors } = validatePayload(req.body);
    if (!valid) {
      return res.status(400).json({ error: 'invalid payload', details: errors });
    }

    const data = req.body;

    // unique_id becomes a filesystem path segment via path.join below and is
    // echoed back in view_url/pdf_url: reject anything that is not a plain
    // token before touching Higgsfield or the filesystem, or a value like
    // "../evil" could write (and later serve) outside publicDir.
    if (typeof data.unique_id !== 'string' || !/^[A-Za-z0-9_-]{6,64}$/.test(data.unique_id)) {
      return res.status(400).json({ error: { message: 'unique_id inválido' } });
    }

    const outputDir = path.join(publicDir, data.unique_id);

    // Image generation (Higgsfield), HTML templating, and the Playwright
    // render must all be covered by the same guard: any of the three can
    // fail and none of them may crash the process or hang the request.
    // Images already in hand are used as-is and never regenerated. Higgsfield's
    // queue swings wildly (measured the same day: 13s for one image, and 122s
    // for another), and seven of those never fit inside one bounded HTTP
    // request — a proposal would come back with two photos and five gradients.
    // Passing them in sidesteps the queue entirely and doubles as the manual
    // escape hatch when an automated run degrades.
    const imageBriefs = Array.isArray(data.image_briefs) ? data.image_briefs : [];
    const dataImages = data.images && typeof data.images === 'object' && !Array.isArray(data.images)
      ? data.images
      : {};

    // A repeated /render for the same unique_id (n8n's "3 Final" flow retries
    // up to 3 times while anything is missing) must not pay Higgsfield again
    // for a brief whose prompt did not change: reuse the photo it already
    // saved to disk on a previous call. Explicitly provided images still win
    // over a reused one, same as they win over generating a fresh one.
    const existingManifest = await readManifest(outputDir);
    const reused = await reusablePhotos(imageBriefs, existingManifest, outputDir);
    const provided = Object.assign({}, reused, dataImages);
    const pending = imageBriefs.filter((b) => b && b.slide && !provided[b.slide]);

    const requested = imageBriefs.map((b) => b && b.slide).filter(Boolean);

    let images;
    let report;
    let generated;
    try {
      generated = await generateProposalImages(pending, higgsfieldCredentials);
      ({ images, report } = await persistImages(Object.assign({}, generated, provided), outputDir));
    } catch (err) {
      console.error('POST /render failed:', err.stack || err.message);
      return res.status(502).json({ error: 'render failed', details: err.message });
    }

    // A slide explicitly provided in data.images wins over a reused one (see
    // `provided` above), so it was not actually served by the manifest even
    // though it also had a reusable entry — and it must not be (re)recorded
    // either: a later retry without that override must not silently reuse a
    // file that the current prompt never produced.
    const reusedThisCall = Object.keys(reused).filter((slide) => !dataImages[slide]);

    // Record, for every brief slide that was actually generated by
    // Higgsfield or actually served from the manifest THIS call, which
    // prompt produced its final local file — so the next retry can tell
    // "same brief" from "prompt changed". A slide satisfied by data.images
    // this call is never written here, whether or not it also has a brief:
    // there is no guarantee the current prompt is what produced that file.
    // Built with a null-prototype target and copied key-by-key (never
    // Object.assign onto a plain {}) so a manifest.json entry literally
    // named "__proto__" — crafted or corrupted — is treated as ordinary
    // data. Object.assign({}, {"__proto__": {...}}) does NOT set an
    // enumerable "__proto__" key: it reassigns the target's own
    // [[Prototype]] to that value instead, so the entry silently vanishes
    // from what gets written back and the object's own property lookups get
    // confused. A null-prototype target has no such setter to trigger.
    //
    // Written as soon as persistImages succeeds — before the HTML/Playwright
    // render below — so that if renderToFiles fails after the photos are
    // already on disk, the manifest still records them and a retry (n8n's
    // "3 Final" flow retries /render up to 3 times while anything is
    // missing) reuses the files instead of paying Higgsfield again.
    const generatedSlides = new Set(Object.keys(generated || {}).filter((slide) => generated[slide]));
    const reusedSlides = new Set(reusedThisCall);
    const nextManifest = Object.create(null);
    for (const [slide, entry] of Object.entries(existingManifest || {})) {
      nextManifest[slide] = entry;
    }
    for (const brief of imageBriefs) {
      const slide = brief && brief.slide;
      if (!slide || !brief.prompt) continue;
      if (!generatedSlides.has(slide) && !reusedSlides.has(slide)) continue;
      const finalImage = images[slide];
      if (typeof finalImage !== 'string' || !finalImage.startsWith('img/')) continue;
      nextManifest[slide] = { file: finalImage.slice('img/'.length), hash: promptHash(brief.prompt) };
    }
    try {
      await writeManifest(outputDir, nextManifest);
    } catch (err) {
      console.error('POST /render: no se pudo guardar el manifiesto de fotos:', err.message);
    }

    let html;
    try {
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
        reused: reusedThisCall.length,
      },
    });
  });

  return app;
}

function start() {
  const port = process.env.PORT || 3000;
  const publicDir = process.env.PUBLIC_DIR || path.join(__dirname, '..', 'public');
  const baseUrl = process.env.BASE_URL || `http://localhost:${port}`;
  const renderKey = (process.env.RENDER_KEY || '').trim();
  const app = createApp({
    publicDir,
    baseUrl,
    higgsfieldCredentials: {
      keyId: process.env.HF_API_KEY_ID,
      keySecret: process.env.HF_API_KEY_SECRET,
    },
    renderKey,
  });
  app.listen(port, () => {
    // Nunca se imprime la clave: solo si está puesta.
    console.log(`propuesta-renderer listening on :${port} (/render ${renderKey ? 'exige clave' : 'abierto: sin RENDER_KEY'})`);
  });
}

module.exports = { createApp, start };
