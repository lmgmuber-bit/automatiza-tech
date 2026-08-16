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
    const outputDir = path.join(publicDir, data.unique_id);

    // Image generation (Higgsfield), HTML templating, and the Playwright
    // render must all be covered by the same guard: any of the three can
    // fail and none of them may crash the process or hang the request.
    let images;
    let html;
    try {
      images = await generateProposalImages(data.image_briefs, higgsfieldCredentials);
      html = renderProposalHtml(data, images);
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
