const path = require('node:path');
const express = require('express');
const { validatePayload } = require('./schema');
const { generateProposalImages } = require('./higgsfield');
const { renderProposalHtml } = require('./template');
const { renderToFiles } = require('./render');
const { persistImages } = require('./images-store');

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
    const provided = data.images && typeof data.images === 'object' && !Array.isArray(data.images)
      ? data.images
      : {};
    const pending = (Array.isArray(data.image_briefs) ? data.image_briefs : []).filter(
      (b) => b && b.slide && !provided[b.slide]
    );

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
